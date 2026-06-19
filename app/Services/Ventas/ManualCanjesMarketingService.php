<?php

namespace App\Services\Ventas;

class ManualCanjesMarketingService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-canjes-marketing/contenido.php');

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: '')
                . '/seguridad/login',
            'version' => config('manual_canjes_marketing.version', '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config('manual_canjes_marketing.capturas', []),
        ]);
    }
}
