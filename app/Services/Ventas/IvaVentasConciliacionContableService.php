<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Support\Ventas\Gastronomia\CierreJornadaVentasCigarrillosSupport;
use App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionModoSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionUnidadCuentaSupport;
use App\Support\Ventas\IvaVentas\IvaVentasUnidadNegocioSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Support\Facades\DB;

final class IvaVentasConciliacionContableService
{
    public function __construct(
        private readonly IvaVentasCtamovAuditoriaService $ctamovAuditoriaService,
    ) {
    }

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

        $ctamov = ! empty($filtros['auditar_ctamov'])
            ? $this->ctamovAuditoriaService->auditar($empresaId, $filtros)
            : ['habilitada' => false];

        $statsAsiento = $this->statsAsientoPorVenta($ventaIds);
        $contableEmpresa = $this->totalesContablesEmpresa($empresaId, $filtros, $cuentas);
        $erpRubro = $this->desgloseErpPorRubro($filas, $empresaId);
        $contableVinculadoPv = $this->totalesContablesVinculadosPorPv($empresaId, $cuentas, $ventaIds, $filtros);

        $porPuntoventa = $this->armarFilasPorPuntoventa(
            $totalesPorPv,
            $contableVinculadoPv,
            $statsAsiento,
            $filas,
        );

        $conciliarPorUnidad = ! empty($filtros['conciliar_por_unidad']);
        $porUnidadNegocio = $conciliarPorUnidad
            ? $this->armarPorUnidadNegocio($empresaId, $filtros, $filas, $contableEmpresa, $ctamov)
            : ['habilitada' => false];
        $resumenEmpresa = $this->armarResumenEmpresa(
            $totalesErp,
            $contableEmpresa,
            $statsAsiento,
            count($filas),
            $ctamov,
            $erpRubro,
            $porUnidadNegocio,
        );
        $porFactura = $this->conciliarPorFacturaVinculada($empresaId, $filtros, $filas, $cuentas, $statsAsiento);
        $auditoriaDiaria = $this->auditoriaDiaria($empresaId, $filtros, $filas, $cuentas, $ctamov);
        $auditoriaDiariaUnidad = $conciliarPorUnidad
            ? $this->auditoriaDiariaPorUnidadNegocio($empresaId, $filtros, $filas)
            : ['habilitada' => false];

        return [
            'habilitada' => true,
            'cuentas' => $cuentas,
            'resumen_empresa' => $resumenEmpresa,
            'por_puntoventa' => $porPuntoventa,
            'por_unidad_negocio' => $porUnidadNegocio,
            'por_factura_vinculada' => $porFactura,
            'auditoria_diaria' => $auditoriaDiaria,
            'auditoria_diaria_unidad' => $auditoriaDiariaUnidad,
            'ctamov' => $ctamov,
            'notas' => $this->notasConciliacion($statsAsiento, count($filas), $porFactura, $ctamov),
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
            'auditoria_diaria_unidad' => ['habilitada' => false, 'unidades' => []],
            'por_unidad_negocio' => ['habilitada' => false],
            'ctamov' => ['habilitada' => false],
            'notas' => [],
        ];
    }

    /**
     * Cuadre día por día entre IVA ventas (ERP) y mayor contable.
     * Tolerancia diaria más amplia que el cuadre global (redondeos y cierres parciales).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cuentas
     * @param  array<string, mixed>  $ctamov
     * @return array<string, mixed>
     */
    private function auditoriaDiaria(int $empresaId, array $filtros, array $filas, array $cuentas, array $ctamov = ['habilitada' => false]): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return ['habilitada' => false, 'dias' => [], 'stats' => []];
        }

        $ctamovHabilitado = ! empty($ctamov['habilitada']);
        $ctamovPorDia = $ctamov['por_dia'] ?? [];

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
                    'exento' => 0.0,
                    'iva' => 0.0,
                    'total' => 0.0,
                ];
            }

            $col = $fila['columnas'] ?? [];
            $erpPorDia[$dia]['comprobantes']++;
            $erpPorDia[$dia]['neto_gravado'] = round($erpPorDia[$dia]['neto_gravado'] + (float) ($col['neto_gravado'] ?? 0), 2);
            $erpPorDia[$dia]['imp_interno'] = round($erpPorDia[$dia]['imp_interno'] + (float) ($col['imp_interno'] ?? 0), 2);
            $erpPorDia[$dia]['exento'] = round($erpPorDia[$dia]['exento'] + (float) ($col['exento'] ?? 0), 2);
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
                'exento' => 0.0,
                'iva' => 0.0,
                'total' => 0.0,
            ];
            $ctb = $contablePorDia[$dia] ?? [
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
                'ventas_total' => 0.0,
            ];

            $erpVentas = round(
                (float) $erp['neto_gravado'] + (float) $erp['imp_interno'] + (float) ($erp['exento'] ?? 0),
                2,
            );
            $ctbVentas = round((float) ($ctb['ventas_total'] ?? 0), 2);
            $difVentas = round($erpVentas - $ctbVentas, 2);
            $difIva = round((float) $erp['iva'] - (float) ($ctb['iva'] ?? 0), 2);

            $tol = IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA;
            $cuadra = IvaVentasConciliacionCuentaSupport::cuadra($erpVentas, $ctbVentas, $tol)
                && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['iva'], (float) ($ctb['iva'] ?? 0), $tol);

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

            $ctm = $ctamovPorDia[$dia] ?? ['ventas' => 0.0, 'iva' => 0.0];
            $ctamovVentas = round((float) ($ctm['ventas'] ?? 0), 2);
            $ctamovIva = round((float) ($ctm['iva'] ?? 0), 2);
            $difCtamovVentas = round((float) ($ctb['ventas_total'] ?? 0) - $ctamovVentas, 2);
            $difCtamovIva = round((float) ($ctb['iva'] ?? 0) - $ctamovIva, 2);

            $dias[] = [
                'dia' => $dia,
                'dia_texto' => date('d/m/Y', strtotime($dia)),
                'comprobantes' => (int) $erp['comprobantes'],
                'erp' => [
                    'ventas' => $erpVentas,
                    'neto_gravado' => (float) $erp['neto_gravado'],
                    'imp_interno' => (float) $erp['imp_interno'],
                    'exento' => (float) ($erp['exento'] ?? 0),
                    'iva' => (float) $erp['iva'],
                    'total' => (float) $erp['total'],
                ],
                'contable' => [
                    'ventas' => $ctbVentas,
                    'ventas_gravadas' => (float) ($ctb['ventas_gravadas'] ?? 0),
                    'ventas_kiosco' => (float) ($ctb['ventas_kiosco'] ?? 0),
                    'iva' => (float) ($ctb['iva'] ?? 0),
                    'ventas_total' => (float) ($ctb['ventas_total'] ?? 0),
                ],
                'ctamov' => [
                    'habilitado' => $ctamovHabilitado,
                    'ventas' => $ctamovVentas,
                    'iva' => $ctamovIva,
                    'dif_ventas' => $difCtamovVentas,
                    'dif_iva' => $difCtamovIva,
                ],
                'diferencias' => [
                    'ventas' => $difVentas,
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
            'ctamov_habilitado' => $ctamovHabilitado,
            'dias' => $dias,
            'stats' => $stats,
        ];
    }

    /**
     * Cuadre día por día por unidad de negocio (ERP vs cuentas de imputación de cada proceso).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function auditoriaDiariaPorUnidadNegocio(int $empresaId, array $filtros, array $filas): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return ['habilitada' => false, 'unidades' => []];
        }

        $mapaUnidades = IvaVentasConciliacionUnidadCuentaSupport::mapaUnidades($empresaId);
        $vendingPvIds = array_keys(IvaVentasUnidadNegocioSupport::vendingPuntoventaIds($empresaId));
        $contablePorDiaUnidad = $this->totalesContablesPorDiaUnidad($empresaId, $filtros, $mapaUnidades, $vendingPvIds);

        $erpPorDiaUnidad = [];
        foreach ($filas as $fila) {
            $dia = (string) ($fila['fecha_orden'] ?? '');
            $unidad = (string) ($fila['unidad_negocio'] ?? IvaVentasUnidadNegocioSupport::OTROS);
            if ($dia === '') {
                continue;
            }

            if (! isset($erpPorDiaUnidad[$unidad][$dia])) {
                $erpPorDiaUnidad[$unidad][$dia] = [
                    'comprobantes' => 0,
                    'neto_gravado' => 0.0,
                    'imp_interno' => 0.0,
                    'exento' => 0.0,
                    'iva' => 0.0,
                    'total' => 0.0,
                ];
            }

            $col = $fila['columnas'] ?? [];
            $erpPorDiaUnidad[$unidad][$dia]['comprobantes']++;
            $erpPorDiaUnidad[$unidad][$dia]['neto_gravado'] = round(
                $erpPorDiaUnidad[$unidad][$dia]['neto_gravado'] + (float) ($col['neto_gravado'] ?? 0),
                2,
            );
            $erpPorDiaUnidad[$unidad][$dia]['imp_interno'] = round(
                $erpPorDiaUnidad[$unidad][$dia]['imp_interno'] + (float) ($col['imp_interno'] ?? 0),
                2,
            );
            $erpPorDiaUnidad[$unidad][$dia]['exento'] = round(
                $erpPorDiaUnidad[$unidad][$dia]['exento'] + (float) ($col['exento'] ?? 0),
                2,
            );
            $erpPorDiaUnidad[$unidad][$dia]['iva'] = round(
                $erpPorDiaUnidad[$unidad][$dia]['iva'] + (float) ($col['iva'] ?? 0),
                2,
            );
            $erpPorDiaUnidad[$unidad][$dia]['total'] = round(
                $erpPorDiaUnidad[$unidad][$dia]['total'] + (float) ($col['total'] ?? 0),
                2,
            );
        }

        $salidaUnidades = [];
        foreach (IvaVentasUnidadNegocioSupport::orden() as $orden) {
            if (! isset($mapaUnidades[$orden])) {
                continue;
            }
            $cfg = $mapaUnidades[$orden];
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
                $erp = $erpPorDiaUnidad[$orden][$dia] ?? [
                    'comprobantes' => 0,
                    'neto_gravado' => 0.0,
                    'imp_interno' => 0.0,
                    'exento' => 0.0,
                    'iva' => 0.0,
                    'total' => 0.0,
                ];
                $ctb = $contablePorDiaUnidad[$orden][$dia] ?? [
                    'ventas_gravadas' => 0.0,
                    'ventas_kiosco' => 0.0,
                    'iva' => 0.0,
                    'ventas_total' => 0.0,
                ];

                $erpVentas = round(
                    (float) $erp['neto_gravado'] + (float) $erp['imp_interno'] + (float) ($erp['exento'] ?? 0),
                    2,
                );
                $ctbVentas = round((float) ($ctb['ventas_total'] ?? 0), 2);
                $difVentas = round($erpVentas - $ctbVentas, 2);
                $difIva = round((float) $erp['iva'] - (float) ($ctb['iva'] ?? 0), 2);
                $ctbTotal = round($ctbVentas + (float) ($ctb['iva'] ?? 0), 2);
                $difTotal = round((float) $erp['total'] - $ctbTotal, 2);

                $tol = IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA;
                $cuadra = IvaVentasConciliacionCuentaSupport::cuadra($erpVentas, $ctbVentas, $tol)
                    && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['iva'], (float) ($ctb['iva'] ?? 0), $tol)
                    && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['total'], $ctbTotal, $tol);

                $tieneMovimiento = (int) $erp['comprobantes'] > 0
                    || abs($ctbVentas) > $tol
                    || abs((float) ($ctb['iva'] ?? 0)) > $tol;

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
                        'ventas' => $erpVentas,
                        'neto_gravado' => (float) $erp['neto_gravado'],
                        'imp_interno' => (float) $erp['imp_interno'],
                        'exento' => (float) ($erp['exento'] ?? 0),
                        'iva' => (float) $erp['iva'],
                        'total' => (float) $erp['total'],
                    ],
                    'contable' => [
                        'ventas' => $ctbVentas,
                        'ventas_gravadas' => (float) ($ctb['ventas_gravadas'] ?? 0),
                        'ventas_kiosco' => (float) ($ctb['ventas_kiosco'] ?? 0),
                        'iva' => (float) ($ctb['iva'] ?? 0),
                        'total' => $ctbTotal,
                    ],
                    'diferencias' => [
                        'ventas' => $difVentas,
                        'iva' => $difIva,
                        'total' => $difTotal,
                    ],
                    'cuadra' => $cuadra,
                    'tiene_movimiento' => $tieneMovimiento,
                ];

                $cursor = strtotime('+1 day', $cursor);
            }

                $salidaUnidades[] = [
                'key' => $orden,
                'label' => (string) ($cfg['label'] ?? IvaVentasUnidadNegocioSupport::label($orden)),
                'cuentas_detalle' => $cfg['cuentas_detalle'] ?? [],
                'dias' => $dias,
                'stats' => $stats,
                'abrir' => (int) ($stats['dias_con_diferencia'] ?? 0) > 0,
            ];
        }

        return [
            'habilitada' => count($salidaUnidades) > 0,
            'tolerancia' => IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA,
            'unidades' => $salidaUnidades,
        ];
    }

    /**
     * @param  array<string, array{
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva: list<int>
     * }>  $mapaUnidades
     * @param  list<int>  $vendingPvIds
     * @return array<string, array<string, array<string, float>>>
     */
    private function totalesContablesPorDiaUnidad(int $empresaId, array $filtros, array $mapaUnidades, array $vendingPvIds): array
    {
        $ordenFecha = (string) ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA);

        $idsTodos = [];
        foreach ($mapaUnidades as $cfg) {
            $idsTodos = array_merge(
                $idsTodos,
                $cfg['ventas_gravadas'] ?? [],
                $cfg['ventas_kiosco'] ?? [],
                $cfg['iva'] ?? [],
            );
        }
        $idsTodos = array_values(array_unique($idsTodos));
        if ($idsTodos === []) {
            return [];
        }

        $diaExpr = IvaVentasConciliacionUnidadCuentaSupport::sqlDiaContableExpr($ordenFecha);

        $unidadExpr = IvaVentasConciliacionUnidadCuentaSupport::sqlClasificarUnidadAsiento($vendingPvIds);

        $query = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('venta as v', function ($join) {
                $join->on('v.id', '=', 'a.venta_id');
            })
            ->leftJoin('venta_estacionamiento_emision as vee', 'vee.venta_id', '=', 'a.venta_id')
            ->leftJoin('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'a.venta_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('cc.id', $idsTodos);

        IvaVentasConciliacionUnidadCuentaSupport::aplicarFiltroPeriodoConciliacion($query, $filtros);

        $cuentasGlobal = IvaVentasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);
        $idsIvaGlobal = array_values(array_unique(array_merge(
            $cuentasGlobal['iva_debito'] ?? [],
            $cuentasGlobal['percepcion_iva'] ?? [],
            $cuentasGlobal['iva_credito'] ?? [],
        )));
        $idsKioscoGlobal = $cuentasGlobal['ventas_kiosco'] ?? [];
        $idsGravadasGlobal = $cuentasGlobal['ventas_gravadas'] ?? [];

        $rows = $query
            ->selectRaw($diaExpr.' as dia, ('.$unidadExpr.') as unidad, cc.id as cuenta_id, SUM(-am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
            ->groupByRaw($diaExpr.', ('.$unidadExpr.'), cc.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $dia = (string) ($row->dia ?? '');
            $unidadSql = (string) ($row->unidad ?? IvaVentasUnidadNegocioSupport::OTROS);
            $cuentaId = (int) ($row->cuenta_id ?? 0);
            $unidad = IvaVentasConciliacionUnidadCuentaSupport::resolverUnidadMovimiento($unidadSql, $cuentaId, $mapaUnidades);
            if ($dia === '' || ! isset($mapaUnidades[$unidad])) {
                continue;
            }

            $importe = round((float) ($row->importe ?? 0), 2);

            if (! isset($out[$unidad][$dia])) {
                $out[$unidad][$dia] = [
                    'ventas_gravadas' => 0.0,
                    'ventas_kiosco' => 0.0,
                    'iva' => 0.0,
                    'ventas_total' => 0.0,
                ];
            }

            if (in_array($cuentaId, $idsIvaGlobal, true)) {
                $out[$unidad][$dia]['iva'] = round($out[$unidad][$dia]['iva'] + $importe, 2);
            } elseif (in_array($cuentaId, $idsKioscoGlobal, true)) {
                $out[$unidad][$dia]['ventas_kiosco'] = round($out[$unidad][$dia]['ventas_kiosco'] + $importe, 2);
            } elseif (in_array($cuentaId, $idsGravadasGlobal, true)) {
                $out[$unidad][$dia]['ventas_gravadas'] = round($out[$unidad][$dia]['ventas_gravadas'] + $importe, 2);
            }

            $out[$unidad][$dia]['ventas_total'] = round(
                $out[$unidad][$dia]['ventas_gravadas'] + $out[$unidad][$dia]['ventas_kiosco'],
                2,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $cuentas
     * @return array<string, array<string, float>>
     */
    private function totalesContablesPorDia(int $empresaId, array $filtros, array $cuentas): array
    {
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

        $diaExpr = IvaVentasConciliacionUnidadCuentaSupport::sqlDiaContableExpr($ordenFecha);

        $query = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('venta as v', function ($join) {
                $join->on('v.id', '=', 'a.venta_id');
            })
            ->where('a.empresa_id', $empresaId)
            ->whereIn('cc.id', $idsTodos);

        IvaVentasConciliacionUnidadCuentaSupport::aplicarFiltroPeriodoConciliacion($query, $filtros);

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
            ->leftJoin('venta as v', function ($join) {
                $join->on('v.id', '=', 'a.venta_id');
            })
            ->where('a.empresa_id', $empresaId)
            ->whereIn('cc.id', $idsTodos);

        IvaVentasConciliacionUnidadCuentaSupport::aplicarFiltroPeriodoConciliacion($query, $filtros);

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
     * @param  array<string, mixed>  $ctamov
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $porUnidadNegocio
     * @return array<string, mixed>
     */
    private function armarResumenEmpresa(
        array $totalesErp,
        array $contableEmpresa,
        array $statsAsiento,
        int $cantidadComprobantes,
        array $ctamov = ['habilitada' => false],
        array $erpRubro = [],
        array $porUnidadNegocio = [],
    ): array {
        $erpTabaco = (float) ($erpRubro['tabaco'] ?? (float) ($totalesErp['imp_interno'] ?? 0));
        $erpExento = (float) ($erpRubro['exento'] ?? (float) ($totalesErp['exento'] ?? 0));
        $tolResumen = IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA;
        $unidades = $porUnidadNegocio['unidades'] ?? [];

        // Antes: "Alimentos y Bebidas" mezclaba gastronomía + estacionamiento + admin en un solo
        // bucket vs todas las cuentas ventas_gravadas → la falta de cierre de estacionamiento
        // aparecía como diferencia de AYB. Ahora el cuadre general abre por unidad de negocio.
        $lineas = [];
        if ($unidades !== []) {
            foreach ($unidades as $unidad) {
                $label = (string) ($unidad['label'] ?? IvaVentasUnidadNegocioSupport::label((string) ($unidad['key'] ?? '')));
                $erpVentas = round((float) ($unidad['erp_ventas'] ?? 0), 2);
                $ctbVentas = round((float) ($unidad['contable']['ventas'] ?? 0), 2);
                $erpIva = round((float) ($unidad['iva'] ?? 0), 2);
                $ctbIva = round((float) ($unidad['contable']['iva'] ?? 0), 2);
                $tieneMov = ! empty($unidad['tiene_movimiento']);

                $lineas[] = [
                    'concepto' => $label.' — ventas (neto + II + exento)',
                    'erp' => $erpVentas,
                    'contable' => $ctbVentas,
                    'unidad' => (string) ($unidad['key'] ?? ''),
                    'tipo' => 'unidad_ventas',
                    'tiene_movimiento' => $tieneMov,
                ];
                $lineas[] = [
                    'concepto' => $label.' — IVA débito fiscal',
                    'erp' => $erpIva,
                    'contable' => $ctbIva,
                    'unidad' => (string) ($unidad['key'] ?? ''),
                    'tipo' => 'unidad_iva',
                    'tiene_movimiento' => $tieneMov,
                ];
            }

            $lineas[] = [
                'concepto' => 'Detalle gastronomía — Ventas Tabaco (neto + imp. interno)',
                'erp' => round($erpTabaco, 2),
                'contable' => round((float) ($contableEmpresa['ventas_kiosco'] ?? 0), 2),
                'unidad' => IvaVentasUnidadNegocioSupport::GASTRONOMIA,
                'tipo' => 'detalle_tabaco',
                'tiene_movimiento' => abs($erpTabaco) > 0.009
                    || abs((float) ($contableEmpresa['ventas_kiosco'] ?? 0)) > 0.009,
            ];
        } else {
            $erpAlimentos = (float) ($erpRubro['alimentos'] ?? (float) ($totalesErp['neto_gravado'] ?? 0));
            $lineas = [
                [
                    'concepto' => 'Ventas gravadas (neto + exento, sin desglose por unidad)',
                    'erp' => round($erpAlimentos, 2),
                    'contable' => (float) ($contableEmpresa['ventas_gravadas'] ?? 0),
                    'tipo' => 'fallback_ventas',
                    'tiene_movimiento' => true,
                ],
                [
                    'concepto' => 'Ventas Tabaco (neto + imp. interno)',
                    'erp' => round($erpTabaco, 2),
                    'contable' => (float) ($contableEmpresa['ventas_kiosco'] ?? 0),
                    'tipo' => 'detalle_tabaco',
                    'tiene_movimiento' => true,
                ],
                [
                    'concepto' => 'IVA débito fiscal',
                    'erp' => (float) ($totalesErp['iva'] ?? 0),
                    'contable' => (float) ($contableEmpresa['iva'] ?? 0),
                    'tipo' => 'fallback_iva',
                    'tiene_movimiento' => true,
                ],
            ];
        }

        foreach ($lineas as &$linea) {
            $linea['diferencia'] = round((float) $linea['erp'] - (float) $linea['contable'], 2);
            $linea['cuadra'] = IvaVentasConciliacionCuentaSupport::cuadra(
                (float) $linea['erp'],
                (float) $linea['contable'],
                $tolResumen,
            );
        }
        unset($linea);

        $ctamovHabilitado = ! empty($ctamov['habilitada']);
        $ctamovVentas = round((float) ($ctamov['total']['ventas'] ?? 0), 2);
        $ctamovIva = round((float) ($ctamov['total']['iva'] ?? 0), 2);

        $lineasCuadreGlobal = array_values(array_filter(
            $lineas,
            static fn (array $l) => ($l['tipo'] ?? '') !== 'detalle_tabaco'
                && ! empty($l['tiene_movimiento']),
        ));

        return [
            'lineas' => $lineas,
            'erp_exento' => round($erpExento, 2),
            'erp_total' => (float) ($totalesErp['total'] ?? 0),
            'contable_ventas_total' => (float) ($contableEmpresa['ventas_total'] ?? 0),
            'contable_iva' => (float) ($contableEmpresa['iva'] ?? 0),
            'ctamov_habilitado' => $ctamovHabilitado,
            'ctamov_ventas_total' => $ctamovVentas,
            'ctamov_iva' => $ctamovIva,
            'ctamov_dif_ventas' => round((float) ($contableEmpresa['ventas_total'] ?? 0) - $ctamovVentas, 2),
            'ctamov_dif_iva' => round((float) ($contableEmpresa['iva'] ?? 0) - $ctamovIva, 2),
            'ctamov_cuadra' => $ctamovHabilitado
                && IvaVentasConciliacionCuentaSupport::cuadra((float) ($contableEmpresa['ventas_total'] ?? 0), $ctamovVentas, IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA)
                && IvaVentasConciliacionCuentaSupport::cuadra((float) ($contableEmpresa['iva'] ?? 0), $ctamovIva, IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA),
            'comprobantes' => $cantidadComprobantes,
            'con_asiento' => (int) ($statsAsiento['con_asiento'] ?? 0),
            'sin_asiento' => (int) ($statsAsiento['sin_asiento'] ?? 0),
            'cuadra_global' => $lineasCuadreGlobal === []
                || collect($lineasCuadreGlobal)->every(static fn (array $l) => ! empty($l['cuadra'])),
        ];
    }

    /**
     * Desglose de las ventas del ERP en rubros contables (Alimentos/Bebidas vs Tabaco)
     * aplicando a cada comprobante la misma lógica de imputación del cierre de jornada.
     *
     * Solo se cargan las ventas con impuesto interno (tabaco) para resolver el importe de
     * cigarrillos; el resto se imputa directo a ventas gravadas (incluye exento, sin IVA).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{alimentos: float, tabaco: float, iva: float, exento: float, imp_interno: float}
     */
    private function desgloseErpPorRubro(array $filas, int $empresaId): array
    {
        $alimentos = 0.0;
        $tabaco = 0.0;
        $iva = 0.0;
        $exento = 0.0;
        $impInterno = 0.0;

        foreach ($filas as $fila) {
            $col = $fila['columnas'] ?? [];
            $total = round((float) ($col['total'] ?? 0), 2);
            $ii = round((float) ($col['imp_interno'] ?? 0), 2);
            $ex = round((float) ($col['exento'] ?? 0), 2);

            $importeCig = 0.0;
            if (abs($ii) > 0.0001) {
                $ventaId = (int) ($fila['venta_id'] ?? 0);
                if ($ventaId > 0 && $empresaId > 0) {
                    $importeCig = CierreJornadaVentasCigarrillosSupport::importeLineasMenuCigarrillosPorVentaId($ventaId, $empresaId);
                }
            }

            $desg = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables($total, $ii, $importeCig, $ex);

            $alimentos = round($alimentos + (float) ($desg['ventas_gravadas'] ?? 0), 2);
            $tabaco = round($tabaco + (float) ($desg['ventas_kiosco'] ?? 0), 2);
            $iva = round($iva + (float) ($desg['iva_normal'] ?? 0) + (float) ($desg['iva_cigarrillos'] ?? 0), 2);
            $exento = round($exento + $ex, 2);
            $impInterno = round($impInterno + $ii, 2);
        }

        return [
            'alimentos' => $alimentos,
            'tabaco' => $tabaco,
            'iva' => $iva,
            'exento' => $exento,
            'imp_interno' => $impInterno,
        ];
    }

    /**
     * Desglose ERP vs contable por unidad de negocio (gastronomía, estacionamiento,
     * máquinas vending, administración) y cuadre del total del período.
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, float>  $contableEmpresa
     * @param  array<string, mixed>  $ctamov
     * @return array<string, mixed>
     */
    private function armarPorUnidadNegocio(
        int $empresaId,
        array $filtros,
        array $filas,
        array $contableEmpresa,
        array $ctamov,
    ): array {
        $mapaUnidades = IvaVentasConciliacionUnidadCuentaSupport::mapaUnidades($empresaId);
        $vendingPvIds = array_keys(IvaVentasUnidadNegocioSupport::vendingPuntoventaIds($empresaId));
        $contablePorDiaUnidad = $this->totalesContablesPorDiaUnidad(
            $empresaId,
            $filtros,
            $mapaUnidades,
            $vendingPvIds,
        );

        $contablePorUnidad = [];
        foreach ($mapaUnidades as $key => $_cfg) {
            $contablePorUnidad[$key] = [
                'ventas' => 0.0,
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
            ];
        }
        foreach ($contablePorDiaUnidad as $unidad => $dias) {
            if (! isset($contablePorUnidad[$unidad])) {
                $contablePorUnidad[$unidad] = [
                    'ventas' => 0.0,
                    'ventas_gravadas' => 0.0,
                    'ventas_kiosco' => 0.0,
                    'iva' => 0.0,
                ];
            }
            foreach ($dias as $totDia) {
                $contablePorUnidad[$unidad]['ventas_gravadas'] = round(
                    $contablePorUnidad[$unidad]['ventas_gravadas'] + (float) ($totDia['ventas_gravadas'] ?? 0),
                    2,
                );
                $contablePorUnidad[$unidad]['ventas_kiosco'] = round(
                    $contablePorUnidad[$unidad]['ventas_kiosco'] + (float) ($totDia['ventas_kiosco'] ?? 0),
                    2,
                );
                $contablePorUnidad[$unidad]['iva'] = round(
                    $contablePorUnidad[$unidad]['iva'] + (float) ($totDia['iva'] ?? 0),
                    2,
                );
            }
            $contablePorUnidad[$unidad]['ventas'] = round(
                $contablePorUnidad[$unidad]['ventas_gravadas'] + $contablePorUnidad[$unidad]['ventas_kiosco'],
                2,
            );
        }

        $buckets = [];
        foreach ($filas as $fila) {
            $key = (string) ($fila['unidad_negocio'] ?? IvaVentasUnidadNegocioSupport::OTROS);
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'neto_gravado' => 0.0,
                    'imp_interno' => 0.0,
                    'exento' => 0.0,
                    'iva' => 0.0,
                    'total' => 0.0,
                    'cantidad' => 0,
                ];
            }

            $columnas = $fila['columnas'] ?? [];
            $buckets[$key]['neto_gravado'] = round($buckets[$key]['neto_gravado'] + (float) ($columnas['neto_gravado'] ?? 0), 2);
            $buckets[$key]['imp_interno'] = round($buckets[$key]['imp_interno'] + (float) ($columnas['imp_interno'] ?? 0), 2);
            $buckets[$key]['exento'] = round($buckets[$key]['exento'] + (float) ($columnas['exento'] ?? 0), 2);
            $buckets[$key]['iva'] = round($buckets[$key]['iva'] + (float) ($columnas['iva'] ?? 0), 2);
            $buckets[$key]['total'] = round($buckets[$key]['total'] + (float) ($columnas['total'] ?? 0), 2);
            $buckets[$key]['cantidad'] += (int) ($fila['cantidad_comprobantes'] ?? 1);
        }

        $tol = IvaVentasConciliacionCuentaSupport::TOLERANCIA_DIARIA;
        $unidades = [];
        foreach (IvaVentasUnidadNegocioSupport::orden() as $orden) {
            if (! isset($mapaUnidades[$orden]) && ! isset($buckets[$orden])) {
                continue;
            }

            $erp = $buckets[$orden] ?? [
                'neto_gravado' => 0.0,
                'imp_interno' => 0.0,
                'exento' => 0.0,
                'iva' => 0.0,
                'total' => 0.0,
                'cantidad' => 0,
            ];
            unset($buckets[$orden]);

            $erpVentasUnidad = round(
                (float) $erp['neto_gravado'] + (float) $erp['imp_interno'] + (float) $erp['exento'],
                2,
            );
            $ctb = $contablePorUnidad[$orden] ?? [
                'ventas' => 0.0,
                'ventas_gravadas' => 0.0,
                'ventas_kiosco' => 0.0,
                'iva' => 0.0,
            ];
            $ctbVentas = round((float) ($ctb['ventas'] ?? 0), 2);
            $ctbIva = round((float) ($ctb['iva'] ?? 0), 2);
            $difVentas = round($erpVentasUnidad - $ctbVentas, 2);
            $difIva = round((float) $erp['iva'] - $ctbIva, 2);
            $cuadra = IvaVentasConciliacionCuentaSupport::cuadra($erpVentasUnidad, $ctbVentas, $tol)
                && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['iva'], $ctbIva, $tol);
            $tieneMovimiento = (int) $erp['cantidad'] > 0
                || abs($ctbVentas) > $tol
                || abs($ctbIva) > $tol;

            $unidades[] = [
                'key' => $orden,
                'label' => IvaVentasUnidadNegocioSupport::label($orden),
                'neto_gravado' => (float) $erp['neto_gravado'],
                'imp_interno' => (float) $erp['imp_interno'],
                'exento' => (float) $erp['exento'],
                'iva' => (float) $erp['iva'],
                'total' => (float) $erp['total'],
                'cantidad' => (int) $erp['cantidad'],
                'erp_ventas' => $erpVentasUnidad,
                'contable' => [
                    'ventas' => $ctbVentas,
                    'ventas_gravadas' => round((float) ($ctb['ventas_gravadas'] ?? 0), 2),
                    'ventas_kiosco' => round((float) ($ctb['ventas_kiosco'] ?? 0), 2),
                    'iva' => $ctbIva,
                ],
                'diferencias' => [
                    'ventas' => $difVentas,
                    'iva' => $difIva,
                ],
                'cuadra' => $cuadra,
                'tiene_movimiento' => $tieneMovimiento,
                'cuentas_detalle' => $mapaUnidades[$orden]['cuentas_detalle'] ?? [],
            ];
        }
        foreach ($buckets as $key => $erp) {
            $erpVentasUnidad = round(
                (float) $erp['neto_gravado'] + (float) $erp['imp_interno'] + (float) $erp['exento'],
                2,
            );
            $unidades[] = [
                'key' => $key,
                'label' => IvaVentasUnidadNegocioSupport::label($key),
                'neto_gravado' => (float) $erp['neto_gravado'],
                'imp_interno' => (float) $erp['imp_interno'],
                'exento' => (float) $erp['exento'],
                'iva' => (float) $erp['iva'],
                'total' => (float) $erp['total'],
                'cantidad' => (int) $erp['cantidad'],
                'erp_ventas' => $erpVentasUnidad,
                'contable' => [
                    'ventas' => 0.0,
                    'ventas_gravadas' => 0.0,
                    'ventas_kiosco' => 0.0,
                    'iva' => 0.0,
                ],
                'diferencias' => [
                    'ventas' => $erpVentasUnidad,
                    'iva' => (float) $erp['iva'],
                ],
                'cuadra' => IvaVentasConciliacionCuentaSupport::cuadra($erpVentasUnidad, 0.0, $tol)
                    && IvaVentasConciliacionCuentaSupport::cuadra((float) $erp['iva'], 0.0, $tol),
                'tiene_movimiento' => (int) $erp['cantidad'] > 0,
                'cuentas_detalle' => [],
            ];
        }

        $totNeto = round(array_sum(array_column($unidades, 'neto_gravado')), 2);
        $totImp = round(array_sum(array_column($unidades, 'imp_interno')), 2);
        $totExento = round(array_sum(array_column($unidades, 'exento')), 2);
        $totIva = round(array_sum(array_column($unidades, 'iva')), 2);
        $totTotal = round(array_sum(array_column($unidades, 'total')), 2);
        $erpVentas = round($totNeto + $totImp + $totExento, 2);

        $contVentas = round((float) ($contableEmpresa['ventas_total'] ?? 0), 2);
        $contIva = round((float) ($contableEmpresa['iva'] ?? 0), 2);

        $ctamovHabilitado = ! empty($ctamov['habilitada']);
        $ctamovVentas = round((float) ($ctamov['total']['ventas'] ?? 0), 2);
        $ctamovIva = round((float) ($ctamov['total']['iva'] ?? 0), 2);

        $cuadre = [
            'ventas' => [
                'concepto' => 'Ventas (neto + kiosco + exento)',
                'erp' => $erpVentas,
                'contable' => $contVentas,
                'dif_contable' => round($erpVentas - $contVentas, 2),
                'ctamov' => $ctamovVentas,
                'dif_ctamov' => round($erpVentas - $ctamovVentas, 2),
                'cuadra' => IvaVentasConciliacionCuentaSupport::cuadra($erpVentas, $contVentas, $tol),
            ],
            'iva' => [
                'concepto' => 'IVA débito fiscal',
                'erp' => $totIva,
                'contable' => $contIva,
                'dif_contable' => round($totIva - $contIva, 2),
                'ctamov' => $ctamovIva,
                'dif_ctamov' => round($totIva - $ctamovIva, 2),
                'cuadra' => IvaVentasConciliacionCuentaSupport::cuadra($totIva, $contIva, $tol),
            ],
        ];

        return [
            'habilitada' => true,
            'unidades' => $unidades,
            'total_erp' => [
                'neto_gravado' => $totNeto,
                'imp_interno' => $totImp,
                'exento' => $totExento,
                'iva' => $totIva,
                'total' => $totTotal,
                'ventas' => $erpVentas,
            ],
            'total_contable' => [
                'ventas' => $contVentas,
                'iva' => $contIva,
            ],
            'cuadre' => $cuadre,
            'ctamov_habilitado' => $ctamovHabilitado,
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
     * @param  array<string, mixed>  $ctamov
     * @return list<string>
     */
    private function notasConciliacion(array $statsAsiento, int $totalComprobantes, array $porFactura, array $ctamov = ['habilitada' => false]): array
    {
        $notas = [
            'Cuadre general: mayor contable del período (incluye cierres de jornada agrupados y facturas con asiento).',
            'Cuadre por factura: solo comprobantes con asiento vinculado (venta_id). Gastronomía / estacionamiento sin asiento individual se validan contra el cuadre general.',
            'Cuentas facturación: config/facturacion.php (CUENTACONTABLE_VENTA, CUENTACONTABLE_IVA, CUENTACONTABLE_PERCEPCION_IVA, CUENTACONTABLE_PERCEPCION_NO_CATEGORIZADO) e impuesto PNC.',
            'Cuentas cierre jornada: tabla gastronomia_cierre_jornada_config (Caja → cierre jornada Waitry / proceso).',
            'Cuentas configurables del reporte: config/iva_ventas.php (ventas + IVA débito/crédito por empresa).',
        ];

        if (! empty($ctamov['habilitada'])) {
            $notas[] = 'ctamov (Anita): '.(int) ($ctamov['lineas'] ?? 0).' línea(s) leídas del bridge para las cuentas configuradas (ventas por haber, IVA crédito netea).';
            foreach ($ctamov['errores'] ?? [] as $error) {
                $notas[] = 'ctamov: '.$error;
            }
        }

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
