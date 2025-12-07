<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Cart;
use App\Models\Destination; // Pastikan import Destination ada
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    /**
     * Checkout dari Keranjang Belanja
     */
    public function checkout(Request $request)
    {
        // 1. Validasi Input (Hanya terima QRIS & BCA VA)
        $request->validate([
            'payment_method' => 'required|in:qris,bca_va',
            'cart_ids' => 'required|array|min:1',
            'cart_ids.*' => 'integer|exists:carts,id',
        ]);

        $userId = $request->user()->id;

        // 2. Ambil item keranjang
        $carts = Cart::with('destination')
            ->where('user_id', $userId)
            ->whereIn('id', $request->cart_ids)
            ->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Tidak ada item valid yang dipilih'], 400);
        }

        return DB::transaction(function () use ($request, $carts, $userId) {

            // Hitung Total
            $total = 0;
            foreach ($carts as $cart) {
                $total += $cart->destination->price * $cart->quantity;
            }

            // Generate Booking Code (Format: TL + 6 Karakter Acak)
            do {
                $bookingCode = 'TL' . Str::upper(Str::random(6));
            } while (Booking::where('booking_code', $bookingCode)->exists());

            // Simpan Booking (Status: PENDING)
            $booking = Booking::create([
                'user_id' => $userId,
                'booking_code' => $bookingCode,
                'grand_total' => $total,
                'status' => 'pending',   // Belum Lunas
                'paid_at' => null,       // Belum ada waktu bayar
                'payment_method' => $request->payment_method,
            ]);

            // Pindahkan ke Booking Detail
            $processedCartIds = [];
            foreach ($carts as $cart) {
                BookingDetail::create([
                    'booking_id' => $booking->id,
                    'destination_id' => $cart->destination_id,
                    'quantity' => $cart->quantity,
                    'price_per_unit' => $cart->destination->price,
                    'subtotal' => $cart->destination->price * $cart->quantity,
                    'visit_date' => $cart->visit_date,
                ]);
                $processedCartIds[] = $cart->id;
            }

            // Hapus keranjang
            Cart::whereIn('id', $processedCartIds)->delete();

            // --- GENERATE PAYMENT INSTRUCTION ---
            $paymentDetails = $this->generatePaymentDetails($request->payment_method, $booking, $request->user());

            return response()->json([
                'message' => 'Booking berhasil dibuat. Silakan lakukan pembayaran.',
                'booking_code' => $booking->booking_code,
                'grand_total' => $booking->grand_total,
                'payment_details' => $paymentDetails, // Data untuk Frontend
                'data' => $booking
            ], 201);
        });
    }

    /**
     * Beli Langsung (Tanpa Keranjang)
     */
    public function buyNow(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'quantity' => 'required|integer|min:1',
            'visit_date' => 'required|date|after_or_equal:today',
            'payment_method' => 'required|in:qris,bca_va', // Validasi Metode
        ]);

        $userId = $request->user()->id;
        $destination = Destination::findOrFail($request->destination_id);

        return DB::transaction(function () use ($request, $userId, $destination) {

            $totalAmount = $destination->price * $request->quantity;

            do {
                $bookingCode = 'TL' . Str::upper(Str::random(6));
            } while (Booking::where('booking_code', $bookingCode)->exists());

            // Simpan Booking (Status: PENDING)
            $booking = Booking::create([
                'user_id' => $userId,
                'booking_code' => $bookingCode,
                'grand_total' => $totalAmount,
                'status' => 'pending',
                'paid_at' => null,
                'payment_method' => $request->payment_method,
            ]);

            BookingDetail::create([
                'booking_id' => $booking->id,
                'destination_id' => $destination->id,
                'quantity' => $request->quantity,
                'price_per_unit' => $destination->price,
                'subtotal' => $totalAmount,
                'visit_date' => $request->visit_date,
            ]);

            // --- GENERATE PAYMENT INSTRUCTION ---
            $paymentDetails = $this->generatePaymentDetails($request->payment_method, $booking, $request->user());

            return response()->json([
                'message' => 'Booking berhasil dibuat. Silakan lakukan pembayaran.',
                'booking_code' => $booking->booking_code,
                'grand_total' => $booking->grand_total,
                'payment_details' => $paymentDetails,
                'data' => $booking
            ], 201);
        });
    }

    /**
     * Helper: Generate Instruksi Pembayaran (Private Function)
     * Agar tidak menulis ulang logika yang sama di checkout & buyNow
     */
    private function generatePaymentDetails($method, $booking, $user)
    {
        $userPhone = $user->phone_number ?? '08123456789';

        // Hapus angka '0' di depan nomor HP (misal 0812 -> 812) untuk format VA
        $cleanPhone = substr($userPhone, 0, 1) === '0' ? substr($userPhone, 1) : $userPhone;

        if ($method === 'bca_va') {
            return [
                'payment_type' => 'virtual_account',
                'bank' => 'BCA',
                // Format: Kode Perusahaan (8001) + No HP
                'va_number' => '8001' . $cleanPhone,
                'expiry_time' => now()->addHours(24)->format('Y-m-d H:i:s'),
                'instruction' => [
                    '1. Buka M-BCA atau ATM BCA.',
                    '2. Pilih menu m-Transfer > BCA Virtual Account.',
                    '3. Masukkan Nomor VA: 8001' . $cleanPhone,
                    '4. Periksa detail tagihan TiketLoka.',
                    '5. Masukkan PIN Anda dan simpan bukti transfer.'
                ]
            ];
        } elseif ($method === 'qris') {
            return [
                'payment_type' => 'qris',
                // String QRIS Dummy (dicampur booking code biar unik)
                'qr_string' => '00020101021126580013.ID.CO.QRIS.WWW.TIKETLOKA.COM.ID.' . $booking->booking_code,
                'expiry_time' => now()->addMinutes(15)->format('Y-m-d H:i:s'),
                'instruction' => [
                    '1. Buka aplikasi e-wallet (GoPay, OVO, Dana) atau M-Banking.',
                    '2. Pilih menu Scan QRIS.',
                    '3. Arahkan kamera ke kode QR di atas.',
                    '4. Periksa nominal pembayaran.',
                    '5. Masukkan PIN untuk menyelesaikan pembayaran.'
                ]
            ];
        }

        return [];
    }

    /**
     * SIMULASI PAYMENT GATEWAY (Tombol "Saya Sudah Bayar")
     */
    public function pay(Request $request)
    {
        $request->validate([
            'booking_code' => 'required|exists:bookings,booking_code',
        ]);

        $booking = Booking::where('booking_code', $request->booking_code)->firstOrFail();

        // Validasi Status
        if ($booking->status === 'success') {
            return response()->json(['message' => 'Transaksi ini sudah lunas.'], 400);
        }

        // Validasi Pemilik
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update jadi LUNAS
        $booking->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pembayaran Berhasil! E-Tiket telah terbit.',
            'data' => $booking
        ]);
    }

    /**
     * Riwayat Booking Saya
     */
    public function myBookings()
    {
        $bookings = Booking::with(['details.destination'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    /**
     * Detail Booking (E-Ticket)
     */
    public function show($booking_code)
    {
        $booking = Booking::with(['details.destination', 'user'])
            ->where('booking_code', $booking_code)
            ->firstOrFail();

        if ($booking->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $booking]);
    }

    /**
     * Admin List Laporan
     */
    public function adminIndex(Request $request)
    {
        $query = Booking::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $bookings]);
    }
}
