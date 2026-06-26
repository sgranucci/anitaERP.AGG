<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionModoSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Support\Facades\DB;

final class IvaVentasConciliacionContableService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoIva
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $resultadoIva): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return $this->vacio();
        }

        $cuentas = IvaVentasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);
        $totalesErp = $resultadoIva['totales_general'] ?? IvaVentasColumnasSupport::montosVacios();
        $totalesPorPv = $resultadoIva['totales_por_puntoventa'] ?? [];
        $filas = $resultadoIva['filas'] ?? [];

        $ventaIds = array_values(array_unique(array_filter(array_map(
            static fn (array $f) => (int) ($f['venta_id'] ?? 0),
            $filas,
        ))));

        $statsAsiento = $this->statsAsientoPorVenta($ventaIds);
        $contableEmpresa = $this->totalesContablesEmpresa($empresaId, $filtros, $cuentas);
        $contableVinculadoPv = $this->totalesContablesVinculadosPorPv($empresaId, $cuentas, $ventaIds, $filtros);

        $porPuntoventa = $this->armarFilasPorPuntoventa(
            $totalesPorPv,
            $contableVinculadoPv,
            $statsAsiento,
            $filas,
        );

        $resumenEmpresa = $this->armarResumenEmpresa($totalesErp, $contableEmpresa, $statsAsiento, count($filas));
        $porFactura = $this->conciliarPorFacturaVinculada($empresaId, $filtros, $filas, $cuentas, $statsAsiento);
        $auditoriaDiaria = $this->auditoriaDiaria($empresaId, $filtros, $filas, $cuentas);

        return [
            'habilitada' => true,
            'cuentas' => $cuentas,
            'resumen_empresa' => $resumenEmpresa,
            'por_puntoventa' => $porPuntoventa,
            'por_factura_vinculada' => $porFactura,
            'auditoria_diaria' => $auditoriaDiaria,
            'notas' => $this->notasConciliacion($statsAsiento, count($filas), $porFactura),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vacio(): array
    {
        return [
            'habilitada' => false,
            'cuentas' => [],
            'resumen_empresa' => [],
            'por_puntoventa' => [],
            'por_factura_vinculada' => ['habilitada' => false, 'facturas' => [], 'stats' => []],
            'auditoria_diaria' => ['habilitada' => false, 'dias' => [], 'stats' => []],
            'notas' => [],
        ];
    }

    /**
     * Cuadre día por día entre IVA ventas (ERP) y mayor contable.
     * Tolerancia diaria más amplia que el cuadre global (redondeos y cierres parciales).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cuentas
     * @return array<string, mixed>
     */
    private function auditoriaDiaria(int $empresaId, array $filtros, array $filas, array $cuentas): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return ['habilitada' => false, 'dias' => [], 'stats' => []];
        }

        $erpPorDia = [];
        foreach ($filas as $fila) {
            $dia = (string) ($fila['fecha_orden'] ?? '');
            if ($dia === '') {
                continue;
            }

            if (! isset($erpPorDia[$dia])) {
                $erpPorDia[$dia] = [
                    'comprobantes' => 0,
                    'neto_gravado' => 0.0,
                    'imp_interno' => 0.0,
                    'iva' => 0.0,
                    'total' => 0.0,
                ];
            }

            $col = $fila['columnas'] ?? [];
            $erpPorDia[$dia]['comprobantes']++;
            $erpPorDia[$dia]['neto_gravado'] = round($erpPorDia[$dia]['neto_gravado'] + (float) ($col['neto_gravado'] ?? 0), 2);
            $erpPorDia[$dia]['imp_interno'] = round($erpPorDia[$dia]['imp_interno'] + (float) ($col['imp_interno'] ?? 0), 2);
            $erpPorDia[$dia]['iva'] = round($erpPorDia[$dia]['iva'] + (float) ($col['iva'] ?? 0), 2);
            $erpPorDia[$dia]['total'] = round($erpPorDia[$dia]['total'] + (float) ($col['total'] ?? 0), 2);
        }

        $contablePorDia = $this->totalesContablesPorDia($empresaId, $filtros, $cuentas);

        $dias = [];
        $stats = [
            'total_dias' => 0,
            'dias_con_movimiento' => 0,
            'dias_cuadran' => 0,
            'dias_con_diferencia' => 0,
        ];

        $cursor = strtotime($desde);
        $fin = strtotime($hasta);
        while ($cursor !== false && $cursor <= $fin) {
            $dia = date('Y-m-d', $cursor);
            $erp = $erpPorDia[$dia] ?? [
                'comprobantes' => 0,
                'neto_gravado' => 0.0,
                'imp_interno' => 0.0,
                'iva' => 0.0,
                'total' => 0.0,
            ];
            $ctb = $contablePorDia[$dia] ?? [
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
                'ventas_total' => 0.0,
            ];

            $difNeto = round((float) $erp['neto_gravado'] - (float) ($ctb['ventas_gravadas'] ?? 0), 2);
            $difImp = round((float) $erp['imp_interno'] - (float) ($ctb['ventas_kiosco'] ?? 0), 2);
            $difIva = round((float) $erp['iva'] - (float) ($ctb['iva'] ?? 0), 2);

            $cuadra = IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['neto_gravado'], (float) ($ctb['ventas_gravadas'] ?? 0), IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA)
                && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['imp_interno'], (float) ($ctb['ventas_kiosco'] ?? 0), IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA)
                && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['iva'], (float) ($ctb['iva'] ?? 0), IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA);

            $tieneMovimiento = (int) $erp['comprobantes'] > 0
                || abs((float) ($ctb['ventas_total'] ?? 0)) > IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA
                || abs((float) ($ctb['iva'] ?? 0)) > IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA;

            $stats['total_dias']++;
            if ($tieneMovimiento) {
                $stats['dias_con_movimiento']++;
            }
            if ($cuadra) {
                $stats['dias_cuadran']++;
            } elseif ($tieneMovimiento) {
                $stats['dias_con_diferencia']++;
            }

            $dias[] = [
                'dia' => $dia,
                'dia_texto' => date('d/m/Y', strtotime($dia)),
                'comprobantes' => (int) $erp['comprobantes'],
                'erp' => [
                    'neto_gravado' => (float) $erp['neto_gravado'],
                    'imp_interno' => (float) $erp['imp_interno'],
                    'iva' => (float) $erp['iva'],
                    'total' => (float) $erp['total'],
                ],
                'contable' => [
                    'ventas_gravadas' => (float) ($ctb['ventas_gravadas'] ?? 0),
                    'ventas_kiosco' => (float) ($ctb['ventas_kiosco'] ?? 0),
                    'iva' => (float) ($ctb['iva'] ?? 0),
                    'ventas_total' => (float) ($ctb['ventas_total'] ?? 0),
                ],
                'diferencias' => [
                    'neto_gravado' => $difNeto,
                    'imp_interno' => $difImp,
                    'iva' => $difIva,
                ],
                'cuadra' => $cuadra,
                'tiene_movimiento' => $tieneMovimiento,
            ];

            $cursor = strtotime('+1 day', $cursor);
        }

        return [
            'habilitada' => true,
            'tolerancia' => IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA,
            'dias' => $dias,
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $cuentas
     * @return array<string, array<string, float>>
     */
    private function totalesContablesPorDia(int $empresaId, array $filtros, array $cuentas): array
    {
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
        $ordenFecha = (string) ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA);

        $idsVentas = array_merge(
            $cuentas['ventas_gravadas'] ?? [],
            $cuentas['ventas_kiosco'] ?? [],
        );
        $idsIva = array_merge(
            $cuentas['iva_debito'] ?? [],
            $cuentas['percepcion_iva'] ?? [],
        );
        $idsTodos = array_values(array_unique(array_merge($idsVentas, $idsIva)));

        if ($idsTodos === []) {
            return [];
        }

        $diaExpr = $ordenFecha === IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA
            ? 'CASE '
                .'WHEN a.venta_id IS NOT NULL THEN DATE(v.fechajornada) '
                .'WHEN a.observacion LIKE "%jornada%" THEN STR_TO_DATE(SUBSTRING(a.observacion, LOCATE("jornada", a.observacion) + 8, 10), "%Y-%m-%d") '
                .'ELSE DATE(a.fecha) '
                .'END'
            : 'DATE(a.fecha)';

        $query = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('venta as v', function ($join) {
                $join->on('v.id', '=', 'a.venta_id')->whereNull('v.deleted_at');
            })
            ->where('a.empresa_id', $empresaId)
            ->whereIn('cc.id', $idsTodos);

        if ($ordenFecha === IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) {
            $query->where(function ($q) use ($fechaDesde, $fechaHasta) {
                $q->where(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNull('a.venta_id')
                        ->where('a.observacion', 'like', '%jornada%')
                        ->whereRaw(
                            'SUBSTRING(a.observacion, LOCATE("jornada", a.observacion) + 8, 10) BETWEEN ? AND ?',
                            [$fechaDesde, $fechaHasta],
                        );
                })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNotNull('a.venta_id')
                        ->whereDate('v.fechajornada', '>=', $fechaDesde)
                        ->whereDate('v.fechajornada', '<=', $fechaHasta);
                })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNull('a.venta_id')
                        ->where('a.observacion', 'not like', '%jornada%')
                        ->whereDate('a.fecha', '>=', $fechaDesde)
                        ->whereDate('a.fecha', '<=', $fechaHasta);
                });
            });
        } else {
            $query->whereDate('a.fecha', '>=', $fechaDesde)
                ->whereDate('a.fecha', '<=', $fechaHasta);
        }

        $rows = $query
            ->selectRaw($diaExpr.' as dia, cc.id as cuenta_id, SUM(-am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
            ->groupByRaw($diaExpr.', cc.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $dia = (string) ($row->dia ?? '');
            if ($dia === '') {
                continue;
            }

            if (! isset($out[$dia])) {
                $out[$dia] = [
                    'ventas_gravadas' => 0.0,
                    'ventas_kiosco' => 0.0,
                    'iva' => 0.0,
                    'ventas_total' => 0.0,
                ];
            }

            $importe = round((float) ($row->importe ?? 0), 2);
            $cuentaId = (int) ($row->cuenta_id ?? 0);

            if (in_array($cuentaId, $cuentas['iva_debito'] ?? [], true) || in_array($cuentaId, $cuentas['percepcion_iva'] ?? [], true)) {
                $out[$dia]['iva'] = round($out[$dia]['iva'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['ventas_kiosco'] ?? [], true)) {
                $out[$dia]['ventas_kiosco'] = round($out[$dia]['ventas_kiosco'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['ventas_gravadas'] ?? [], true)) {
                $out[$dia]['ventas_gravadas'] = round($out[$dia]['ventas_gravadas'] + $importe, 2);
            }

            $out[$dia]['ventas_total'] = round($out[$dia]['ventas_gravadas'] + $out[$dia]['ventas_kiosco'], 2);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cuentas
     * @return array<string, float>
     */
    private function totalesContablesEmpresa(int $empresaId, array $filtros, array $cuentas): array
    {
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
        $ordenFecha = (string) ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA);

        $idsVentas = array_merge(
            $cuentas['ventas_gravadas'] ?? [],
            $cuentas['ventas_kiosco'] ?? [],
        );
        $idsIva = array_merge(
            $cuentas['iva_debito'] ?? [],
            $cuentas['percepcion_iva'] ?? [],
        );
        $idsTodos = array_values(array_unique(array_merge($idsVentas, $idsIva)));

        if ($idsTodos === []) {
            return [
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
                'ventas_total' => 0.0,
            ];
        }

        $query = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('cc.id', $idsTodos);

        if ($ordenFecha === IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) {
            $query->where(function ($q) use ($fechaDesde, $fechaHasta) {
                $q->where(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNull('a.venta_id')
                        ->where('a.observacion', 'like', '%jornada%')
                        ->whereRaw(
                            'SUBSTRING(a.observacion, LOCATE("jornada", a.observacion) + 8, 10) BETWEEN ? AND ?',
                            [$fechaDesde, $fechaHasta],
                        );
                })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNotNull('a.venta_id')
                        ->whereExists(function ($sub) use ($fechaDesde, $fechaHasta) {
                            $sub->select(DB::raw(1))
                                ->from('venta as v')
                                ->whereColumn('v.id', 'a.venta_id')
                                ->whereNull('v.deleted_at')
                                ->whereDate('v.fechajornada', '>=', $fechaDesde)
                                ->whereDate('v.fechajornada', '<=', $fechaHasta);
                        });
                })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                    $q2->whereNull('a.venta_id')
                        ->where('a.observacion', 'not like', '%jornada%')
                        ->whereDate('a.fecha', '>=', $fechaDesde)
                        ->whereDate('a.fecha', '<=', $fechaHasta);
                });
            });
        } else {
            $query->whereDate('a.fecha', '>=', $fechaDesde)
                ->whereDate('a.fecha', '<=', $fechaHasta);
        }

        $rows = $query
            ->selectRaw('cc.id as cuenta_id, SUM(-am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
            ->groupBy('cc.id')
            ->get();

        $ventasGravadas = 0.0;
        $ventasKiosco = 0.0;
        $iva = 0.0;

        foreach ($rows as $row) {
            $importe = round((float) ($row->importe ?? 0), 2);
            $cuentaId = (int) ($row->cuenta_id ?? 0);

            if (in_array($cuentaId, $cuentas['iva_debito'] ?? [], true) || in_array($cuentaId, $cuentas['percepcion_iva'] ?? [], true)) {
                $iva = round($iva + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['ventas_kiosco'] ?? [], true)) {
                $ventasKiosco = round($ventasKiosco + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['ventas_gravadas'] ?? [], true)) {
                $ventasGravadas = round($ventasGravadas + $importe, 2);
            }
        }

        return [
            'ventas_gravadas' => $ventasGravadas,
            'ventas_kiosco' => $ventasKiosco,
            'iva' => $iva,
            'ventas_total' => round($ventasGravadas + $ventasKiosco, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $cuentas
     * @param  list<int>  $ventaIds
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, float>>
     */
    private function totalesContablesVinculadosPorPv(int $empresaId, array $cuentas, array $ventaIds, array $filtros): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $idsVentas = array_merge(
            $cuentas['ventas_gravadas'] ?? [],
            $cuentas['ventas_kiosco'] ?? [],
        );
        $idsIva = array_merge(
            $cuentas['iva_debito'] ?? [],
            $cuentas['percepcion_iva'] ?? [],
        );
        $idsTodos = array_values(array_unique(array_merge($idsVentas, $idsIva)));

        if ($idsTodos === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ventaIds, 2000) as $chunk) {
            $rows = DB::table('asiento as a')
                ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->join('venta as v', 'v.id', '=', 'a.venta_id')
                ->where('a.empresa_id', $empresaId)
                ->whereIn('a.venta_id', $chunk)
                ->whereIn('cc.id', $idsTodos)
                ->whereNull('v.deleted_at')
                ->selectRaw('v.puntoventa_id, cc.id as cuenta_id, SUM(-am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
                ->groupBy('v.puntoventa_id', 'cc.id')
                ->get();

            foreach ($rows as $row) {
                $pvId = (int) ($row->puntoventa_id ?? 0);
                if ($pvId <= 0) {
                    continue;
                }

                if (! isset($out[$pvId])) {
                    $out[$pvId] = [
                        'ventas_gravadas' => 0.0,
                        'ventas_kiosco' => 0.0,
                        'iva' => 0.0,
                        'ventas_total' => 0.0,
                    ];
                }

                $importe = round((float) ($row->importe ?? 0), 2);
                $cuentaId = (int) ($row->cuenta_id ?? 0);

                if (in_array($cuentaId, $cuentas['iva_debito'] ?? [], true) || in_array($cuentaId, $cuentas['percepcion_iva'] ?? [], true)) {
                    $out[$pvId]['iva'] = round($out[$pvId]['iva'] + $importe, 2);
                } elseif (in_array($cuentaId, $cuentas['ventas_kiosco'] ?? [], true)) {
                    $out[$pvId]['ventas_kiosco'] = round($out[$pvId]['ventas_kiosco'] + $importe, 2);
                } elseif (in_array($cuentaId, $cuentas['ventas_gravadas'] ?? [], true)) {
                    $out[$pvId]['ventas_gravadas'] = round($out[$pvId]['ventas_gravadas'] + $importe, 2);
                }

                $out[$pvId]['ventas_total'] = round($out[$pvId]['ventas_gravadas'] + $out[$pvId]['ventas_kiosco'], 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array{con_asiento: int, sin_asiento: int, ventas_con_asiento: list<int>}
     */
    private function statsAsientoPorVenta(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return ['con_asiento' => 0, 'sin_asiento' => 0, 'ventas_con_asiento' => []];
        }

        $conAsiento = [];
        foreach (array_chunk($ventaIds, 2000) as $chunk) {
            $ids = DB::table('asiento')
                ->whereIn('venta_id', $chunk)
                ->pluck('venta_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            foreach ($ids as $id) {
                $conAsiento[$id] = true;
            }
        }

        $totalCon = count($conAsiento);
        $totalSin = max(0, count($ventaIds) - $totalCon);

        return [
            'con_asiento' => $totalCon,
            'sin_asiento' => $totalSin,
            'ventas_con_asiento' => array_keys($conAsiento),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $totalesPorPv
     * @param  array<int, array<string, float>>  $contableVinculadoPv
     * @param  array<string, mixed>  $statsAsiento
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function armarFilasPorPuntoventa(array $totalesPorPv, array $contableVinculadoPv, array $statsAsiento, array $filas): array
    {
        $statsPv = [];
        $ventasConAsiento = array_flip($statsAsiento['ventas_con_asiento'] ?? []);

        foreach ($filas as $fila) {
            $pvId = (int) ($fila['puntoventa_id'] ?? 0);
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            if ($pvId <= 0) {
                continue;
            }

            if (! isset($statsPv[$pvId])) {
                $statsPv[$pvId] = ['con_asiento' => 0, 'sin_asiento' => 0, 'cantidad' => 0];
            }

            $statsPv[$pvId]['cantidad']++;
            if (isset($ventasConAsiento[$ventaId])) {
                $statsPv[$pvId]['con_asiento']++;
            } else {
                $statsPv[$pvId]['sin_asiento']++;
            }
        }

        $salida = [];

        foreach ($totalesPorPv as $totPv) {
            $pvId = (int) ($totPv['puntoventa_id'] ?? 0);
            $erp = $totPv['columnas'] ?? IvaVentasColumnasSupport::montosVacios();
            $ctb = $contableVinculadoPv[$pvId] ?? [
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
                'ventas_total' => 0.0,
            ];
            $st = $statsPv[$pvId] ?? ['con_asiento' => 0, 'sin_asiento' => 0, 'cantidad' => 0];

            $salida[] = [
                'seccion' => $totPv['seccion'] ?? '',
                'seccion_label' => $totPv['seccion_label'] ?? '',
                'puntoventa_id' => $pvId,
                'puntoventa_codigo' => $totPv['puntoventa_codigo'] ?? '',
                'puntoventa_nombre' => $totPv['puntoventa_nombre'] ?? '',
                'erp' => [
                    'neto_gravado' => (float) ($erp['neto_gravado'] ?? 0),
                    'imp_interno' => (float) ($erp['imp_interno'] ?? 0),
                    'iva' => (float) ($erp['iva'] ?? 0),
                    'total' => (float) ($erp['total'] ?? 0),
                ],
                'contable_vinculado' => $ctb,
                'diferencias' => [
                    'neto_gravado' => round((float) ($erp['neto_gravado'] ?? 0) - (float) ($ctb['ventas_gravadas'] ?? 0), 2),
                    'imp_interno' => round((float) ($erp['imp_interno'] ?? 0) - (float) ($ctb['ventas_kiosco'] ?? 0), 2),
                    'iva' => round((float) ($erp['iva'] ?? 0) - (float) ($ctb['iva'] ?? 0), 2),
                ],
                'cuadra_vinculado' => IvaVentasConciliacionCuentaSupport::cuadra((float) ($erp['neto_gravado'] ?? 0), (float) ($ctb['ventas_gravadas'] ?? 0))
                    && IvaVentasConciliacionCuentaSupport::cuadra((float) ($erp['iva'] ?? 0), (float) ($ctb['iva'] ?? 0)),
                'stats' => $st,
                'modo_contable' => ($st['con_asiento'] ?? 0) > 0 ? 'vinculado' : 'cierre_agrupado',
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, float>  $totalesErp
     * @param  array<string, float>  $contableEmpresa
     * @param  array<string, mixed>  $statsAsiento
     * @return array<string, mixed>
     */
    private function armarResumenEmpresa(array $totalesErp, array $contableEmpresa, array $statsAsiento, int $cantidadComprobantes): array
    {
        $lineas = [
            [
                'concepto' => 'Neto gravado',
                'erp' => (float) ($totalesErp['neto_gravado'] ?? 0),
                'contable' => (float) ($contableEmpresa['ventas_gravadas'] ?? 0),
            ],
            [
                'concepto' => 'Imp. interno / kiosco',
                'erp' => (float) ($totalesErp['imp_interno'] ?? 0),
                'contable' => (float) ($contableEmpresa['ventas_kiosco'] ?? 0),
            ],
            [
                'concepto' => 'IVA débito fiscal',
                'erp' => (float) ($totalesErp['iva'] ?? 0),
                'contable' => (float) ($contableEmpresa['iva'] ?? 0),
            ],
        ];

        foreach ($lineas as &$linea) {
            $linea['diferencia'] = round($linea['erp'] - $linea['contable'], 2);
            $linea['cuadra'] = IvaVentasConciliacionCuentaSupport::cuadra($linea['erp'], $linea['contable']);
        }
        unset($linea);

        return [
            'lineas' => $lineas,
            'erp_total' => (float) ($totalesErp['total'] ?? 0),
            'contable_ventas_total' => (float) ($contableEmpresa['ventas_total'] ?? 0),
            'contable_iva' => (float) ($contableEmpresa['iva'] ?? 0),
            'comprobantes' => $cantidadComprobantes,
            'con_asiento' => (int) ($statsAsiento['con_asiento'] ?? 0),
            'sin_asiento' => (int) ($statsAsiento['sin_asiento'] ?? 0),
            'cuadra_global' => collect($lineas)->every(static fn (array $l) => ! empty($l['cuadra'])),
        ];
    }

    /**
     * Conciliación comprobante a comprobante (solo ventas con asiento vinculado).
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cuentas
     * @param  array<string, mixed>  $statsAsiento
     * @return array<string, mixed>
     */
    private function conciliarPorFacturaVinculada(int $empresaId, array $filtros, array $filas, array $cuentas, array $statsAsiento): array
    {
        $ventasConAsiento = array_flip($statsAsiento['ventas_con_asiento'] ?? []);
        $idsVentasGrav = $cuentas['ventas_gravadas'] ?? [];
        $idsKiosco = $cuentas['ventas_kiosco'] ?? [];
        $idsIva = array_merge($cuentas['iva_debito'] ?? [], $cuentas['percepcion_iva'] ?? []);
        $idsTodos = array_values(array_unique(array_merge($idsVentasGrav, $idsKiosco, $idsIva)));

        if ($idsTodos === []) {
            return [
                'habilitada' => false,
                'facturas' => [],
                'stats' => [
                    'vinculadas' => 0,
                    'cuadran' => 0,
                    'con_diferencia' => 0,
                    'sin_asiento' => count($filas),
                    'cierre_agrupado' => 0,
                ],
            ];
        }

        $ventaIdsVinculadas = [];
        foreach ($filas as $fila) {
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            if ($ventaId > 0 && isset($ventasConAsiento[$ventaId])) {
                $ventaIdsVinculadas[] = $ventaId;
            }
        }
        $ventaIdsVinculadas = array_values(array_unique($ventaIdsVinculadas));

        $asientoPorVenta = [];
        $contablePorVenta = [];
        foreach (array_chunk($ventaIdsVinculadas, 2000) as $chunk) {
            $asientos = DB::table('asiento')
                ->where('empresa_id', $empresaId)
                ->whereIn('venta_id', $chunk)
                ->select('id', 'venta_id')
                ->get();
            foreach ($asientos as $a) {
                $asientoPorVenta[(int) $a->venta_id] = (int) $a->id;
            }

            $rows = DB::table('asiento as a')
                ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->where('a.empresa_id', $empresaId)
                ->whereIn('a.venta_id', $chunk)
                ->whereIn('cc.id', $idsTodos)
                ->selectRaw('a.venta_id, cc.id as cuenta_id, SUM(-am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
                ->groupBy('a.venta_id', 'cc.id')
                ->get();

            foreach ($rows as $row) {
                $ventaId = (int) ($row->venta_id ?? 0);
                if ($ventaId <= 0) {
                    continue;
                }
                if (! isset($contablePorVenta[$ventaId])) {
                    $contablePorVenta[$ventaId] = [
                        'ventas_gravadas' => 0.0,
                        'ventas_kiosco' => 0.0,
                        'iva' => 0.0,
                    ];
                }
                $importe = round((float) ($row->importe ?? 0), 2);
                $cuentaId = (int) ($row->cuenta_id ?? 0);
                if (in_array($cuentaId, $cuentas['iva_debito'] ?? [], true) || in_array($cuentaId, $cuentas['percepcion_iva'] ?? [], true)) {
                    $contablePorVenta[$ventaId]['iva'] = round($contablePorVenta[$ventaId]['iva'] + $importe, 2);
                } elseif (in_array($cuentaId, $idsKiosco, true)) {
                    $contablePorVenta[$ventaId]['ventas_kiosco'] = round($contablePorVenta[$ventaId]['ventas_kiosco'] + $importe, 2);
                } elseif (in_array($cuentaId, $idsVentasGrav, true)) {
                    $contablePorVenta[$ventaId]['ventas_gravadas'] = round($contablePorVenta[$ventaId]['ventas_gravadas'] + $importe, 2);
                }
            }
        }

        $facturas = [];
        $stats = [
            'vinculadas' => 0,
            'cuadran' => 0,
            'con_diferencia' => 0,
            'sin_asiento' => 0,
            'cierre_agrupado' => 0,
        ];

        foreach ($filas as $fila) {
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            $tieneAsiento = $ventaId > 0 && isset($ventasConAsiento[$ventaId]);
            $modo = IvaVentasConciliacionModoSupport::modoDesdeFila($fila, $tieneAsiento);

            if (! IvaVentasConciliacionModoSupport::conciliaPorFactura($modo)) {
                if ($modo === IvaVentasConciliacionModoSupport::CIERRE_AGRUPADO) {
                    $stats['cierre_agrupado']++;
                } else {
                    $stats['sin_asiento']++;
                }
                continue;
            }

            $erp = $fila['columnas'] ?? IvaVentasColumnasSupport::montosVacios();
            $ctb = $contablePorVenta[$ventaId] ?? [
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
            ];

            $difNeto = round((float) ($erp['neto_gravado'] ?? 0) - (float) ($ctb['ventas_gravadas'] ?? 0), 2);
            $difImp = round((float) ($erp['imp_interno'] ?? 0) - (float) ($ctb['ventas_kiosco'] ?? 0), 2);
            $difIva = round((float) ($erp['iva'] ?? 0) - (float) ($ctb['iva'] ?? 0), 2);

            $cuadra = IvaVentasConciliacionCuentaSupport::cuadra((float) ($erp['neto_gravado'] ?? 0), (float) ($ctb['ventas_gravadas'] ?? 0))
                && IvaVentasConciliacionCuentaSupport::cuadra((float) ($erp['imp_interno'] ?? 0), (float) ($ctb['ventas_kiosco'] ?? 0))
                && IvaVentasConciliacionCuentaSupport::cuadra((float) ($erp['iva'] ?? 0), (float) ($ctb['iva'] ?? 0));

            $stats['vinculadas']++;
            if ($cuadra) {
                $stats['cuadran']++;
            } else {
                $stats['con_diferencia']++;
            }

            $facturas[] = [
                'venta_id' => $ventaId,
                'asiento_id' => (int) ($asientoPorVenta[$ventaId] ?? 0),
                'comprobante' => (string) ($fila['comprobante'] ?? ''),
                'fecha_mov' => (string) ($fila['fecha_mov'] ?? ''),
                'cliente_nombre' => (string) ($fila['cliente_nombre'] ?? ''),
                'puntoventa_codigo' => (string) ($fila['puntoventa_codigo'] ?? ''),
                'seccion' => (string) ($fila['seccion'] ?? ''),
                'seccion_label' => (string) ($fila['seccion_label'] ?? ''),
                'modo' => $modo,
                'erp' => [
                    'neto_gravado' => (float) ($erp['neto_gravado'] ?? 0),
                    'imp_interno' => (float) ($erp['imp_interno'] ?? 0),
                    'iva' => (float) ($erp['iva'] ?? 0),
                    'total' => (float) ($erp['total'] ?? 0),
                ],
                'contable' => $ctb,
                'diferencias' => [
                    'neto_gravado' => $difNeto,
                    'imp_interno' => $difImp,
                    'iva' => $difIva,
                ],
                'cuadra' => $cuadra,
            ];
        }

        usort($facturas, static function (array $a, array $b): int {
            if (($a['cuadra'] ?? false) !== ($b['cuadra'] ?? false)) {
                return ($a['cuadra'] ?? false) ? 1 : -1;
            }

            return strcmp((string) ($a['fecha_mov'] ?? ''), (string) ($b['fecha_mov'] ?? ''));
        });

        return [
            'habilitada' => true,
            'facturas' => $facturas,
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $statsAsiento
     * @param  array<string, mixed>  $porFactura
     * @return list<string>
     */
    private function notasConciliacion(array $statsAsiento, int $totalComprobantes, array $porFactura): array
    {
        $notas = [
            'Cuadre general: mayor contable del período (incluye cierres de jornada agrupados y facturas con asiento).',
            'Cuadre por factura: solo comprobantes con asiento vinculado (venta_id). Gastronomía / estacionamiento sin asiento individual se validan contra el cuadre general.',
            'Cuentas facturación: config/facturacion.php (CUENTACONTABLE_VENTA, CUENTACONTABLE_IVA, CUENTACONTABLE_PERCEPCION_IVA).',
            'Cuentas cierre jornada: tabla gastronomia_cierre_jornada_config (Caja → cierre jornada Waitry / proceso).',
        ];

        $sinAsiento = (int) ($statsAsiento['sin_asiento'] ?? 0);
        if ($totalComprobantes > 0 && $sinAsiento >= $totalComprobantes) {
            $notas[] = 'Ningún comprobante del período tiene asiento individual: la contabilización probablemente se agrupa en cierres de jornada (cuadre general).';
        } elseif ($sinAsiento > 0) {
            $notas[] = sprintf(
                '%d de %d comprobantes sin asiento vinculado (cierre agrupado); use el cuadre general para gastronomía / estacionamiento.',
                $sinAsiento,
                $totalComprobantes,
            );
        }

        $conDif = (int) ($porFactura['stats']['con_diferencia'] ?? 0);
        if ($conDif > 0) {
            $notas[] = sprintf(
                '%d factura(s) con asiento vinculado presentan diferencias ERP vs contable — revise el detalle inferior.',
                $conDif,
            );
        }

        return $notas;
    }

    /**
     * Coeficiente a moneda del reporte (misma lógica que calculaCoeficienteMoneda en biblioteca.php).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function sqlCoeficienteMonedaAsiento(array $filtros): string
    {
        $monedaReporteId = max(1, (int) ($filtros['moneda_id'] ?? 1));

        if ($monedaReporteId === 1) {
            return 'CASE WHEN COALESCE(am.moneda_id, 1) = 1 THEN 1 ELSE COALESCE(NULLIF(am.cotizacion, 0), 1) END';
        }

        return sprintf(
            'CASE '
            .'WHEN COALESCE(am.moneda_id, 1) = %1$d THEN 1 '
            .'WHEN COALESCE(am.moneda_id, 1) = 1 THEN 1 / COALESCE(NULLIF(am.cotizacion, 0), 1) '
            .'ELSE COALESCE(NULLIF(am.cotizacion, 0), 1) '
            .'END',
            $monedaReporteId,
        );
    }
}
