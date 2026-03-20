<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Get public settings.
     */
    public function index(Request $request): JsonResponse
    {
        $keys = $request->input('keys');
        $query = SystemSetting::query();

        if ($keys) {
            $keyList = is_array($keys) ? $keys : explode(',', $keys);
            $query->whereIn('key', $keyList);
        } else {
            // By default, only allow reading specific public keys to prevent leakage
            $query->where('key', 'like', 'page_%')
                  ->orWhere('key', 'like', 'public_%');
        }

        $settings = $query->get()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * Get all settings (Super Admin).
     */
    public function adminIndex(): JsonResponse
    {
        $settings = SystemSetting::all()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * Update settings (Super Admin).
     */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->input('settings');

        if (!is_array($settings)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid settings format.',
            ], 422);
        }

        foreach ($settings as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
        ]);
    }
}
