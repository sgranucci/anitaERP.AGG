<?php

namespace App\Support\Compras;

use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoLinea;
use App\Models\Compras\ProgramaPagoOp;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;

/**
 * Arma, sin grabar, las órdenes de pago de los proveedores tildados.
 * Una OP por proveedor: junta cheques y TRANSF (medios = neto) y, si hay cheques,
 * recupera el bruto (+ retenciones) para aplicar a la deuda.
 */
final class ProgramaPagoOpPlanSupport
{
    /**
     * @param  list<int>  $lineaIds
     * @return list<array<string, mixed>>
     */
    public static function armar(ProgramaPago $programa, array $lineaIds, ?string $fechaPago = null): array
    {
        $lineaIds = array_values(array_unique(array_filter(array_map('intval', $lineaIds))));
        if ($lineaIds === []) {
            return [];
        }

        $columnas = ProgramaPagoMesesSupport::columnas(
            (string) $programa->anio_mes_inicio,
            (int) $programa->cantidad_meses,
            (bool) $programa->incluye_transf
        );
        $claves = ProgramaPagoMesesSupport::claves($columnas);

        $lineas = ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->whereIn('id', $lineaIds)
            ->with([
                'proveedores:id,codigo,nombre',
                'asignaciones',
                'chequesAsignados.cheque:id,numerocheque,nro_interno_anita,fechapago,monto,banco_id,moneda_id,cotizacion,sucursalpago,cuentalibradora,pagoproveedor_id',
            ])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $opsExistentes = ProgramaPagoOp::query()
            ->whereIn('programa_pago_linea_id', $lineas->pluck('id')->all())
            ->orderByDesc('id')
            ->get()
            ->unique('programa_pago_linea_id')
            ->keyBy('programa_pago_linea_id');

        $fecha = $fechaPago ?: date('Y-m-d');
        $plan = [];
        $consumido = [];
        foreach ($lineas as $linea) {
            $medios = 0.0;
            foreach ($linea->asignaciones as $asig) {
                if (! in_array((string) $asig->clave, $claves, true)) {
                    continue;
                }
                $medios += (float) $asig->monto;
            }
            $medios = round(max(0, $medios), 2);
            $cheques = self::chequesDeColumna($linea->chequesAsignados->all());
            $sumaCheques = round(array_sum(array_column($cheques, 'monto')), 2);
            if ($medios < 0.01 && $sumaCheques < 0.01) {
                continue;
            }

            $retenciones = 0.0;
            $programado = $medios;
            $neto = $medios;
            $diferencia = 0.0;
            if ($medios > 0.009) {
                $est = ProgramaPagoRetencionEstimacionSupport::resolverBrutoNeto(
                    (int) $programa->empresa_id,
                    (int) $linea->proveedor_id,
                    $medios,
                    (float) $linea->saldo_adeudado,
                    $fecha
                );
                $retenciones = (float) $est['retenciones'];
                $programado = (float) $est['bruto'];
                $neto = (float) $est['neto'];
                if ($sumaCheques > 0.009) {
                    // Transferencia = medios de la matriz (neto o bruto) menos cheques.
                    // Si la fila aún está en bruto, el neto a cubrir con medios es $neto.
                    $baseMedios = ! empty($est['ya_neto']) ? $medios : $neto;
                    $diferencia = round(max(0, $baseMedios - $sumaCheques), 2);
                } else {
                    $diferencia = $neto;
                }
            }

            $aplicaciones = $programado > 0.009
                ? self::aplicarFifo(
                    (int) $programa->empresa_id,
                    (int) $linea->proveedor_id,
                    $programado,
                    $fecha,
                    $consumido
                )
                : [];
            $aplicado = round(array_sum(array_column($aplicaciones, 'montoaplicado')), 2);
            $existente = $opsExistentes->get($linea->id);

            $plan[] = [
                'linea_id' => (int) $linea->id,
                'proveedor_id' => (int) $linea->proveedor_id,
                'codigo' => (string) ($linea->proveedores->codigo ?? ''),
                'nombre' => (string) ($linea->proveedores->nombre ?? ''),
                'programado' => $programado,
                'medios' => $medios,
                'neto' => $neto,
                'retenciones' => $retenciones,
                'cheques' => $cheques,
                'suma_cheques' => $sumaCheques,
                'diferencia' => $diferencia,
                'necesita_cuenta' => $diferencia > 0.009,
                'aplicado' => $aplicado,
                'comprobantes' => count($aplicaciones),
                'sin_deuda' => $programado > 0.009 && $aplicado < 0.01,
                'pagoproveedor_id' => $existente ? (int) $existente->pagoproveedor_id : null,
            ];
        }

        return $plan;
    }

    /**
     * Facturas con saldo, de la más vieja a la más nueva, hasta cubrir el monto.
     *
     * @return list<array{proveedor_cuentacorriente_id:int,montoaplicado:float,moneda_id:int,cotizacion:float,cotizacion_aplicada:float}>
     */
    public static function aplicarFifo(
        int $empresaId,
        int $proveedorId,
        float $monto,
        string $fechaHasta,
        array &$consumido = [],
    ): array {
        $monto = round($monto, 2);
        if ($empresaId <= 0 || $proveedorId <= 0 || $monto < 0.01) {
            return [];
        }

        $aplicaciones = DB::table('proveedor_cuentacorriente_aplicacion')
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) AS aplicado')
            ->where('fecha', '<=', $fechaHasta)
            ->groupBy('proveedor_cuentacorriente_id');

        $aplicado = SqlDialectSupport::coalesce('apl.aplicado', '0');
        $saldoExpr = 'CASE WHEN cc.total >= 0 THEN GREATEST(0, cc.total + '.$aplicado.')'
            .' ELSE LEAST(0, cc.total + '.$aplicado.') END';

        $filas = DB::table('proveedor_cuentacorriente as cc')
            ->leftJoinSub($aplicaciones, 'apl', 'apl.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->leftJoin('comprobante_proveedor as cpb', 'cpb.id', '=', 'cc.comprobante_proveedor_id')
            ->where('cc.empresa_id', $empresaId)
            ->where('cc.proveedor_id', $proveedorId)
            ->where('cc.fecha', '<=', $fechaHasta)
            ->whereRaw(SqlDialectSupport::sqlAlcanceDeudaAbiertaProveedorCc('cc'))
            ->whereRaw('('.$saldoExpr.') > 0.009')
            ->where(function ($q) {
                $q->whereNull('cpb.id')->orWhere('cpb.bloqueado_pago', false)->orWhereNull('cpb.bloqueado_pago');
            })
            ->orderBy('cc.fecha')
            ->orderBy('cc.id')
            ->selectRaw('cc.id as id, cc.moneda_id as moneda_id, cc.cotizacion as cotizacion, ('.$saldoExpr.') as saldo')
            ->get();

        $resto = $monto;
        $out = [];
        foreach ($filas as $fila) {
            if ($resto < 0.01) {
                break;
            }
            $saldo = round((float) $fila->saldo - (float) ($consumido[(int) $fila->id] ?? 0), 2);
            if ($saldo < 0.01) {
                continue;
            }
            $toma = round(min($saldo, $resto), 2);
            $consumido[(int) $fila->id] = round((float) ($consumido[(int) $fila->id] ?? 0) + $toma, 2);
            $cot = (float) ($fila->cotizacion ?? 0);
            if ($cot <= 0) {
                $cot = 1.0;
            }
            $out[] = [
                'proveedor_cuentacorriente_id' => (int) $fila->id,
                'montoaplicado' => $toma,
                'moneda_id' => (int) ($fila->moneda_id ?: 1) ?: 1,
                'cotizacion' => $cot,
                'cotizacion_aplicada' => $cot,
            ];
            $resto = round($resto - $toma, 2);
        }

        return $out;
    }

    /**
     * @param  list<\App\Models\Compras\ProgramaPagoCheque>  $filas
     * @return list<array<string, mixed>>
     */
    private static function chequesDeColumna(array $filas): array
    {
        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->cheque_id;
            if ($id <= 0 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $ch = $fila->cheque;
            $out[] = [
                'id' => $id,
                'numerocheque' => (string) ($ch->numerocheque ?? ''),
                'nro_interno_anita' => $ch && $ch->nro_interno_anita !== null ? (int) $ch->nro_interno_anita : null,
                'fechapago' => $ch && $ch->fechapago ? (string) $ch->fechapago : '',
                'monto' => round((float) ($ch->monto ?? $fila->monto_cheque), 2),
                'banco_id' => (int) ($ch->banco_id ?? 0),
                'moneda_id' => (int) ($ch->moneda_id ?? 1) ?: 1,
                'cotizacion' => (float) ($ch->cotizacion ?? 0) > 0 ? (float) $ch->cotizacion : 1.0,
                'sucursalpago' => (string) ($ch->sucursalpago ?? ''),
                'cuentalibradora' => (string) ($ch->cuentalibradora ?? ''),
                'ya_en_op' => $ch && (int) ($ch->pagoproveedor_id ?? 0) > 0,
            ];
        }

        return $out;
    }
}
