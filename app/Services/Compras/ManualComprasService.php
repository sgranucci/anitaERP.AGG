<?php

namespace App\Services\Compras;

class ManualComprasService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-compras/contenido.php');

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: '')
                . '/seguridad/login',
            'version' => config('manual_compras.version', '1.2'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config('manual_compras.capturas', []),
        ]);
    }
}
