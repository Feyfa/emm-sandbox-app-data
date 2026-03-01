<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        // User sudah di-set oleh ClerkAuthenticate middleware
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'            => $user->id,
                'clerk_user_id' => $user->clerk_user_id,
                'name'          => $user->name,
                'email'         => $user->email,
                'avatar_url'    => $user->avatar_url,
            ],
        ]);
    }
}
