<?php

declare(strict_types=1);

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ProyeccionPagosMedioPagoSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proyección de pagos totalizada en la posición bancaria (hoja Macro).
 *
 * Transferencias con vencimiento en el día: una fila TRF PROYECCION.
 * Cheques: una fila CHEQUES PROYECCION en cada fecha de diferimiento
 * (vencimiento de la cuota, que sale de la condición de pago).
 * Lo ya aplicado no entra: al emitirse el pago, el cheque pasa a la cartera de diferidos.
 */
final class PosicionBancariaProyeccionPagosSupport
{
    private const HOJA = 'Macro';

    private const DIAS_CHEQUE = 120;

    /**
     * @return array<string, array<string, list<array{
     *   detalle: string, B: ?float, C: ?float, D: ?float, estado: string, tono: string, rubro: string
     * }>>>
     */
    public function lineasPorFechaYHoja(Carbon $desde, Carbon $hasta): array
    {
        $desdeDia = $desde->copy()->startOfDay();
        $hastaTrf = $hasta->copy()->startOfDay();
        $hastaCheque = $desdeDia->copy()->addDays(self::DIAS_CHEQUE);
        if ($hastaTrf->gt($hastaCheque)) {
            $hastaCheque = $hastaTrf->copy();
        }
        $totales = [];

        foreach ($this->deudaAbierta($hastaCheque) as $row) {
            $clasif = $this->clasificar($row, $desdeDia, $hastaTrf, $hastaCheque);
            if ($clasif === null) {
                continue;
            }
            $col = FinanzaPosicionHojaSupport::COLUMNA_POR_EMPRESA[(int) $row->empresa_id] ?? null;
            if ($col === null) {
                continue;
            }
            $clave = $clasif['fecha'].'|'.$clasif['detalle'];
            $totales[$clave]['fecha'] = $clasif['fecha'];
            $totales[$clave]['detalle'] = $clasif['detalle'];
            $totales[$clave][$col] = round(($totales[$clave][$col] ?? 0) + $clasif['importe'], 2);
        }

        $out = [];
        foreach ($totales as $total) {
            $fila = [
                'detalle' => $total['detalle'],
                'B' => isset($total['B']) ? round((float) $total['B'], 2) : null,
                'C' => isset($total['C']) ? round((float) $total['C'], 2) : null,
                'D' => isset($total['D']) ? round((float) $total['D'], 2) : null,
                'estado' => 'Proyección',
                'tono' => 'proyeccion',
                'rubro' => '',
            ];
            if ($fila['B'] === null && $fila['C'] === null && $fila['D'] === null) {
                continue;
            }
            $out[$total['fecha']][self::HOJA][] = $fila;
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    private function deudaAbierta(Carbon $hastaCheque): array
    {
        $aplicaciones = DB::table('proveedor_cuentacorriente_aplicacion')
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) AS aplicado')
            ->groupBy('proveedor_cuentacorriente_id');

        return DB::table('proveedor_cuentacorriente as cc')
            ->join('proveedor as p', 'p.id', '=', 'cc.proveedor_id')
            ->join('comprobante_proveedor as comp', 'comp.id', '=', 'cc.comprobante_proveedor_id')
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'comp.tipotransaccion_compra_id')
            ->leftJoin('comprobante_proveedor_cuota as cuo', 'cuo.id', '=', 'cc.comprobante_proveedor_cuota_id')
            ->leftJoin('formapago as fp', 'fp.id', '=', 'cuo.formapago_id')
            ->leftJoin('ordencompra_comprobante_cuota as occ', 'occ.id', '=', 'cuo.ordencompra_comprobante_cuota_id')
            ->leftJoin('formapago as fpoc', 'fpoc.id', '=', 'occ.formapago_id')
            ->leftJoin('ordencompra_comprobante as ocp', 'ocp.id', '=', 'occ.ordencompra_comprobante_id')
            ->leftJoin('ordencompra as oc', function ($join) {
                $join->on('oc.id', '=', DB::raw(SqlDialectSupport::coalesce('ocp.ordencompra_id', 'comp.ordencompra_id')));
            })
            ->leftJoinSub(
                DB::table('ordencompra_comprobante as ocp_med')
                    ->join('ordencompra_comprobante_cuota as occ_med', 'occ_med.ordencompra_comprobante_id', '=', 'ocp_med.id')
                    ->selectRaw('ocp_med.ordencompra_id, MIN(occ_med.id) AS occ_id')
                    ->groupBy('ocp_med.ordencompra_id'),
                'ocmed',
                'ocmed.ordencompra_id',
                '=',
                'oc.id',
            )
            ->leftJoin('ordencompra_comprobante_cuota as occdoc', 'occdoc.id', '=', 'ocmed.occ_id')
            ->leftJoin('formapago as fpdoc', 'fpdoc.id', '=', 'occdoc.formapago_id')
            ->leftJoinSub($aplicaciones, 'apl', 'apl.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->whereNull('cc.pagoproveedor_id')
            ->whereIn('cc.empresa_id', [1, 2, 3])
            ->where('cc.fecha', '<=', $hastaCheque->toDateString())
            ->where(function ($q) {
                $q->where('comp.bloqueado_pago', false)->orWhereNull('comp.bloqueado_pago');
            })
            ->where('comp.estado', '!=', ComprobanteProveedorEstados::ANULADO)
            ->whereRaw('ABS('.SqlDialectSupport::coalesce('apl.aplicado', '0').') < ABS(cc.total)')
            ->get([
                'cc.empresa_id',
                'cc.moneda_id',
                'cc.cotizacion',
                'cc.fecha',
                'cc.fechavencimiento',
                'cc.total',
                DB::raw(SqlDialectSupport::coalesce('apl.aplicado', '0').' as aplicado'),
                'tt.signo as tipo_signo',
                'comp.origen_entrada',
                'fp.abreviatura as formapago_abreviatura',
                'fp.nombre as formapago_nombre',
                'fpoc.abreviatura as formapago_oc_abreviatura',
                'fpoc.nombre as formapago_oc_nombre',
                'fpdoc.abreviatura as formapago_oc_doc_abreviatura',
                'fpdoc.nombre as formapago_oc_doc_nombre',
            ])
            ->all();
    }

    /**
     * @return array{fecha: string, detalle: string, importe: float}|null
     */
    private function clasificar(object $row, Carbon $desde, Carbon $hastaTrf, Carbon $hastaCheque): ?array
    {
        $medio = ProyeccionPagosMedioPagoSupport::resolver(
            $row->formapago_abreviatura ?? null,
            $row->formapago_nombre ?? null,
            $row->formapago_oc_abreviatura ?? null,
            $row->formapago_oc_nombre ?? null,
            $row->formapago_oc_doc_abreviatura ?? null,
            $row->formapago_oc_doc_nombre ?? null,
            (int) ($row->tipo_signo ?? 0),
            isset($row->origen_entrada) ? (string) $row->origen_entrada : null,
        );
        $tipo = $this->tipoMedio($medio);
        if ($tipo === null) {
            return null;
        }

        $saldo = round((float) $row->total + (float) $row->aplicado, 2);
        if (abs($saldo) < 0.01) {
            return null;
        }
        $importe = $this->aPesos($saldo, (int) $row->moneda_id, (float) ($row->cotizacion ?? 0), (string) ($row->fechavencimiento ?: $row->fecha));
        if ($importe === null) {
            return null;
        }
        // Sale de la cuenta: la deuda a pagar resta.
        $importe = round(-1 * $importe, 2);

        $vencimiento = $this->fecha($row->fechavencimiento);
        if ($tipo === 'trf') {
            $fecha = ($vencimiento === null || $vencimiento < $desde->toDateString())
                ? $desde->toDateString()
                : $vencimiento;
            if ($fecha > $hastaTrf->toDateString()) {
                return null;
            }

            return ['fecha' => $fecha, 'detalle' => 'TRF PROYECCION', 'importe' => $importe];
        }

        $fecha = $vencimiento ?? $this->fecha($row->fecha);
        if ($fecha === null) {
            return null;
        }
        if ($fecha < $desde->toDateString()) {
            $fecha = $desde->toDateString();
        }
        if ($fecha > $hastaCheque->toDateString()) {
            return null;
        }

        return ['fecha' => $fecha, 'detalle' => 'CHEQUES PROYECCION', 'importe' => $importe];
    }

    private function tipoMedio(string $medio): ?string
    {
        $u = mb_strtoupper(trim($medio));
        if ($u === '' || $u === 'EFECT.' || str_contains($u, 'EFECT')) {
            return null;
        }
        if ($u === 'TRANSF' || str_contains($u, 'TRANSF') || $u === 'T') {
            return 'trf';
        }
        if ($u === 'CHEQUE' || str_contains($u, 'CHEQUE') || $u === 'C' || $u === 'CH' || $u === 'V') {
            return 'cheque';
        }

        return null;
    }

    private function aPesos(float $importe, int $monedaId, float $cotizacionDocumento, string $fecha): ?float
    {
        if ($monedaId <= 1) {
            return $importe;
        }
        $cot = $cotizacionDocumento > CotizacionVigenteSupport::COTIZACION_MINIMA_EXTRANJERA
            ? $cotizacionDocumento
            : CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($cot <= 0) {
            return null;
        }

        return round($importe * $cot, 2);
    }

    private function fecha(mixed $valor): ?string
    {
        $texto = substr(trim((string) $valor), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) || str_starts_with($texto, '0000')) {
            return null;
        }

        return $texto;
    }
}
