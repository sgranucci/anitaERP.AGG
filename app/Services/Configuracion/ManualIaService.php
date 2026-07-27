<?php

namespace App\Services\Configuracion;

class ManualIaService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-ia/contenido.php');
        $contenido['secciones'] = $this->enriquecerSeccionesConHerramientas($contenido['secciones'] ?? []);

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: '')
                .'/seguridad/login',
            'version' => config('manual_ia.version', '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config('manual_ia.capturas', []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array<int, array<string, mixed>>
     */
    private function enriquecerSeccionesConHerramientas(array $secciones): array
    {
        $defs = require base_path('docs/manual-ia/herramientas.php');

        foreach ($secciones as $i => $sec) {
            $grupos = $sec['herramientas_grupos'] ?? null;
            if ($grupos === null) {
                continue;
            }
            $secciones[$i]['herramientas_grupos'] = $this->resolverGruposHerramientas($grupos, $defs);
            unset($secciones[$i]['herramientas_clave'], $secciones[$i]['herramientas_incluir_listado']);
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
