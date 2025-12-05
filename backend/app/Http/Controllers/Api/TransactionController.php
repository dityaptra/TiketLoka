<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    // --- USER AREA ---

    // Proses Checkout (Dari Keranjang -> Jadi Transaksi)
    public function checkout(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'cart_ids' => 'required|array|min:1', // Wajib kirim array ID cart
            'cart_ids.*' => 'integer|exists:carts,id', // Validasi tiap item harus ada di DB
        ]);

        $userId = $request->user()->id;

        // 1. Ambil item keranjang SESUAI YANG DIPILIH user & MILIK user tersebut
        $carts = Cart::with('destination')
            ->where('user_id', $userId)
            ->whereIn('id', $request->cart_ids) // <--- FILTER KUNCI DISINI
            ->get();

        // Validasi: Jika user mencoba checkout ID cart orang lain / cart kosong
        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Tidak ada item valid yang dipilih'], 400);
        }

        return DB::transaction(function () use ($request, $carts, $userId) {

            // 2. Hitung Total
            $total = 0;
            foreach ($carts as $cart) {
                $total += $cart->destination->price * $cart->quantity;
            }

            $invoiceCode = 'INV-' . Carbon::now()->format('Ymd') . '-' . Str::upper(Str::random(4));

            // 3. Buat Transaksi (Langsung Success)
            $transaction = Transaction::create([
                'user_id' => $userId,
                'invoice_code' => $invoiceCode,
                'grand_total' => $total,
                'status' => 'success',
                'paid_at' => now(),
                'payment_method' => $request->payment_method,
            ]);

            // 4. Pindahkan item ke TransactionDetail
            // Kita kumpulkan ID cart yang berhasil diproses untuk dihapus nanti
            $processedCartIds = [];

            foreach ($carts as $cart) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'destination_id' => $cart->destination_id,
                    'quantity' => $cart->quantity,
                    'price_per_unit' => $cart->destination->price,
                    'subtotal' => $cart->destination->price * $cart->quantity,
                    'visit_date' => $cart->visit_date,
                ]);

                $processedCartIds[] = $cart->id;
            }

            // 5. Hapus HANYA item yang dipilih dari keranjang
            // Item yang tidak dipilih (tidak ada di array cart_ids) akan tetap aman di keranjang
            Cart::whereIn('id', $processedCartIds)->delete();

            return response()->json([
                'message' => 'Transaksi berhasil',
                'invoice_code' => $transaction->invoice_code,
                'data' => $transaction
            ], 201);
        });
    }

    // --- FITUR BELI LANGSUNG (Direct Buy) ---
    public function buyNow(Request $request)
    {
        // ... (Validasi tetap sama) ...
        $userId = $request->user()->id;
        $destination = \App\Models\Destination::findOrFail($request->destination_id);

        return DB::transaction(function () use ($request, $userId, $destination) {

            $totalAmount = $destination->price * $request->quantity;
            $invoiceCode = 'INV-' . Carbon::now()->format('Ymd') . '-' . Str::upper(Str::random(4));

            // [PERUBAHAN DISINI]
            $transaction = Transaction::create([
                'user_id' => $userId,
                'invoice_code' => $invoiceCode,
                'grand_total' => $totalAmount,

                // Ubah jadi SUCCESS langsung
                'status' => 'success',

                // Langsung isi waktu bayar
                'paid_at' => now(),

                'payment_method' => $request->payment_method,
            ]);

            // ... (Buat TransactionDetail tetap sama) ...
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'destination_id' => $destination->id,
                'quantity' => $request->quantity,
                'price_per_unit' => $destination->price,
                'subtotal' => $totalAmount,
                'visit_date' => $request->visit_date,
            ]);

            return response()->json([
                'message' => 'Transaksi berhasil & Pembayaran terkonfirmasi otomatis',
                'invoice_code' => $transaction->invoice_code,
                'data' => $transaction
            ], 201);
        });
    }

    // Riwayat Transaksi User
    public function myTransactions()
    {
        $transactions = Transaction::with(['details.destination'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $transactions]);
    }

    // Detail Satu Transaksi (Untuk User melihat Invoice)
    public function show($invoice_code)
    {
        $transaction = Transaction::with(['details.destination', 'user'])
            ->where('invoice_code', $invoice_code)
            ->firstOrFail();

        // Keamanan: Pastikan yang lihat adalah pemilik atau admin
        if ($transaction->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $transaction]);
    }

    // --- ADMIN AREA ---

    // Lihat Semua Transaksi (Bisa Filter Status)
    public function adminIndex(Request $request)
    {
        $query = Transaction::with('user');

        // Filter status (pending/success)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter tanggal (Sesuai Flowchart Admin)
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $transactions]);
    }
}
