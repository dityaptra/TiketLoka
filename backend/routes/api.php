<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\DashboardController;


// --- PUBLIC ROUTES (Bisa diakses tanpa login) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Wisata & Kategori
Route::get('/destinations', [DestinationController::class, 'index']);
Route::get('/destinations/{slug}', [DestinationController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']); // <--- Untuk Menu Filter Frontend

// Review
Route::get('/reviews/{destinationId}', [ReviewController::class, 'index']);

// --- PROTECTED ROUTES (Harus Login) ---
Route::middleware('auth:sanctum')->group(function () {

    // Auth User
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::put('/profile', [ProfileController::class, 'update']);

    // Cart (Keranjang)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // Checkout (Dari Keranjang)
    Route::post('/checkout', [BookingController::class, 'checkout']);

    // Beli Langsung (Tanpa Keranjang)
    Route::post('/buy-now', [BookingController::class, 'buyNow']);
    
    // Route Simulasi Payment Gateway
    Route::post('/pay', [BookingController::class, 'pay']);
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::get('/bookings/{booking_code}', [BookingController::class, 'show']);

    // Kirim Review
    Route::post('/reviews', [ReviewController::class, 'store']);

    // --- ADMIN ONLY ROUTES ---
    Route::prefix('admin')->group(function () {

        // Dashboard Stats
        Route::get('/dashboard', [DashboardController::class, 'stats']); // <--- Route Dashboard

        // Manajemen Wisata
        Route::post('/destinations', [DestinationController::class, 'store']);

        // PENTING: Gunakan POST untuk update yang ada file gambarnya
        // Frontend nanti mengirim ke sini dengan form-data
        Route::post('/destinations/{id}', [DestinationController::class, 'update']);

        Route::delete('/destinations/{id}', [DestinationController::class, 'destroy']);

        // Manajemen Transaksi
        Route::get('/bookings', [BookingController::class, 'adminIndex']);
    });
});
