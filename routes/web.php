<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $startTime = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

    return response()->json([
        'success'   => true,
        'service'   => 'JagoKasir API Service',
        'status'    => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'timezone'  => config('app.timezone', 'UTC'),
        'uptime'    => round(microtime(true) - $startTime, 4) . 's',
    ]);
});

Route::get('/docs/api-docs.json', function () {
    $openapiPath = resource_path('swagger/openapi.json');
    if (file_exists($openapiPath)) {
        return response()->json(json_decode(file_get_contents($openapiPath), true));
    }
    return response()->json(['error' => 'Dokumentasi API tidak ditemukan'], 404);
});

Route::get('/docs', function () {
    return '
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JagoKasir API Documentation</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.9.0/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.9.0/swagger-ui-bundle.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.9.0/swagger-ui-standalone-preset.js"></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: \'/docs/api-docs.json\',
        dom_id: \'#swagger-ui\',
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout",
        deepLinking: true
      });
    };
  </script>
</body>
</html>';
});
