<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del informe de tickets canje caja (empresa, rango fechas, estado).
 */
final class TicketCanjeCajaReporteFiltros
{
    public const ESTADO_TODOS = '';

    public const ESTADO_PENDIENTE = 'P';

    public const ESTADO_CANJEADO = 'C';

    public const ESTADO_VIP = 'V';

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => null,
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'estado' => self::ESTADO_TODOS,
            'consultar' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $filtros = self::filtrosVacios();
        $empresaId = (int) $request->input('empresa_id', 0);
        $filtros['empresa_id'] = $empresaId > 0 ? $empresaId : null;
        $filtros['fecha_desde'] = self::normalizarFecha($request->input('fecha_desde'));
        $filtros['fecha_hasta'] = self::normalizarFecha($request->input('fecha_hasta'));
        $estado = strtoupper(trim((string) $request->input('estado', '')));
        $filtros['estado'] = in_array($estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_CANJEADO,
            self::ESTADO_VIP,
        ], true)
            ? $estado
            : self::ESTADO_TODOS;
        $filtros['consultar'] = $request->boolean('consultar') || $request->input('consultar') == '1';

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (! empty($filtros['empresa_id'])) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $out['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $out['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $out['estado'] = (string) $filtros['estado'];
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['empresa_id'])
            && ! empty($filtros['fecha_desde'])
            && ! empty($filtros['fecha_hasta']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Período '.$filtros['fecha_desde'].' a '.$filtros['fecha_hasta'];
        }
        $estado = (string) ($filtros['estado'] ?? '');
        $partes[] = match ($estado) {
            self::ESTADO_PENDIENTE => 'Estado: Pendiente',
            self::ESTADO_CANJEADO => 'Estado: Canjeado',
            self::ESTADO_VIP => 'Estado: VIP',
            default => 'Estado: Todos',
        };

        return implode(' · ', $partes);
    }

    private static function normalizarFecha(mixed $valor): ?string
    {
        $s = trim((string) ($valor ?? ''));
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }
}
