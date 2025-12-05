<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DestinationController extends Controller
{
    /**
     * PUBLIC: Menampilkan daftar wisata (List & Search)
     */
    public function index(Request $request)
    {
        // Mulai query, hanya ambil yang aktif
        $query = Destination::with('category')->where('is_active', true);

        // Fitur Search (Berdasarkan Nama)
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Fitur Filter Kategori
        if ($request->has('category_slug')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        // Urutkan dari yang terbaru
        $destinations = $query->latest()->get();

        // Transform data agar URL gambar lengkap
        $data = $destinations->map(function ($item) {
            $item->image_url = $item->image_url ? asset('storage/' . $item->image_url) : null;
            return $item;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * PUBLIC: Detail Wisata Single Page
     */
    public function show($slug)
    {
        $destination = Destination::with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        // Siapkan URL gambar
        $imageUrl = $destination->image_url ? asset('storage/' . $destination->image_url) : null;

        return response()->json([
            'data' => [
                'id' => $destination->id,
                'name' => $destination->name,
                'slug' => $destination->slug,
                'category' => $destination->category->name,
                'description' => $destination->description,
                'price' => $destination->price,
                'location' => $destination->location,
                'image_url' => $imageUrl,
                'is_active' => $destination->is_active,
            ],
            'seo' => [
                'title' => $destination->meta_title ?? $destination->name,
                'description' => $destination->meta_description ?? Str::limit(strip_tags($destination->description), 150),
                'keywords' => $destination->meta_keywords,
                'og_image' => $imageUrl,
            ]
        ]);
    }

    /**
     * ADMIN: Simpan Wisata Baru
     */
    public function store(Request $request)
    {
        // Validasi
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'location' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB

            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string',
        ]);

        // Generate Slug Otomatis
        $validated['slug'] = Str::slug($validated['name']);

        // Upload Gambar
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('destinations', 'public');
            $validated['image_url'] = $path;
        }

        // Simpan ke Database
        $destination = Destination::create($validated);

        return response()->json([
            'message' => 'Destinasi wisata berhasil ditambahkan',
            'data' => $destination
        ], 201);
    }

    /**
     * ADMIN: Update Wisata
     */
    public function update(Request $request, $id)
    {
        $destination = Destination::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'location' => 'sometimes|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Cek jika nama berubah, update slug juga
        if ($request->has('name')) {
            $validated['slug'] = Str::slug($request->name);
        }

        // Cek jika ada upload gambar baru
        if ($request->hasFile('image')) {
            // Hapus gambar lama
            if ($destination->image_url && Storage::disk('public')->exists($destination->image_url)) {
                Storage::disk('public')->delete($destination->image_url);
            }

            // Upload yang baru
            $path = $request->file('image')->store('destinations', 'public');
            $validated['image_url'] = $path;
        }

        $destination->update($validated);

        return response()->json([
            'message' => 'Data wisata berhasil diperbarui',
            'data' => $destination
        ]);
    }

    /**
     * ADMIN: Hapus Wisata
     */
    public function destroy($id)
    {
        $destination = Destination::findOrFail($id);

        // Hapus file gambar fisik dari storage
        if ($destination->image_url && Storage::disk('public')->exists($destination->image_url)) {
            Storage::disk('public')->delete($destination->image_url);
        }

        $destination->delete();

        return response()->json(['message' => 'Destinasi wisata berhasil dihapus']);
    }
}
