<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use Illuminate\Http\Request;

final class SicoreListadoFiltros
{
    /** @var array<string, string> */
    public const CRITERIOS = [
        'ventas' => 'Ventas (percepciones)',
        'compras' => 'Compras (retenciones IVA y ganancias)',
        'sueldos' => 'Sueldos (4ta categoría)',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $criterio = (string) $request->input('criterio', 'compras');
        if (! array_key_exists($criterio, self::CRITERIOS)) {
            $criterio = 'compras';
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'fecha_desde' => (string) $request->input('fecha_desde', date('Y-m-01')),
            'fecha_hasta' => (string) $request->input('fecha_hasta', date('Y-m-d')),
            'criterio' => $criterio,
            'conciliar_contable' => $request->boolean('conciliar_contable', true),
            'quincena' => max(0, min(2, (int) $request->input('quincena', 0))),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'criterio' => (string) ($filtros['criterio'] ?? ''),
        ];

        if (! empty($filtros['conciliar_contable'])) {
            $out['conciliar_contable'] = 1;
        }
        if ((int) ($filtros['quincena'] ?? 0) > 0) {
            $out['quincena'] = (int) $filtros['quincena'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['fecha_desde'] ?? '') !== ''
            && ($filtros['fecha_hasta'] ?? '') !== ''
            && ($filtros['criterio'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return '';
        }

        $fmt = static fn (string $iso) => date('d/m/Y', strtotime($iso));

        return $fmt($desde).' — '.$fmt($hasta);
    }
}
