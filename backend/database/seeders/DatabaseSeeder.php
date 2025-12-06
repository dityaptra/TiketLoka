<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Review; // <--- Jangan lupa import model Review

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Bersihkan tabel lama (Urutan penting agar tidak error Foreign Key)
        Schema::disableForeignKeyConstraints();
        Review::truncate();            // Hapus Review
        BookingDetail::truncate(); // Hapus Detail Transaksi
        Booking::truncate();       // Hapus Transaksi
        Destination::truncate();       // Hapus Wisata
        Category::truncate();          // Hapus Kategori
        User::truncate();              // Hapus User
        Schema::enableForeignKeyConstraints();

        echo "Mulai mengisi data dummy TiketLoka (With Reviews)...\n";

        // --- A. SEED USERS ---
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@tiketloka.com',
            'phone_number' => '081234567890',
            'role' => 'admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $customer1 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@tiketloka.com',
            'phone_number' => '089876543210',
            'role' => 'customer',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Kita tambah user kedua agar simulasi rating lebih real (ada 2 orang review)
        $customer2 = User::create([
            'name' => 'Siti Aminah',
            'email' => 'siti@tiketloka.com',
            'phone_number' => '085555555555',
            'role' => 'customer',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // --- B. SEED CATEGORIES ---
        $catBali = Category::create(['name' => 'Bali', 'slug' => 'bali']);
        $catJateng = Category::create(['name' => 'Jawa Tengah', 'slug' => 'jawa-tengah']);
        $catJakarta = Category::create(['name' => 'DKI Jakarta', 'slug' => 'dki-jakarta']);

        // --- C. SEED DESTINATIONS ---

        // Wisata 1: Tanah Lot (Akan kita beri 2 review nanti)
        $destTanahLot = Destination::create([
            'category_id' => $catBali->id,
            'name' => 'Tanah Lot Sunset',
            'slug' => 'tanah-lot-sunset',
            'description' => 'Nikmati pemandangan matahari terbenam yang ikonik di pura tengah laut Tanah Lot.',
            'price' => 50000,
            'location' => 'Tabanan, Bali',
            'image_url' => null,
            'is_active' => true,
            'meta_title' => 'Tiket Masuk Tanah Lot Bali Murah',
            'meta_description' => 'Beli tiket online Tanah Lot Bali termurah.',
            'meta_keywords' => 'tanah lot, wisata bali',
        ]);

        // Wisata 2: Borobudur (Akan kita beri 1 review)
        $destBorobudur = Destination::create([
            'category_id' => $catJateng->id,
            'name' => 'Candi Borobudur Sunrise',
            'slug' => 'candi-borobudur-sunrise',
            'description' => 'Saksikan keajaiban dunia Candi Borobudur di pagi hari.',
            'price' => 75000,
            'location' => 'Magelang, Jawa Tengah',
            'is_active' => true,
        ]);

        // Wisata 3: Dufan (Tidak ada review)
        $destDufan = Destination::create([
            'category_id' => $catJakarta->id,
            'name' => 'Dufan Ancol (Weekend)',
            'slug' => 'dufan-ancol-weekend',
            'description' => 'Dunia Fantasi Ancol, taman hiburan terbesar di Jakarta.',
            'price' => 250000,
            'location' => 'Ancol, Jakarta',
            'is_active' => true,
        ]);


        // 1. Transaksi SUKSES Budi -> Beli Tanah Lot
        $trx1 = Booking::create([
            'user_id' => $customer1->id,
            'booking_code' => 'TLX7K92M',
            'grand_total' => 100000,
            'status' => 'success', // WAJIB SUCCESS AGAR BISA REVIEW
            'payment_method' => 'bank_transfer',
            'paid_at' => now()->subDays(2),
        ]);
        BookingDetail::create([
            'booking_id' => $trx1->id,
            'destination_id' => $destTanahLot->id,
            'quantity' => 2,
            'price_per_unit' => 50000,
            'subtotal' => 100000,
            'visit_date' => Carbon::tomorrow(),
        ]);

        // 2. Transaksi SUKSES Siti -> Beli Tanah Lot (User berbeda beli barang sama)
        $trx2 = Booking::create([
            'user_id' => $customer2->id,
            'booking_code' => 'TLX7K93M',
            'grand_total' => 50000,
            'status' => 'success',
            'payment_method' => 'ewallet',
            'paid_at' => now()->subDays(1),
        ]);
        BookingDetail::create([
            'booking_id' => $trx2->id,
            'destination_id' => $destTanahLot->id,
            'quantity' => 1,
            'price_per_unit' => 50000,
            'subtotal' => 50000,
            'visit_date' => Carbon::tomorrow(),
        ]);

        // 3. Transaksi SUKSES Budi -> Beli Borobudur
        $trx3 = Booking::create([
            'user_id' => $customer1->id,
            'booking_code' => 'TLX7K94M',
            'grand_total' => 75000,
            'status' => 'success',
            'paid_at' => now()->subDays(5),
        ]);
        BookingDetail::create([
            'booking_id' => $trx3->id,
            'destination_id' => $destBorobudur->id,
            'quantity' => 1,
            'price_per_unit' => 75000,
            'subtotal' => 75000,
            'visit_date' => Carbon::tomorrow(),
        ]);

        // --- E. SEED REVIEWS ---

        // Review 1: Budi me-review Tanah Lot (Bintang 5)
        Review::create([
            'user_id' => $customer1->id,
            'destination_id' => $destTanahLot->id,
            'booking_id' => $trx1->id, // Terhubung ke Transaksi 1
            'rating' => 5,
            'comment' => 'Pemandangannya luar biasa indah! Sangat recommended untuk melihat sunset.',
            'created_at' => now()->subHours(5),
        ]);

        // Review 2: Siti me-review Tanah Lot (Bintang 4) -> Rata-rata jadi 4.5
        Review::create([
            'user_id' => $customer2->id,
            'destination_id' => $destTanahLot->id,
            'booking_id' => $trx2->id, // Terhubung ke Transaksi 2
            'rating' => 4,
            'comment' => 'Tempatnya bagus, tapi agak ramai pengunjung jadi susah parkir.',
            'created_at' => now()->subHours(2),
        ]);

        // Review 3: Budi me-review Borobudur (Bintang 5)
        Review::create([
            'user_id' => $customer1->id,
            'destination_id' => $destBorobudur->id,
            'booking_id' => $trx3->id,
            'rating' => 5,
            'comment' => 'Megah sekali! Guide-nya juga ramah.',
        ]);

        echo "Selesai! \n";
        echo "- Tanah Lot: 2 Reviews (Avg: 4.5)\n";
        echo "- Borobudur: 1 Review (Avg: 5.0)\n";
        echo "- Dufan: 0 Review\n";
    }
}
