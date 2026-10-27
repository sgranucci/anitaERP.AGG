<?php

namespace App\Services\Ayuda;

class ManualBuscadorService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-buscador/contenido.php');

        return array_merge($contenido, [
            'empresa' => (string) config('app.empresa'),
            'url_login' => url('/seguridad/login'),
            'version' => (string) ($contenido['version'] ?? '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
        ]);
    }
}
