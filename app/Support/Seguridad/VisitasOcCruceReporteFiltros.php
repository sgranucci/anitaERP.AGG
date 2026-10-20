<?php

namespace App\Support\Seguridad;

use Illuminate\Http\Request;

final class VisitasOcCruceReporteFiltros
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

        $soloConOc = $request->input('solo_con_oc', '1') !== '0';

        return [
            'empresa_id' => self::entero($request->input('empresa_id')),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'proveedor_id' => self::entero($request->input('proveedor_id')),
            'ordencompra_id' => self::entero($request->input('ordencompra_id')),
            'solo_con_oc' => $soloConOc,
            'estado' => self::estado($request->input('estado')),
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'proveedor_id' => $filtros['proveedor_id'] ?? null,
            'ordencompra_id' => $filtros['ordencompra_id'] ?? null,
            'solo_con_oc' => ($filtros['solo_con_oc'] ?? true) ? '1' : '0',
            'estado' => $filtros['estado'] ?? null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }

    public static function subtitulo(array $filtros): string
    {
        $partes = [
            'Período '.self::dmy($filtros['fecha_desde'] ?? '').' — '.self::dmy($filtros['fecha_hasta'] ?? ''),
        ];
        if ($filtros['solo_con_oc'] ?? true) {
            $partes[] = 'Solo visitas con OC';
        }
        if (! empty($filtros['estado'])) {
            $partes[] = 'Estado: '.IngresoProveedorEstados::etiqueta((string) $filtros['estado']);
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

    private static function estado($valor): string
    {
        $estado = strtoupper(trim((string) $valor));
        if ($estado !== '' && ! in_array($estado, IngresoProveedorEstados::todos(), true)) {
            return '';
        }

        return $estado;
    }

    private static function dmy(?string $ymd): string
    {
        $ymd = trim((string) $ymd);
        if ($ymd === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
