<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Destination;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        // 1. Total Pendapatan (Hanya dari transaksi sukses)
        $totalRevenue = Transaction::where('status', 'success')->sum('grand_total');

        // 2. Total Transaksi (Sukses)
        $totalTransactions = Transaction::where('status', 'success')->count();

        // 3. Total Tiket Terjual (Dari detail transaksi sukses)
        // Kita butuh join atau whereHas, tapi cara simpelnya hitung dari TransactionDetail
        // Asumsi model TransactionDetail sudah dibuat
        $totalTicketsSold = \App\Models\TransactionDetail::whereHas('transaction', function ($q) {
            $q->where('status', 'success');
        })->sum('quantity');

        // 4. Transaksi Terbaru (5 data terakhir)
        $recentTransactions = Transaction::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'total_tickets_sold' => (int) $totalTicketsSold,
                'total_users' => User::where('role', 'customer')->count(),
                'recent_transactions' => $recentTransactions
            ]
        ]);
    }
}
