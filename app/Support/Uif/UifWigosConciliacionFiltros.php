<?php

namespace App\Support\Uif;

use Carbon\Carbon;
use Illuminate\Http\Request;

final class UifWigosConciliacionFiltros
{
    /**
     * @return array{anio: int, mes: int, empresa_id: int, consultar: bool}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $periodo = trim((string) $request->input('periodo', ''));
        $anio = (int) $request->input('anio', 0);
        $mes = (int) $request->input('mes', 0);

        if ($periodo !== '' && preg_match('/^(\d{4})-(\d{1,2})$/', $periodo, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
        }

        if ($anio <= 0) {
            $anio = (int) date('Y');
        }

        if ($mes <= 0) {
            $mes = (int) date('n');
        }

        $mes = max(1, min(12, $mes));
        $anio = max(2000, min(2100, $anio));

        return [
            'anio' => $anio,
            'mes' => $mes,
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /**
     * @param  array{anio?: int, mes?: int, empresa_id?: int, consultar?: bool}  $filtros
     * @return array<string, int|string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'periodo' => sprintf('%04d-%02d', (int) ($filtros['anio'] ?? 0), (int) ($filtros['mes'] ?? 0)),
        ];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        if (! empty($filtros['consultar'])) {
            $params['consultar'] = 1;
        }

        return $params;
    }

    /**
     * Parámetros para export PDF/Excel (sin flag consultar).
     *
     * @param  array{anio?: int, mes?: int, empresa_id?: int, consultar?: bool}  $filtros
     * @return array<string, int|string>
     */
    public static function paraQueryStringExport(array $filtros): array
    {
        $periodo = trim((string) ($filtros['periodo'] ?? ''));
        $anio = (int) ($filtros['anio'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);

        if ($periodo !== '' && preg_match('/^(\d{4})-(\d{1,2})$/', $periodo, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
        }

        $params = [
            'periodo' => sprintf('%04d-%02d', $anio, $mes),
        ];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * @param  array{anio?: int, mes?: int}  $filtros
     */
    public static function periodoTexto(array $filtros): string
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);

        if ($mes <= 0 || $anio <= 0) {
            return '';
        }

        return sprintf('%02d/%04d', $mes, $anio);
    }

    public static function enPeriodo(?Carbon $fecha, int $anio, int $mes): bool
    {
        if ($fecha === null) {
            return false;
        }

        return (int) $fecha->year === $anio && (int) $fecha->month === $mes;
    }
}
