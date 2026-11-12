<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del informe comparativo de propinas por casino (Anita: l-propinaxcas.c).
 */
final class TesoreriaCrownPropinaCasinoFiltros
{
    /**
     * @return array{fecha_desde: string, fecha_hasta: string, consultar: bool}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = self::fechaOpcional($request->input('fecha_desde')) ?? date('Y-m-01');
        $hasta = self::fechaOpcional($request->input('fecha_hasta')) ?? date('Y-m-d');
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string, consultar?: bool}  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? date('Y-m-01')),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? date('Y-m-d')),
        ];
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string}  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $desde = self::fechaOpcional($filtros['fecha_desde'] ?? null) ?? date('Y-m-01');
        $hasta = self::fechaOpcional($filtros['fecha_hasta'] ?? null) ?? date('Y-m-d');

        return 'Período '.self::dmy($desde).' a '.self::dmy($hasta);
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string}  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::fechaOpcional($filtros['fecha_desde'] ?? null) !== null
            || self::fechaOpcional($filtros['fecha_hasta'] ?? null) !== null;
    }

    private static function fechaOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        return $texto;
    }

    private static function dmy(string $ymd): string
    {
        $partes = explode('-', $ymd);
        if (count($partes) !== 3) {
            return $ymd;
        }

        return $partes[2].'/'.$partes[1].'/'.$partes[0];
    }
}
