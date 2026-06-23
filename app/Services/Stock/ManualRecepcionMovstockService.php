<?php

namespace App\Services\Stock;

class ManualRecepcionMovstockService
{
    public function meta(): array
    {
        $contenido = require base_path('docs/manual-recepcion-movstock/contenido.php');
        $contenido['secciones'] = $this->enriquecerSeccionesConHerramientas($contenido['secciones'] ?? []);

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                . (env('APP_CARPETA', '') ?: '')
                . '/seguridad/login',
            'version' => config('manual_recepcion_movstock.version', '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config('manual_recepcion_movstock.capturas', []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array<int, array<string, mixed>>
     */
    private function enriquecerSeccionesConHerramientas(array $secciones): array
    {
        $defs = require base_path('docs/manual-recepcion-movstock/herramientas.php');
        $comunesListado = $defs['comunes_listado'] ?? [];

        foreach ($secciones as $i => $sec) {
            $grupos = $sec['herramientas_grupos'] ?? null;
            if ($grupos !== null) {
                $secciones[$i]['herramientas_grupos'] = $this->resolverGruposHerramientas($grupos, $defs, $comunesListado);

                continue;
            }

            $clave = $sec['herramientas_clave'] ?? null;
            if ($clave === null) {
                continue;
            }

            $especificas = $defs[$clave] ?? [];
            $incluirComunes = $sec['herramientas_incluir_listado'] ?? false;
            $secciones[$i]['herramientas'] = $incluirComunes
                ? array_merge($comunesListado, $especificas)
                : $especificas;
            unset($secciones[$i]['herramientas_clave'], $secciones[$i]['herramientas_incluir_listado']);
        }

        return $secciones;
    }

    /**
     * @param  array<int, array{clave: string, titulo?: string, incluir_listado?: bool}>  $grupos
     * @param  array<string, array<int, array<string, string>>>  $defs
     * @param  array<int, array<string, string>>  $comunesListado
     * @return array<int, array{titulo: string, items: array<int, array<string, string>>}>
     */
    private function resolverGruposHerramientas(array $grupos, array $defs, array $comunesListado): array
    {
        $out = [];
        foreach ($grupos as $g) {
            $especificas = $defs[$g['clave']] ?? [];
            $items = ($g['incluir_listado'] ?? false)
                ? array_merge($comunesListado, $especificas)
                : $especificas;
            $out[] = [
                'titulo' => $g['titulo'] ?? 'Herramientas',
                'items' => $items,
            ];
        }

        return $out;
    }
}
