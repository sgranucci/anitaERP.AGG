<?php

namespace App\Support\Seguridad;

use Illuminate\Http\Request;

class IngresoProveedorReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = self::fecha($request->input('fecha_desde'))
            ?: now()->startOfMonth()->format('Y-m-d');
        $hasta = self::fecha($request->input('fecha_hasta'))
            ?: now()->format('Y-m-d');
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $estado = strtoupper(trim((string) $request->input('estado', '')));
        if ($estado !== '' && ! in_array($estado, IngresoProveedorEstados::todos(), true)) {
            $estado = '';
        }

        $tipo = strtoupper(trim((string) $request->input('tipo', '')));
        if (! in_array($tipo, ['', IngresoProveedorVisitanteSupport::PROVEEDOR, IngresoProveedorVisitanteSupport::VISITANTE], true)) {
            $tipo = '';
        }

        return [
            'empresa_id' => self::entero($request->input('empresa_id')),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'estado' => $estado,
            'tipo' => $tipo,
            'motivo_id' => self::entero($request->input('motivo_id')),
            'punto_id' => self::entero($request->input('punto_id')),
            'sector_id' => self::entero($request->input('sector_id')),
            'area_id' => self::entero($request->input('area_id')),
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
            'estado' => $filtros['estado'] ?? null,
            'tipo' => $filtros['tipo'] ?? null,
            'motivo_id' => $filtros['motivo_id'] ?? null,
            'punto_id' => $filtros['punto_id'] ?? null,
            'sector_id' => $filtros['sector_id'] ?? null,
            'area_id' => $filtros['area_id'] ?? null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0 && $v !== '0');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Período '.self::dmy($filtros['fecha_desde'] ?? '').' — '.self::dmy($filtros['fecha_hasta'] ?? '');
        }
        if (! empty($filtros['estado'])) {
            $partes[] = 'Estado: '.IngresoProveedorEstados::etiqueta((string) $filtros['estado']);
        }
        if (($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::VISITANTE) {
            $partes[] = 'Tipo: Visitante';
        } elseif (($filtros['tipo'] ?? '') === IngresoProveedorVisitanteSupport::PROVEEDOR) {
            $partes[] = 'Tipo: Proveedor';
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
