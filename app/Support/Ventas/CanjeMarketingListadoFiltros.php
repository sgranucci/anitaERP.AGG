<?php

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del listado de canjes marketing (index / exportaciones).
 */
class CanjeMarketingListadoFiltros
{
    /**
     * @return array{
     *   empresa_id: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   ubicacion_ids: list<int>
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $ubicacionIds = $request->input('ubicacion_ids', []);
        if (! is_array($ubicacionIds)) {
            $ubicacionIds = $ubicacionIds !== '' && $ubicacionIds !== null ? [(int) $ubicacionIds] : [];
        }
        $ubicacionIds = array_values(array_unique(array_filter(array_map('intval', $ubicacionIds), fn (int $id) => $id > 0)));

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'ubicacion_ids' => $ubicacionIds,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['ubicacion_ids'] ?? []) !== []) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'ubicacion_ids' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $out['fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $out['fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }
        foreach ($filtros['ubicacion_ids'] ?? [] as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $out['ubicacion_ids'][] = $uid;
            }
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' && $hasta === '') {
            return ['', ''];
        }

        if ($desde === '') {
            $desde = $hasta;
        }
        if ($hasta === '') {
            $hasta = $desde;
        }

        try {
            $d = Carbon::parse($desde)->format('Y-m-d');
            $h = Carbon::parse($hasta)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }
}
