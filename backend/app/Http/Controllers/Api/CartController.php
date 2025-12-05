<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // Lihat isi keranjang
    public function index(Request $request)
    {
        // Ambil cart milik user yang sedang login + data destinasinya
        $carts = Cart::with('destination')->where('user_id', $request->user()->id)->get();

        return response()->json(['data' => $carts]);
    }

    // Tambah ke keranjang
    public function store(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'quantity' => 'required|integer|min:1',
            'visit_date' => 'required|date|after_or_equal:today',
        ]);

        // Cek apakah item yang sama di tanggal yang sama sudah ada di keranjang?
        // Jika ya, kita update quantity-nya saja (Optional logic)
        $existingCart = Cart::where('user_id', $request->user()->id)
            ->where('destination_id', $request->destination_id)
            ->where('visit_date', $request->visit_date)
            ->first();

        if ($existingCart) {
            $existingCart->increment('quantity', $request->quantity);
            return response()->json(['message' => 'Jumlah tiket diperbarui', 'data' => $existingCart]);
        }

        // Jika belum ada, buat baru
        $cart = Cart::create([
            'user_id' => $request->user()->id,
            'destination_id' => $request->destination_id,
            'quantity' => $request->quantity,
            'visit_date' => $request->visit_date,
        ]);

        return response()->json(['message' => 'Berhasil masuk keranjang', 'data' => $cart], 201);
    }

    // Hapus item keranjang
    public function destroy($id)
    {
        $cart = Cart::where('user_id', Auth::id())->where('id', $id)->firstOrFail();
        $cart->delete();

        return response()->json(['message' => 'Item dihapus dari keranjang']);
    }
}
