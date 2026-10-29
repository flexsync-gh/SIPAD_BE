<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    /**
     * Get the Google OAuth redirect URL.
     */
    public function googleRedirect(Request $request): JsonResponse
    {
        $driver = Socialite::driver('google')->stateless();

        if ($request->filled('redirect_uri')) {
            $driver->redirectUrl((string) $request->input('redirect_uri'));
        }

        $url = $driver->redirect()->getTargetUrl();

        return response()->json([
            'status' => 'success',
            'message' => 'URL redirect Google OAuth berhasil dibuat.',
            'url' => $url,
        ]);
    }

    /**
     * Handle the Google OAuth callback (via code or token).
     */
    public function googleCallback(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $token = $request->input('token') ?? $request->input('access_token');

        if (! $code && ! $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization code atau access token dari Google wajib disertakan.',
            ], 422);
        }

        try {
            $driver = Socialite::driver('google')->stateless();

            if ($request->filled('redirect_uri')) {
                $driver->redirectUrl((string) $request->input('redirect_uri'));
            }

            if ($token) {
                $googleUser = $driver->userFromToken((string) $token);
            } else {
                $googleUser = $driver->user();
            }
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memverifikasi akun Google: '.$e->getMessage(),
            ], 400);
        }

        // 1. Verifikasi status email dari Google OAuth
        $rawUser = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : ($googleUser->user ?? []);
        $isVerified = filter_var($rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $isVerified) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak, email belum diverifikasi oleh Google.',
            ], 403);
        }

        // 2. Verifikasi domain mail.ugm.ac.id
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $requiredDomain = '@mail.ugm.ac.id';

        if (! str_ends_with($email, $requiredDomain)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak, hanya email dengan domain @mail.ugm.ac.id yang diizinkan untuk login.',
            ], 403);
        }

        // 3. Verifikasi whitelist KRS (Tabel users dengan status = 'active')
        $user = User::where('email', $email)->first();

        if (! $user || $user->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak, email tidak terdaftar pada KRS aktif.',
            ], 403);
        }

        // 4. Update data Google pada user yang terdaftar
        $user->google_id_sub = (string) $googleUser->getId();
        $user->avatar_link = $googleUser->getAvatar();

        if (empty($user->name) && ! empty($googleUser->getName())) {
            $user->name = (string) $googleUser->getName();
        }

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }

        $user->save();

        // 5. Generate Personal Access Token Sanctum
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Autentikasi SSO UGM berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('studentProfile')),
        ]);
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('studentProfile');

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil pengguna yang sedang login berhasil diambil.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Log out user by revoking current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout, token telah dihapus.',
        ]);
    }
}
