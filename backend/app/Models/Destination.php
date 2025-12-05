<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'location',
        'image_url',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
    ];

    // Satu Destinasi milik satu Kategori
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relasi ke Review
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Accessor: Menghitung rata-rata rating secara otomatis
    // Cara panggil di frontend nanti: destination.average_rating
    public function getAverageRatingAttribute()
    {
        return round($this->reviews()->avg('rating'), 1); // Hasil: 4.5, 3.2, dst
    }

    // Accessor: Menghitung jumlah review
    public function getTotalReviewsAttribute()
    {
        return $this->reviews()->count();
    }

    // Tambahkan ke $appends agar muncul di JSON response
    protected $appends = ['average_rating', 'total_reviews'];
}
