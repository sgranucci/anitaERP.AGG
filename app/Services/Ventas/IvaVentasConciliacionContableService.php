<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport;
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
        $contableVinculadoPv = $this->totalesContablesVinculadosPorPv($empresaId, $cuentas, $ventaIds);

        $porPuntoventa = $this->armarFilasPorPuntoventa(
            $totalesPorPv,
            $contableVinculadoPv,
            $statsAsiento,
            $filas,
        );

        $resumenEmpresa = $this->armarResumenEmpresa($totalesErp, $contableEmpresa, $statsAsiento, count($filas));

        return [
            'habilitada' => true,
            'cuentas' => $cuentas,
            'resumen_empresa' => $resumenEmpresa,
            'por_puntoventa' => $porPuntoventa,
            'notas' => $this->notasConciliacion($statsAsiento, count($filas)),
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
            'notas' => [],
        ];
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
        $idsIva = $cuentas['iva_debito'] ?? [];
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
            ->selectRaw('cc.id as cuenta_id, SUM(-am.monto * COALESCE(NULLIF(am.cotizacion, 0), 1)) as importe')
            ->groupBy('cc.id')
            ->get();

        $ventasGravadas = 0.0;
        $ventasKiosco = 0.0;
        $iva = 0.0;

        foreach ($rows as $row) {
            $importe = round((float) ($row->importe ?? 0), 2);
            $cuentaId = (int) ($row->cuenta_id ?? 0);

            if (in_array($cuentaId, $idsIva, true)) {
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
     * @return array<int, array<string, float>>
     */
    private function totalesContablesVinculadosPorPv(int $empresaId, array $cuentas, array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $idsVentas = array_merge(
            $cuentas['ventas_gravadas'] ?? [],
            $cuentas['ventas_kiosco'] ?? [],
        );
        $idsIva = $cuentas['iva_debito'] ?? [];
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
                ->selectRaw('v.puntoventa_id, cc.id as cuenta_id, SUM(-am.monto * COALESCE(NULLIF(am.cotizacion, 0), 1)) as importe')
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

                if (in_array($cuentaId, $idsIva, true)) {
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
     * @param  array<string, mixed>  $statsAsiento
     * @return list<string>
     */
    private function notasConciliacion(array $statsAsiento, int $totalComprobantes): array
    {
        $notas = [
            'Contable empresa: suma movimientos en cuentas configuradas (cierre jornada / facturación) dentro del período.',
            'Por punto de venta: contable vinculado solo cuando el asiento tiene venta_id (facturación unitaria).',
        ];

        $sinAsiento = (int) ($statsAsiento['sin_asiento'] ?? 0);
        if ($totalComprobantes > 0 && $sinAsiento >= $totalComprobantes) {
            $notas[] = 'Ningún comprobante del período tiene asiento individual: la contabilización probablemente se agrupa en cierres de jornada (cuadre general).';
        } elseif ($sinAsiento > 0) {
            $notas[] = sprintf(
                '%d de %d comprobantes sin asiento vinculado; use el cuadre general para validar contra el mayor.',
                $sinAsiento,
                $totalComprobantes,
            );
        }

        return $notas;
    }
}
