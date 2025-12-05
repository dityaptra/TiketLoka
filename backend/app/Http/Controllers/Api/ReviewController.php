<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Kirim Review
    public function store(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->id;

        // Cari transaksi milik user ini, yang statusnya 'success', 
        // dan di dalamnya ada item tiket wisata yang mau direview.
        $validTransaction = Transaction::where('user_id', $userId)
            ->where('status', 'success')
            ->whereHas('details', function ($query) use ($request) {
                $query->where('destination_id', $request->destination_id);
            })
            ->latest()
            ->first();

        if (!$validTransaction) {
            return response()->json([
                'message' => 'Anda harus membeli tiket wisata ini dan menyelesaikan pembayaran sebelum memberi review.'
            ], 403);
        }

        // Apakah user sudah pernah review untuk transaksi ini?
        $existingReview = Review::where('user_id', $userId)
            ->where('destination_id', $request->destination_id)
            ->where('transaction_id', $validTransaction->id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'Anda sudah memberikan ulasan untuk pembelian ini.'], 409);
        }

        // 3. SIMPAN REVIEW
        $review = Review::create([
            'user_id' => $userId,
            'destination_id' => $request->destination_id,
            'transaction_id' => $validTransaction->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Terima kasih atas ulasan Anda!',
            'data' => $review
        ], 201);
    }

    // Lihat Review per Destinasi (Public)
    public function index($destinationId)
    {
        $reviews = Review::with('user:id,name') 
            ->where('destination_id', $destinationId)
            ->latest()
            ->paginate(5);

        return response()->json($reviews);
    }
}
