<?php

declare(strict_types=1);

namespace App\Services\Manuales;

abstract class ManualContenidoService
{
    protected string $contenidoRelativo;

    protected string $configKey;

    protected ?string $herramientasRelativo = null;

    public function meta(): array
    {
        $contenido = require base_path($this->contenidoRelativo);
        $contenido['secciones'] = $this->enriquecerSecciones(
            $contenido['secciones'] ?? [],
        );

        return array_merge($contenido, [
            'empresa' => trim((string) env('EMPRESA', $contenido['empresa'] ?? ''), "'\""),
            'url_base' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: ''),
            'url_login' => rtrim(config('app.url', env('APP_URL', '')), '/')
                .(env('APP_CARPETA', '') ?: '')
                .'/seguridad/login',
            'version' => config($this->configKey.'.version', $contenido['version'] ?? '1.0'),
            'fecha' => now()->locale('es')->translatedFormat('F Y'),
            'capturas' => config($this->configKey.'.capturas', []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array<int, array<string, mixed>>
     */
    private function enriquecerSecciones(array $secciones): array
    {
        if ($this->herramientasRelativo === null) {
            return $secciones;
        }

        $defs = require base_path($this->herramientasRelativo);
        $comunes = $defs['comunes_listado'] ?? [];

        foreach ($secciones as $i => $seccion) {
            $grupos = $seccion['herramientas_grupos'] ?? null;
            if ($grupos !== null) {
                $resueltos = [];
                foreach ($grupos as $grupo) {
                    $items = $defs[$grupo['clave']] ?? [];
                    if ($grupo['incluir_listado'] ?? false) {
                        $items = array_merge($comunes, $items);
                    }
                    $resueltos[] = [
                        'titulo' => $grupo['titulo'] ?? 'Herramientas',
                        'items' => $items,
                    ];
                }
                $secciones[$i]['herramientas_grupos'] = $resueltos;

                continue;
            }

            $clave = $seccion['herramientas_clave'] ?? null;
            if ($clave === null) {
                continue;
            }
            $items = $defs[$clave] ?? [];
            if ($seccion['herramientas_incluir_listado'] ?? false) {
                $items = array_merge($comunes, $items);
            }
            $secciones[$i]['herramientas'] = $items;
            unset(
                $secciones[$i]['herramientas_clave'],
                $secciones[$i]['herramientas_incluir_listado'],
            );
        }

        return $secciones;
    }
}
