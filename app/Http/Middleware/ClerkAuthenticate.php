<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserIdentity;
use Symfony\Component\HttpFoundation\Response;

class ClerkAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Decode JWT payload (base64url)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return response()->json(['message' => 'Invalid token format'], 401);
        }

        $payload = json_decode(base64_decode(
            str_replace(['-', '_'], ['+', '/'], $parts[1])
        ), true);

        if (!$payload || !isset($payload['sub'])) {
            return response()->json(['message' => 'Invalid token payload'], 401);
        }

        $clerkUserId = $payload['sub'];

        // Verifikasi token & ambil data user dari Clerk API
        $clerkResponse = Http::withToken(env('CLERK_SECRET_KEY'))
            ->get("https://api.clerk.com/v1/users/{$clerkUserId}");

        if (!$clerkResponse->ok()) {
            return response()->json(['message' => 'Unauthorized - invalid clerk user'], 401);
        }

        $clerkUser = $clerkResponse->json();

        // Ambil primary email
        $emailObj = collect($clerkUser['email_addresses'] ?? [])
            ->firstWhere('id', $clerkUser['primary_email_address_id'] ?? null);
        $email = $emailObj['email_address'] ?? null;

        // Ambil nama & avatar
        $name = trim(($clerkUser['first_name'] ?? '') . ' ' . ($clerkUser['last_name'] ?? ''));
        $avatarUrl = $clerkUser['image_url'] ?? null;

        // Cari atau buat user berdasarkan clerk_user_id
        $user = User::where('clerk_user_id', $clerkUserId)->first();

        if (!$user) {
            $user = User::create([
                'clerk_user_id' => $clerkUserId,
                'name'          => $name ?: 'User',
                'email'         => $email,
                'avatar_url'    => $avatarUrl,
                'password'      => '',
            ]);
        } else {
            $user->update([
                'name'       => $name ?: $user->name,
                'email'      => $email ?: $user->email,
                'avatar_url' => $avatarUrl,
            ]);
        }

        // Sync external accounts (social login) ke user_identities
        $externalAccounts = $clerkUser['external_accounts'] ?? [];
        foreach ($externalAccounts as $account) {
            $provider    = $account['provider'] ?? null;
            $providerUid = $account['id'] ?? null;
            $providerEmail = $account['email_address'] ?? null;

            if (!$provider || !$providerUid) {
                continue;
            }

            UserIdentity::firstOrCreate(
                ['provider_uid' => $providerUid],
                [
                    'user_id'  => $user->id,
                    'provider' => $provider,
                    'email'    => $providerEmail,
                ]
            );
        }

        // Set authenticated user untuk request ini
        Auth::setUser($user);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Attach clerk user data ke request attributes (optional)
        $request->attributes->set('clerk_user', $clerkUser);

        return $next($request);
    }
}
