<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\RegisterTenantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Services\AuthService;
use App\Services\TenantService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
        protected AuthService $authService,
        protected \App\Services\OtpService $otpService
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'type' => 'sometimes|string|in:registration,reset_password,verification',
        ]);

        $type = $validated['type'] ?? 'registration';

        // Additional validation based on type
        if ($type === 'registration') {
            $exists = User::where('email', $validated['email'])->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ini sudah terdaftar. Silakan masuk menggunakan akun Anda.',
                ], 422);
            }
        } elseif ($type === 'reset_password' || $type === 'verification') {
            $exists = User::where('email', $validated['email'])->exists();
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan dalam sistem kami.',
                ], 422);
            }
        }

        $result = $this->otpService->generateAndSend(
            $validated['email'], 
            $type
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Kode OTP berhasil dikirim ke ' . $validated['email'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim kode OTP: ' . ($result['error'] ?? 'Silakan periksa konfigurasi email atau coba lagi nanti.'),
        ], 500);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        Log::info('Verify OTP Request:', $request->all());
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'type' => 'required|string|in:registration,reset_password,verification',
        ]);

        $isValid = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            $validated['type'],
            false // Don't delete yet, wait for the final action
        );

        if ($isValid) {
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired OTP.',
        ], 422);
    }

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        Log::info('Register Request Data:', $request->all());
        $validated = $request->validated();

        // Verify OTP
        $isValid = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            'registration'
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 422);
        }

        $dto = RegisterTenantDTO::fromRequest($validated);
        $result = $this->tenantService->register($dto);

        return response()->json([
            'message' => 'Tenant registered successfully.',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if (!$result) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => $result,
        ]);
    }

    public function loginPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'pin' => 'required|string',
        ]);

        $result = $this->authService->loginWithPin($validated);

        if (!$result) {
            return response()->json([
                'message' => 'Invalid PIN or credentials.',
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => $result,
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'data' => auth('api')->user()->load(['roles', 'outlet', 'tenant.latestSubscription.plan']),
        ]);
    }

    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return response()->json([
            'message' => 'Token refreshed.',
            'data' => $result,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        Log::info('Reset Password Request:', $request->all());
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 1. Verify OTP
        $isValid = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            'reset_password'
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // 2. Update Password
        $user = User::where('email', $validated['email'])->first();
        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diatur ulang.',
        ]);
    }
}
