<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    /**
     * Upload an UploadedFile instance to Cloudinary securely using signed requests.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string|null The uploaded secure URL or null on failure.
     */
    public static function upload(UploadedFile $file, string $folder = 'kasirku'): ?string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            Log::error('Cloudinary credentials missing in .env');
            return null;
        }

        $timestamp = time();
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        // Sort parameters alphabetically
        ksort($params);

        // Build string to sign: key=value&key2=value2
        $toSign = [];
        foreach ($params as $key => $value) {
            $toSign[] = "$key=$value";
        }
        $stringToSign = implode('&', $toSign) . $apiSecret;
        $signature = sha1($stringToSign);

        // Determine resource type: images -> image, others -> raw
        $mime = $file->getMimeType();
        $resourceType = 'image';
        if (strpos($mime, 'image/') === false && strpos($mime, 'video/') === false) {
            $resourceType = 'raw';
        }

        try {
            $response = Http::attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload", array_merge($params, [
                'api_key' => $apiKey,
                'signature' => $signature,
            ]));

            if ($response->successful()) {
                $data = $response->json();
                return $data['secure_url'] ?? $data['url'] ?? null;
            }

            Log::error('Cloudinary Upload Failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Cloudinary Upload Exception: ' . $e->getMessage());
            return null;
        }
    }
}
