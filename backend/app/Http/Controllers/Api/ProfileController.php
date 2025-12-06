<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Update Profile User (Nama, Email, HP, & Password)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            // Gunakan 'sometimes' agar tidak wajib dikirim
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:15',

            'email' => [
                'sometimes',
                'email',
                // Tetap cek unik, kecuali punya user ini sendiri
                Rule::unique('users')->ignore($user->id)
            ],

            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        // Cek satu per satu: Jika ada datanya, baru di-update
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $user
        ]);
    }
}
