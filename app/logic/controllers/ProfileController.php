<?php

namespace App\Controllers;

use App\Utils\FileUploadUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function currentUser(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'phone'  => 'nullable|string|max:30',
            'bio'    => 'nullable|string|max:1000',
            'locale' => 'nullable|string|in:en,en-gb,es,fr',
        ]);

        $user = Auth::user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user'    => $user->only('id', 'name', 'email', 'phone', 'bio', 'locale'),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png|max:5120',
        ]);

        $user = Auth::user();

        if ($user->avatar_path && file_exists(PROJECT_ROOT . '/' . $user->avatar_path)) {
            @unlink(PROJECT_ROOT . '/' . $user->avatar_path);
        }

        $path = FileUploadUtil::store($request->file('avatar'), 'avatars');

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'success'    => true,
            'message'    => 'Avatar updated.',
            'avatar_url' => '/' . $path,
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => $request->input('password')]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}
