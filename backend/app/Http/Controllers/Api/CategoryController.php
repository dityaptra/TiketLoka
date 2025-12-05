<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Mengambil semua kategori untuk dropdown filter di Frontend
    public function index()
    {
        $categories = Category::all();
        return response()->json(['data' => $categories]);
    }
}
