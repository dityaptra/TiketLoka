<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'destination_id',
        'quantity',
        'price_per_unit', // Harga saat beli (Snapshot)
        'subtotal',
        'visit_date',
    ];

    // Relasi: Detail ini milik transaksi nomor berapa?
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    // Relasi: Tiket wisata apa yang dibeli di detail ini?
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }
}
