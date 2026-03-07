<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function latest()
    {
        $latest = AppVersion::orderBy('version_code', 'desc')->first();

        if (!$latest) {
            return response()->json([
                'success' => false,
                'message' => 'No version found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version_name' => $latest->version_name,
                'version_code' => $latest->version_code,
                'release_notes' => $latest->release_notes,
                'is_critical' => (bool) $latest->is_critical,
                'download_url' => $latest->download_url,
                'created_at' => $latest->created_at,
            ]
        ]);
    }

    public function index()
    {
        $versions = AppVersion::orderBy('version_code', 'desc')->paginate(10);
        return response()->json([
            'success' => true,
            'data' => [
                'data' => $versions->items(),
                'meta' => [
                    'current_page' => $versions->currentPage(),
                    'last_page' => $versions->lastPage(),
                    'per_page' => $versions->perPage(),
                    'total' => $versions->total(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'version_name' => 'required|string',
            'version_code' => 'required|integer|unique:app_versions,version_code',
            'apk_file' => 'required|file|mimes:zip,apk,bin', // Bin for generic, apk might need extra config in some PHP setups
            'release_notes' => 'nullable|string',
            'is_critical' => 'boolean',
        ]);

        if ($request->hasFile('apk_file')) {
            $file = $request->file('apk_file');
            $fileName = 'jagokasir-v' . $request->version_name . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('updates', $fileName, 'public');

            $version = AppVersion::create([
                'version_name' => $request->version_name,
                'version_code' => $request->version_code,
                'file_path' => $path,
                'release_notes' => $request->release_notes,
                'is_critical' => $request->boolean('is_critical'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Version uploaded successfully',
                'data' => $version
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File upload failed',
        ], 400);
    }

    public function destroy($id)
    {
        $version = AppVersion::findOrFail($id);
        
        // Delete file from storage
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($version->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($version->file_path);
        }

        $version->delete();

        return response()->json([
            'success' => true,
            'message' => 'Version deleted successfully'
        ]);
    }
}
