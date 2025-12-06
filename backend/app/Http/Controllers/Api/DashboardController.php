<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Destination;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\BookingDetail;

class DashboardController extends Controller
{
    public function stats()
    {
        // 1. Total Pendapatan (Hanya dari transaksi sukses)
        $totalRevenue = Booking::where('status', 'success')->sum('grand_total');

        // 2. Total Transaksi (Sukses)
        $totalBookings = Booking::where('status', 'success')->count();

        // 3. Total Tiket Terjual (Dari detail transaksi sukses)
        $totalTicketsSold = BookingDetail::whereHas('booking', function ($q) {
            $q->where('status', 'success');
        })->sum('quantity');

        // 4. Transaksi Terbaru (5 data terakhir)
        $recentBookings = Booking::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_bookings' => $totalBookings,
                'total_tickets_sold' => (int) $totalTicketsSold,
                'total_users' => User::where('role', 'customer')->count(),
                'recent_bookings' => $recentBookings
            ]
        ]);
    }
}
