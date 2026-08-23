<?php

namespace App\Support\Seguridad;

use Illuminate\Http\Request;

class IngresoProveedorAbonoReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = self::fecha($request->input('fecha_desde'))
            ?: now()->startOfMonth()->format('Y-m-d');
        $hasta = self::fecha($request->input('fecha_hasta'))
            ?: now()->endOfMonth()->format('Y-m-d');
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $resultado = strtoupper(trim((string) $request->input('resultado', '')));
        if (! in_array($resultado, ['', 'OK', 'REVISAR'], true)) {
            $resultado = '';
        }

        return [
            'empresa_id' => self::entero($request->input('empresa_id')),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'resultado' => $resultado,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'resultado' => $filtros['resultado'] ?? null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0 && $v !== '0');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = ['Período '.self::dmy($filtros['fecha_desde'] ?? '').' — '.self::dmy($filtros['fecha_hasta'] ?? '')];
        if (($filtros['resultado'] ?? '') === 'OK') {
            $partes[] = 'Solo OK';
        } elseif (($filtros['resultado'] ?? '') === 'REVISAR') {
            $partes[] = 'Solo sin ingresos';
        }

        return implode(' · ', $partes);
    }

    private static function fecha($valor): ?string
    {
        $v = trim((string) $valor);
        if ($v === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return $v;
    }

    private static function entero($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $n = (int) $valor;

        return $n > 0 ? $n : null;
    }

    private static function dmy(string $ymd): string
    {
        if ($ymd === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt ? $dt->format('d/m/Y') : $ymd;
    }
}
