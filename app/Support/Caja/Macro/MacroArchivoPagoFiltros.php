<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

use Illuminate\Http\Request;

/**
 * Filtros exportación Macro (espejo p-enviamacro + Interbanking archivo-pago).
 */
final class MacroArchivoPagoFiltros
{
    /**
     * @return array{
     *   empresa_id:int,
     *   cuentacaja_id:int,
     *   cuenta_anita:string,
     *   cuenta_debito:string,
     *   fecha_desde:string,
     *   fecha_hasta:string,
     *   tipo_op:string,
     *   op_desde:int,
     *   op_hasta:int,
     *   tipo_aplicacion:string,
     *   sucursal_banco:int,
     *   usuario_retencion:string,
     *   incluir_cheques:bool,
     *   incluir_transferencias:bool,
     *   incluir_anita:bool,
     *   incluir_erp:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $tipoOp = strtoupper(substr(trim((string) $request->input('tipo_op', '0')), 0, 3));
        if ($tipoOp === '') {
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
        $tieneFlagsMedio = $request->has('incluir_cheques')
            || $request->has('incluir_transferencias')
            || $consultando;

        $sucursalDefault = (int) config('macro.sucursal_default', 651);
        if ($sucursalDefault <= 0) {
            $sucursalDefault = 651;
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'cuentacaja_id' => max(0, (int) $request->input('cuentacaja_id', 0)),
            'cuenta_anita' => self::padCuentaAnita((string) $request->input('cuenta_anita', '')),
            'cuenta_debito' => preg_replace('/\D+/', '', (string) $request->input('cuenta_debito', '')) ?? '',
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_op' => $tipoOp,
            'op_desde' => $opDesde,
            'op_hasta' => $opHasta > 0 ? $opHasta : 99999999,
            'tipo_aplicacion' => strtoupper(substr(trim((string) $request->input('tipo_aplicacion', '')), 0, 3)),
            'sucursal_banco' => max(0, (int) $request->input('sucursal_banco', $sucursalDefault)),
            'usuario_retencion' => substr(trim((string) $request->input('usuario_retencion', '')), 0, 10),
            'incluir_cheques' => $tieneFlagsMedio ? $request->boolean('incluir_cheques') : true,
            'incluir_transferencias' => $tieneFlagsMedio ? $request->boolean('incluir_transferencias') : true,
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
            'cuenta_anita' => (string) ($filtros['cuenta_anita'] ?? ''),
            'cuenta_debito' => (string) ($filtros['cuenta_debito'] ?? ''),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'tipo_op' => (string) ($filtros['tipo_op'] ?? '0'),
            'op_desde' => (int) ($filtros['op_desde'] ?? 0),
            'op_hasta' => (int) ($filtros['op_hasta'] ?? 99999999),
            'tipo_aplicacion' => (string) ($filtros['tipo_aplicacion'] ?? ''),
            'sucursal_banco' => (int) ($filtros['sucursal_banco'] ?? config('macro.sucursal_default', 651)),
            'usuario_retencion' => (string) ($filtros['usuario_retencion'] ?? ''),
            'incluir_cheques' => ! empty($filtros['incluir_cheques']) ? 1 : 0,
            'incluir_transferencias' => ! empty($filtros['incluir_transferencias']) ? 1 : 0,
            'incluir_anita' => ! empty($filtros['incluir_anita']) ? 1 : 0,
            'incluir_erp' => ! empty($filtros['incluir_erp']) ? 1 : 0,
        ];
    }

    public static function padCuentaAnita(string $codigo): string
    {
        $c = preg_replace('/\D+/', '', $codigo) ?? '';
        if ($c === '') {
            return '';
        }

        return str_pad(substr($c, -8), 8, '0', STR_PAD_LEFT);
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
