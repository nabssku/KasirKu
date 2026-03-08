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
        \Illuminate\Support\Facades\Log::info('App version upload attempt', [
            'version_name' => $request->version_name,
            'version_code' => $request->version_code,
            'has_file' => $request->hasFile('apk_file'),
        ]);

        $request->validate([
            'version_name' => 'required|string',
            'version_code' => 'required|integer|unique:app_versions,version_code',
            'apk_file' => 'required|file', // Removed strict mimes as it often fails for APKs
            'release_notes' => 'nullable|string',
            'is_critical' => 'boolean',
        ]);

        if ($request->hasFile('apk_file')) {
            $file = $request->file('apk_file');
            
            \Illuminate\Support\Facades\Log::info('Processing APK upload', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $fileName = 'jagokasir-v' . $request->version_name . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('updates', $fileName, 'public');

            $version = AppVersion::create([
                'version_name' => $request->version_name,
                'version_code' => $request->version_code,
                'file_path' => $path,
                'release_notes' => $request->release_notes,
                'is_critical' => $request->boolean('is_critical'),
            ]);

            \Illuminate\Support\Facades\Log::info('App version created successfully', ['id' => $version->id]);

            return response()->json([
                'success' => true,
                'message' => 'Version uploaded successfully',
                'data' => $version
            ]);
        }

        \Illuminate\Support\Facades\Log::error('App version upload failed: File missing');

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
