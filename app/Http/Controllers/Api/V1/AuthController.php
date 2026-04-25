<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\RegisterTenantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Services\AuthService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
        protected AuthService $authService,
        protected \App\Services\OtpService $otpService
    ) {}

    public function sendOtp(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'type' => 'sometimes|string|in:registration,reset_password,verification',
        ]);

        $success = $this->otpService->generateAndSend(
            $validated['email'], 
            $validated['type'] ?? 'registration'
        );

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to ' . $validated['email'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
        ], 500);
    }

    public function verifyOtp(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'type' => 'required|string|in:registration,reset_password,verification',
        ]);

        $isValid = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            $validated['type']
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
        $dto = RegisterTenantDTO::fromRequest($request->validated());
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

    public function loginPin(\Illuminate\Http\Request $request): JsonResponse
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

    public function resetPassword(\Illuminate\Http\Request $request): JsonResponse
    {
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
        $user = \App\Models\User::where('email', $validated['email'])->first();
        $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
