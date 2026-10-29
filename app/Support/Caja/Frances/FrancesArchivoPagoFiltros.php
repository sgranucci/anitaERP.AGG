<?php

declare(strict_types=1);

namespace App\Support\Caja\Frances;

use Illuminate\Http\Request;

/**
 * Filtros del archivo Francés: empresa, rango de fechas y cuenta de caja.
 */
final class FrancesArchivoPagoFiltros
{
    /**
     * @return array{empresa_id:int, fecha_desde:string, fecha_hasta:string, cuentacaja_id:int}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = self::fecha($request->input('fecha_desde'), date('Y-m-d', strtotime('-7 days')));
        $hasta = self::fecha($request->input('fecha_hasta'), date('Y-m-d'));
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cuentacaja_id' => max(0, (int) $request->input('cuentacaja_id', 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['fecha_desde'] ?? '') !== ''
            && ($filtros['fecha_hasta'] ?? '') !== ''
            && (int) ($filtros['cuentacaja_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'cuentacaja_id' => (int) ($filtros['cuentacaja_id'] ?? 0),
        ];
    }

    private static function fecha(mixed $valor, string $fallback): string
    {
        $texto = trim((string) $valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) === 1) {
            return $texto;
        }

        return $fallback;
    }
}
