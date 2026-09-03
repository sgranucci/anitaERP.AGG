<?php

declare(strict_types=1);

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del archivo ASCII de pagos Interbanking (espejo p-pagoxbanco).
 */
final class InterbankingArchivoPagoFiltros
{
    /**
     * @return array{
     *   empresa_id:int,
     *   cuentacaja_id:int,
     *   cbu_origen:string,
     *   fecha_desde:string,
     *   fecha_hasta:string,
     *   tipo_op:string,
     *   op_desde:int,
     *   op_hasta:int,
     *   tipo_aplicacion:string,
     *   fecha_solicitud:string,
     *   secuencia:int,
     *   incluir_anita:bool,
     *   incluir_erp:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $tipoOp = strtoupper(substr(trim((string) $request->input('tipo_op', 'OPP')), 0, 3));
        if ($tipoOp === '' || $tipoOp === '0') {
            $tipoOp = '0';
        }

        $opDesde = max(0, (int) $request->input('op_desde', 0));
        $opHasta = max(0, (int) $request->input('op_hasta', 99999999));
        if ($opHasta < $opDesde) {
            [$opDesde, $opHasta] = [$opHasta, $opDesde];
        }

        $fechaDesde = self::normalizarFecha($request->input('fecha_desde'), date('Y-m-d', strtotime('-7 days')));
        $fechaHasta = self::normalizarFecha($request->input('fecha_hasta'), date('Y-m-d'));
        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $consultando = $request->boolean('consultar');
        $tieneFlagsOrigen = $request->has('incluir_erp') || $request->has('incluir_anita') || $consultando;

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'cuentacaja_id' => max(0, (int) $request->input('cuentacaja_id', 0)),
            'cbu_origen' => preg_replace('/\D+/', '', (string) $request->input('cbu_origen', '')) ?? '',
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_op' => $tipoOp,
            'op_desde' => $opDesde,
            'op_hasta' => $opHasta > 0 ? $opHasta : 99999999,
            'tipo_aplicacion' => strtoupper(substr(trim((string) $request->input('tipo_aplicacion', '')), 0, 3)),
            'fecha_solicitud' => self::normalizarFecha(
                $request->input('fecha_solicitud'),
                date('Y-m-d')
            ),
            'secuencia' => max(1, (int) $request->input('secuencia', 1)),
            // Con flags en query (consulta/descarga): ausente = off. Primera carga: ambos on.
            'incluir_anita' => $tieneFlagsOrigen ? $request->boolean('incluir_anita') : true,
            'incluir_erp' => $tieneFlagsOrigen ? $request->boolean('incluir_erp') : true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['fecha_desde'] ?? '') !== ''
            && ($filtros['fecha_hasta'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'cuentacaja_id' => (int) ($filtros['cuentacaja_id'] ?? 0),
            'cbu_origen' => (string) ($filtros['cbu_origen'] ?? ''),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'tipo_op' => (string) ($filtros['tipo_op'] ?? 'OPP'),
            'op_desde' => (int) ($filtros['op_desde'] ?? 0),
            'op_hasta' => (int) ($filtros['op_hasta'] ?? 99999999),
            'tipo_aplicacion' => (string) ($filtros['tipo_aplicacion'] ?? ''),
            'fecha_solicitud' => (string) ($filtros['fecha_solicitud'] ?? ''),
            'secuencia' => (int) ($filtros['secuencia'] ?? 1),
            'incluir_anita' => ! empty($filtros['incluir_anita']) ? 1 : 0,
            'incluir_erp' => ! empty($filtros['incluir_erp']) ? 1 : 0,
        ];
    }

    private static function normalizarFecha(mixed $valor, string $default): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return $default;
        }
        $ts = strtotime($valor);

        return $ts ? date('Y-m-d', $ts) : $default;
    }
}
