<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'destination_id',
        'quantity',
        'visit_date',
    ];

    // Relasi: Item keranjang ini milik siapa?
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Item keranjang ini produk wisatanya apa?
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }
}
