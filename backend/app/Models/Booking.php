<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_code',
        'grand_total',
        'status',          // pending, success, failed
        'payment_method',
        'paid_at'
    ];

    // Casting agar 'paid_at' otomatis jadi objek Carbon (Date)
    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // Relasi: Transaksi ini milik user siapa?
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Transaksi ini punya detail item apa saja?
    public function details()
    {
        // Perhatikan nama methodnya 'details', modelnya 'BookingDetail'
        return $this->hasMany(BookingDetail::class);
    }
}
