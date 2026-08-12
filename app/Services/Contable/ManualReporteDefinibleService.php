<?php

namespace App\Services\Contable;

class ManualReporteDefinibleService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-reporte-definible/contenido.php');
        $contenido['secciones'] = $this->enriquecerSeccionesConHerramientas($contenido['secciones'] ?? []);

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: '')
                .'/seguridad/login',
            'version' => config('manual_reporte_definible.version', '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config('manual_reporte_definible.capturas', []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array<int, array<string, mixed>>
     */
    private function enriquecerSeccionesConHerramientas(array $secciones): array
    {
        $defs = require base_path('docs/manual-reporte-definible/herramientas.php');

        foreach ($secciones as $i => $sec) {
            $grupos = $sec['herramientas_grupos'] ?? null;
            if ($grupos === null) {
                continue;
            }
            $secciones[$i]['herramientas_grupos'] = $this->resolverGruposHerramientas($grupos, $defs);
        }

        return $secciones;
    }

    /**
     * @param  array<int, array{clave: string, titulo?: string}>  $grupos
     * @param  array<string, array<int, array<string, string>>>  $defs
     * @return array<int, array{titulo: string, items: array<int, array<string, string>>}>
     */
    private function resolverGruposHerramientas(array $grupos, array $defs): array
    {
        $out = [];
        foreach ($grupos as $g) {
            $out[] = [
                'titulo' => $g['titulo'] ?? 'Herramientas',
                'items' => $defs[$g['clave']] ?? [],
            ];
        }

        return $out;
    }
}
