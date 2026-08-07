<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionMaquina;
use Carbon\Carbon;

/**
 * Arma el contexto ERP → Informix para rendmaquina / rendvalor / rendmapgasto.
 */
final class RendicionMaquinaAnitaContextBuilder
{
    /**
     * Códigos apertura_gasto → columnas float legacy en cabecera rendmaquina.
     *
     * @var array<int, string>
     */
    public const GASTO_CODIGO_A_COLUMNA = [
        1 => 'reint_ff_ger',
        2 => 'reint_ff_adm',
        3 => 'reint_ff_fin',
        4 => 'ff_maquina',
        5 => 'ff_legales',
        6 => 'perd_de_pers',
        7 => 'reconoc_cli',
        8 => 'desperf_maq',
        9 => 'retiros_adm',
        10 => 'gastos_div',
        11 => 'ref_bingo',
        12 => 'canje_puntos',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function desdeRendicion(RendicionMaquina $rendicion): array
    {
        $rendicion->loadMissing([
            'empresa',
            'valores.cuentacaja',
            'gastos.aperturaGasto',
            'creoUsuario',
            'cajeroUsuario',
            'supervisorUsuario',
            'auxiliarUsuario',
        ]);

        $fecha = $rendicion->fecha
            ? Carbon::parse($rendicion->fecha)->startOfDay()
            : Carbon::today()->startOfDay();
        $ahora = now();

        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        $empresaErpId = (int) $rendicion->empresa_id;
        $empresaAnita = (int) ($rendicion->empresa?->codigo ?? $empresaErpId);

        $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
        $calcVars = is_array($rendicion->calc_json['variables'] ?? null)
            ? $rendicion->calc_json['variables']
            : [];

        $input = static function (string $clave) use ($inputs): float {
            if (array_key_exists($clave, $inputs)) {
                return round((float) $inputs[$clave], 4);
            }
            $ruta = 'inputs.'.$clave;
            if (array_key_exists($ruta, $inputs)) {
                return round((float) $inputs[$ruta], 4);
            }

            return 0.0;
        };

        $calc = static function (string $clave) use ($calcVars): float {
            if (array_key_exists($clave, $calcVars)) {
                return round((float) $calcVars[$clave], 4);
            }
            $ruta = 'calc.'.$clave;
            if (array_key_exists($ruta, $calcVars)) {
                return round((float) $calcVars[$ruta], 4);
            }

            return 0.0;
        };

        $gastosPorCodigo = [];
        foreach ($rendicion->gastos as $linea) {
            $codigo = (int) ($linea->aperturaGasto?->codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $gastosPorCodigo[$codigo] = round(
                ($gastosPorCodigo[$codigo] ?? 0.0) + (float) $linea->monto,
                4
            );
        }

        $gastoCtx = [];
        foreach (self::GASTO_CODIGO_A_COLUMNA as $codigo => $ctxKey) {
            $gastoCtx[$ctxKey] = round((float) ($gastosPorCodigo[$codigo] ?? 0), 4);
        }

        $cajaDefault = (int) (config('rendicion_maquina_anita.caja_id_default_por_empresa')[$empresaErpId] ?? 12);

        $lineasValor = [];
        foreach ($rendicion->valores as $valor) {
            $cuenta = $valor->cuentacaja;
            $codigo = self::codigoRendvalor($empresaErpId, $valor->codigo_valormae, $cuenta);
            if ($codigo === null) {
                continue;
            }
            $monto = round((float) $valor->monto, 4);
            if (! isset($lineasValor[$codigo])) {
                $lineasValor[$codigo] = [
                    'codigo' => $codigo,
                    'total' => 0.0,
                    'cotizacion' => round((float) ($valor->cotizacion ?? 0), 6),
                ];
            }
            $lineasValor[$codigo]['total'] = round($lineasValor[$codigo]['total'] + $monto, 4);
            if ($valor->cotizacion !== null) {
                $lineasValor[$codigo]['cotizacion'] = round((float) $valor->cotizacion, 6);
            }
        }

        $lineasGasto = [];
        $orden = 0;
        foreach ($rendicion->gastos as $linea) {
            $codigo = (int) ($linea->aperturaGasto?->codigo ?? 0);
            $monto = round((float) $linea->monto, 4);
            if ($codigo <= 0 || abs($monto) < 0.00001) {
                continue;
            }
            $lineasGasto[] = [
                'orden' => $orden++,
                'codigo' => $codigo,
                'importe' => $monto,
            ];
        }

        return array_merge([
            'nro_oper' => $nroOper,
            'tipo_oper' => substr((string) config('rendicion_maquina_anita.tipo_oper', 'F'), 0, 1),
            'empresa_id' => $empresaErpId,
            'empresa_anita' => $empresaAnita,
            'caja_id' => $cajaDefault,
            'cajero_id' => (int) ($rendicion->cajero_usuario_id ?? 0),
            'usuario_id' => (int) ($rendicion->creousuario_id ?? 0),
            'supervisor_id' => (int) ($rendicion->supervisor_usuario_id ?? 0),
            'auxiliar_id' => (int) ($rendicion->auxiliar_usuario_id ?? 0),
            'fecha_entera' => (int) $fecha->format('Ymd'),
            'fecha_alfa' => $fecha->format('d/m/y'),
            'hora' => $ahora->format('H:i:s'),
            'fecha_carga' => (int) $ahora->format('Ymd'),
            'hora_carga' => $ahora->format('H:i:s'),
            'turno' => substr((string) $rendicion->turno, 0, 1),
            'estado' => (string) config('rendicion_maquina_anita.estado_pendiente', ' '),
            'observacion' => (string) ($rendicion->observacion ?? ''),
            'sobrantes' => $input('sobrantes'),
            'deposito' => (array_key_exists('calc.deposito', $calcVars) || array_key_exists('deposito', $calcVars))
                ? $calc('deposito')
                : $input('deposito'),
            'venta_ficha' => $input('venta_ficha'),
            'drop_bill_ant' => $input('drop_bill_ant'),
            // Anita rendm_drop_billete = bruto WIGOS; dr_bill_rod = neto
            'drop_billete' => abs($input('drop_billete_bruto')) > 0.00001
                ? $input('drop_billete_bruto')
                : round($input('drop_billete') + $input('impuesto_drop'), 4),
            'pago_manual' => $input('pago_manual'),
            'tito' => $input('tito'),
            'hopper' => $input('hopper'),
            'venta_ruleta' => $input('venta_ruleta'),
            'drop_rul_ant' => $input('drop_rul_ant'),
            'drop_ruleta' => $input('drop_ruleta'),
            'salida_ruleta' => $input('salida_ruleta'),
            'tito_ruleta' => $input('tito_ruleta'),
            'vale_anterior' => $input('vale_anterior'),
            'variacion_ff' => $input('variacion_ff'),
            'pago_diferido' => $input('pago_diferido'),
            'ticket_prom' => $input('ticket_prom'),
            'impuesto_drop' => $input('impuesto_drop'),
            'impuesto_venta' => $input('impuesto_venta'),
            'impuesto_qr' => $input('impuesto_qr'),
            'ajuste_wigosd' => 0.0,
            'billem_rodillo' => $input('billem_rodillo'),
            'billem_ruleta' => $input('billem_ruleta'),
            'dropqr_rodillo' => $input('dropqr_rodillo'),
            'dropqr_ruleta' => $input('dropqr_ruleta'),
            'fondo_inicial' => round((float) $rendicion->fondo_inicial, 4),
            'comprobante' => $calc('comprobante'),
            'fondo_fijo' => $calc('fondo_fijo'),
            'drop_bill_rodillo' => $calc('drop_bill_rodillo'),
            'drop_bill_ruleta' => $calc('drop_bill_ruleta'),
            'entrada_ruleta' => $calc('entrada_ruleta'),
            'venta_ficha_calc' => $calc('venta_ficha'),
            'sobrante_supervisor' => $calc('sobrante_supervisor'),
            'total_ingreso' => round((float) $rendicion->total_ingreso, 4),
            'tito_rodillo' => $calc('tito_rodillo'),
            'tito_ruleta_calc' => $calc('tito_ruleta'),
            'deposito_pesos' => $calc('deposito_pesos'),
            'total_salida' => round((float) $rendicion->total_salida, 4),
            'vale_rep_fondo' => $calc('vale_rep_fondo'),
            'deposito_efectivo' => $calc('deposito_efectivo'),
            'resultado_turno' => round((float) $rendicion->resultado_turno, 4),
            'comprobante_cierre' => $calc('comprobante_cierre'),
            'fondo_cierre' => round((float) $rendicion->fondo_cierre, 4),
            'transferencia' => round((float) $rendicion->transferencia, 4),
            'dif_caja' => round((float) $rendicion->dif_caja, 4),
            'canje_gastro' => round((float) ($gastosPorCodigo[13] ?? 0), 4),
            'lineas_valor' => array_values($lineasValor),
            'lineas_gasto' => $lineasGasto,
        ], $gastoCtx);
    }

    private static function codigoRendvalor(int $empresaId, mixed $codigoValormae, ?Cuentacaja $cuenta): ?int
    {
        $desdeMae = (int) $codigoValormae;
        if ($desdeMae > 0) {
            return $desdeMae;
        }

        if ($cuenta === null) {
            return null;
        }

        $codigo = trim((string) $cuenta->codigo);
        if ($codigo !== '' && ctype_digit($codigo)) {
            return (int) $codigo;
        }

        return null;
    }
}
