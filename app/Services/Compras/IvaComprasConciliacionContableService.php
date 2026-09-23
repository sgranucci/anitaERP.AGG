<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Support\Compras\IvaCompras\IvaComprasConciliacionCuentaSupport;
use App\Support\Compras\IvaCompras\IvaComprasDesgloseSupport;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación IVA compras (ERP) vs mayor on-line.
 *
 * Cuadra solo IVA crédito y percepciones, sobre los asientos vinculados a los
 * comprobantes del libro (misma fecha IVA), con cotización de am.moneda_id/am.cotizacion.
 */
final class IvaComprasConciliacionContableService
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

        $cuentas = IvaComprasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);
        $filas = $resultadoIva['filas'] ?? [];

        $erp = $this->desgloseErp($filas);
        $asientoIds = $this->asientoIdsDeFilas($filas);
        $contable = $this->totalesContablesAsientos($empresaId, $filtros, $cuentas, $asientoIds);
        $resumen = $this->armarResumen($erp, $contable);
        $auditoriaDiaria = $this->auditoriaDiaria($empresaId, $filtros, $filas, $cuentas);
        $porComprobante = $this->conciliarPorComprobanteVinculado($empresaId, $filtros, $filas, $cuentas);

        return [
            'habilitada' => true,
            'cuentas' => $cuentas,
            'resumen_empresa' => $resumen,
            'auditoria_diaria' => $auditoriaDiaria,
            'por_comprobante_vinculado' => $porComprobante,
            'notas' => $this->notas($filas, $porComprobante, $contable),
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
            'auditoria_diaria' => ['habilitada' => false, 'dias' => [], 'stats' => []],
            'por_comprobante_vinculado' => ['habilitada' => false, 'comprobantes' => [], 'stats' => []],
            'notas' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{neto: float, iva: float, perc_iva: float, perc_iibb: float, total: float, comprobantes: int}
     */
    private function desgloseErp(array $filas): array
    {
        $neto = 0.0;
        $iva = 0.0;
        $percIva = 0.0;
        $percIibb = 0.0;
        $total = 0.0;

        foreach ($filas as $fila) {
            $rubros = $fila['rubros'] ?? IvaComprasDesgloseSupport::rubrosVacios();
            $neto += (float) ($rubros['neto'] ?? 0);
            $iva += (float) ($rubros['iva'] ?? 0);
            $percIva += (float) ($rubros['perc_iva'] ?? 0);
            $percIibb += (float) ($rubros['perc_iibb'] ?? 0);
            $total += (float) (($fila['columnas']['total'] ?? 0));
        }

        return [
            'neto' => round($neto, 2),
            'iva' => round($iva, 2),
            'perc_iva' => round($percIva, 2),
            'perc_iibb' => round($percIibb, 2),
            'total' => round($total, 2),
            'comprobantes' => count($filas),
        ];
    }

    /**
     * @param  array<string, float|int>  $erp
     * @param  array<string, float>  $contable
     * @return array<string, mixed>
     */
    private function armarResumen(array $erp, array $contable): array
    {
        $lineas = [];
        $defs = [
            ['key' => 'iva', 'concepto' => 'IVA crédito fiscal', 'ctb' => 'iva_credito'],
            ['key' => 'perc_iva', 'concepto' => 'Percepciones IVA', 'ctb' => 'perc_iva'],
            ['key' => 'perc_iibb', 'concepto' => 'Percepciones / retenciones IIBB', 'ctb' => 'perc_iibb'],
        ];

        $cuadraGlobal = true;
        foreach ($defs as $def) {
            $erpVal = (float) ($erp[$def['key']] ?? 0);
            $ctbVal = (float) ($contable[$def['ctb']] ?? 0);
            $dif = round($erpVal - $ctbVal, 2);
            $tieneMov = abs($erpVal) > 0.009 || abs($ctbVal) > 0.009;
            $cuadra = ! $tieneMov || IvaComprasConciliacionCuentaSupport::cuadra($erpVal, $ctbVal);
            if ($tieneMov && ! $cuadra) {
                $cuadraGlobal = false;
            }
            $lineas[] = [
                'concepto' => $def['concepto'],
                'erp' => $erpVal,
                'contable' => $ctbVal,
                'diferencia' => $dif,
                'cuadra' => $cuadra,
                'tiene_movimiento' => $tieneMov,
            ];
        }

        // Informativo: neto del libro (no se confronta con mayor de gastos).
        $lineas[] = [
            'concepto' => 'Neto / no gravado / exento (solo libro)',
            'erp' => (float) ($erp['neto'] ?? 0),
            'contable' => null,
            'diferencia' => null,
            'cuadra' => true,
            'tiene_movimiento' => abs((float) ($erp['neto'] ?? 0)) > 0.009,
            'solo_libro' => true,
        ];

        return [
            'lineas' => $lineas,
            'cuadra_global' => $cuadraGlobal,
            'erp_comprobantes' => (int) ($erp['comprobantes'] ?? 0),
            'erp_total' => (float) ($erp['total'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<int>
     */
    private function asientoIdsDeFilas(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['asiento_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<string, mixed>  $cuentas
     * @param  list<int>  $asientoIds
     * @return array{iva_credito: float, perc_iva: float, perc_iibb: float}
     */
    private function totalesContablesAsientos(int $empresaId, array $filtros, array $cuentas, array $asientoIds): array
    {
        $vacio = [
            'iva_credito' => 0.0,
            'perc_iva' => 0.0,
            'perc_iibb' => 0.0,
        ];
        if ($asientoIds === []) {
            return $vacio;
        }

        $idsTodos = array_values(array_unique(array_merge(
            $cuentas['iva_credito'] ?? [],
            $cuentas['perc_iva'] ?? [],
            $cuentas['perc_iibb'] ?? [],
        )));
        if ($idsTodos === []) {
            return $vacio;
        }

        $rows = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('a.id', $asientoIds)
            ->whereIn('cc.id', $idsTodos)
            ->selectRaw('cc.id as cuenta_id, SUM(am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
            ->groupBy('cc.id')
            ->get();

        $out = $vacio;
        foreach ($rows as $row) {
            $importe = round((float) ($row->importe ?? 0), 2);
            $cuentaId = (int) ($row->cuenta_id ?? 0);
            if (in_array($cuentaId, $cuentas['iva_credito'] ?? [], true)) {
                $out['iva_credito'] = round($out['iva_credito'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['perc_iva'] ?? [], true)) {
                $out['perc_iva'] = round($out['perc_iva'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['perc_iibb'] ?? [], true)) {
                $out['perc_iibb'] = round($out['perc_iibb'] + $importe, 2);
            }
        }

        return $out;
    }

    /**
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
        $asientosPorDia = [];
        foreach ($filas as $fila) {
            $dia = (string) ($fila['fecha_orden'] ?? '');
            if ($dia === '') {
                continue;
            }
            if (! isset($erpPorDia[$dia])) {
                $erpPorDia[$dia] = [
                    'comprobantes' => 0,
                    'iva' => 0.0,
                    'perc_iva' => 0.0,
                    'perc_iibb' => 0.0,
                    'total' => 0.0,
                ];
            }
            $rubros = $fila['rubros'] ?? [];
            $erpPorDia[$dia]['comprobantes']++;
            $erpPorDia[$dia]['iva'] = round($erpPorDia[$dia]['iva'] + (float) ($rubros['iva'] ?? 0), 2);
            $erpPorDia[$dia]['perc_iva'] = round($erpPorDia[$dia]['perc_iva'] + (float) ($rubros['perc_iva'] ?? 0), 2);
            $erpPorDia[$dia]['perc_iibb'] = round($erpPorDia[$dia]['perc_iibb'] + (float) ($rubros['perc_iibb'] ?? 0), 2);
            $erpPorDia[$dia]['total'] = round(
                $erpPorDia[$dia]['total'] + (float) (($fila['columnas']['total'] ?? 0)),
                2,
            );
            $aid = (int) ($fila['asiento_id'] ?? 0);
            if ($aid > 0) {
                $asientosPorDia[$dia][$aid] = $aid;
            }
        }

        $contablePorDia = $this->totalesContablesPorDiaAsientos($empresaId, $filtros, $cuentas, $asientosPorDia);
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
                'iva' => 0.0,
                'perc_iva' => 0.0,
                'perc_iibb' => 0.0,
                'total' => 0.0,
            ];
            $ctb = $contablePorDia[$dia] ?? [
                'iva_credito' => 0.0,
                'perc_iva' => 0.0,
                'perc_iibb' => 0.0,
            ];

            $difIva = round((float) $erp['iva'] - (float) ($ctb['iva_credito'] ?? 0), 2);
            $tol = IvaComprasConciliacionCuentaSupport::TOLERANCIA_DIARIA;
            $tieneMov = (int) $erp['comprobantes'] > 0 || abs((float) ($ctb['iva_credito'] ?? 0)) > 0.009;
            $cuadra = IvaComprasConciliacionCuentaSupport::cuadra(
                (float) $erp['iva'],
                (float) ($ctb['iva_credito'] ?? 0),
                $tol,
            )
                && IvaComprasConciliacionCuentaSupport::cuadra(
                    (float) $erp['perc_iva'],
                    (float) ($ctb['perc_iva'] ?? 0),
                    $tol,
                )
                && IvaComprasConciliacionCuentaSupport::cuadra(
                    (float) $erp['perc_iibb'],
                    (float) ($ctb['perc_iibb'] ?? 0),
                    $tol,
                );

            $stats['total_dias']++;
            if ($tieneMov) {
                $stats['dias_con_movimiento']++;
                if ($cuadra) {
                    $stats['dias_cuadran']++;
                } else {
                    $stats['dias_con_diferencia']++;
                }
            }

            $dias[] = [
                'fecha' => $dia,
                'fecha_fmt' => date('d/m/Y', $cursor),
                'erp' => [
                    'comprobantes' => (int) $erp['comprobantes'],
                    'neto' => 0.0,
                    'iva' => (float) $erp['iva'],
                    'total' => (float) $erp['total'],
                ],
                'contable' => [
                    'neto' => 0.0,
                    'iva' => (float) ($ctb['iva_credito'] ?? 0),
                ],
                'diferencias' => [
                    'neto' => 0.0,
                    'iva' => $difIva,
                ],
                'cuadra' => $cuadra,
                'tiene_movimiento' => $tieneMov,
            ];

            $cursor = strtotime('+1 day', $cursor);
        }

        return [
            'habilitada' => true,
            'tolerancia' => IvaComprasConciliacionCuentaSupport::TOLERANCIA_DIARIA,
            'dias' => $dias,
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $cuentas
     * @param  array<string, array<int, int>>  $asientosPorDia
     * @return array<string, array{iva_credito: float, perc_iva: float, perc_iibb: float}>
     */
    private function totalesContablesPorDiaAsientos(
        int $empresaId,
        array $filtros,
        array $cuentas,
        array $asientosPorDia,
    ): array {
        $todos = [];
        foreach ($asientosPorDia as $ids) {
            foreach ($ids as $id) {
                $todos[$id] = $id;
            }
        }
        if ($todos === []) {
            return [];
        }

        $idsCuentas = array_values(array_unique(array_merge(
            $cuentas['iva_credito'] ?? [],
            $cuentas['perc_iva'] ?? [],
            $cuentas['perc_iibb'] ?? [],
        )));
        if ($idsCuentas === []) {
            return [];
        }

        // Mapa asiento → día (fechaiva del libro).
        $diaPorAsiento = [];
        foreach ($asientosPorDia as $dia => $ids) {
            foreach ($ids as $aid) {
                $diaPorAsiento[$aid] = $dia;
            }
        }

        $rows = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('a.id', array_values($todos))
            ->whereIn('cc.id', $idsCuentas)
            ->selectRaw('a.id as asiento_id, cc.id as cuenta_id, SUM(am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
            ->groupBy('a.id', 'cc.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $aid = (int) ($row->asiento_id ?? 0);
            $dia = (string) ($diaPorAsiento[$aid] ?? '');
            if ($dia === '') {
                continue;
            }
            if (! isset($out[$dia])) {
                $out[$dia] = ['iva_credito' => 0.0, 'perc_iva' => 0.0, 'perc_iibb' => 0.0];
            }
            $importe = round((float) ($row->importe ?? 0), 2);
            $cuentaId = (int) ($row->cuenta_id ?? 0);
            if (in_array($cuentaId, $cuentas['iva_credito'] ?? [], true)) {
                $out[$dia]['iva_credito'] = round($out[$dia]['iva_credito'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['perc_iva'] ?? [], true)) {
                $out[$dia]['perc_iva'] = round($out[$dia]['perc_iva'] + $importe, 2);
            } elseif (in_array($cuentaId, $cuentas['perc_iibb'] ?? [], true)) {
                $out[$dia]['perc_iibb'] = round($out[$dia]['perc_iibb'] + $importe, 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cuentas
     * @return array<string, mixed>
     */
    private function conciliarPorComprobanteVinculado(
        int $empresaId,
        array $filtros,
        array $filas,
        array $cuentas,
    ): array {
        // Indexar por comprobante (no por asiento): si dos CP compartieran asiento no se pisan.
        $porComprobante = [];
        $sinAsiento = [];
        foreach ($filas as $fila) {
            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
            $rubros = $fila['rubros'] ?? IvaComprasDesgloseSupport::rubrosVacios();
            $base = [
                'comprobante_proveedor_id' => $cpId,
                'comprobante' => (string) ($fila['comprobante'] ?? ''),
                'proveedor_nombre' => (string) ($fila['proveedor_nombre'] ?? ''),
                'fecha_mov' => (string) ($fila['fecha_iva'] ?? $fila['fecha_mov'] ?? ''),
                'erp_iva' => (float) ($rubros['iva'] ?? 0),
                'erp_perc_iva' => (float) ($rubros['perc_iva'] ?? 0),
                'erp_perc_iibb' => (float) ($rubros['perc_iibb'] ?? 0),
                'asiento_id' => $asientoId,
            ];

            if ($asientoId <= 0) {
                $clave = $cpId > 0 ? 'cp_'.$cpId : 'sa_'.count($sinAsiento);
                $sinAsiento[$clave] = $base;
                continue;
            }

            $clave = $cpId > 0 ? 'cp_'.$cpId : 'as_'.$asientoId.'_'.count($porComprobante);
            $porComprobante[$clave] = $base;
        }

        $asientoIds = array_values(array_unique(array_map(
            static fn (array $r): int => (int) $r['asiento_id'],
            $porComprobante,
        )));

        $idsIva = array_values(array_unique(array_merge(
            $cuentas['iva_credito'] ?? [],
            $cuentas['perc_iva'] ?? [],
            $cuentas['perc_iibb'] ?? [],
        )));
        $ctbPorAsiento = [];

        if ($asientoIds !== [] && $idsIva !== []) {
            foreach (array_chunk($asientoIds, 2000) as $chunk) {
                $rows = DB::table('asiento as a')
                    ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
                    ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                    ->where('a.empresa_id', $empresaId)
                    ->whereIn('a.id', $chunk)
                    ->whereIn('cc.id', $idsIva)
                    ->selectRaw('a.id as asiento_id, cc.id as cuenta_id, SUM(am.monto * ('.$this->sqlCoeficienteMonedaAsiento($filtros).')) as importe')
                    ->groupBy('a.id', 'cc.id')
                    ->get();

                foreach ($rows as $row) {
                    $aid = (int) ($row->asiento_id ?? 0);
                    $cuentaId = (int) ($row->cuenta_id ?? 0);
                    $importe = round((float) ($row->importe ?? 0), 2);
                    if (! isset($ctbPorAsiento[$aid])) {
                        $ctbPorAsiento[$aid] = ['iva' => 0.0, 'perc_iva' => 0.0, 'perc_iibb' => 0.0];
                    }
                    if (in_array($cuentaId, $cuentas['iva_credito'] ?? [], true)) {
                        $ctbPorAsiento[$aid]['iva'] = round($ctbPorAsiento[$aid]['iva'] + $importe, 2);
                    } elseif (in_array($cuentaId, $cuentas['perc_iva'] ?? [], true)) {
                        $ctbPorAsiento[$aid]['perc_iva'] = round($ctbPorAsiento[$aid]['perc_iva'] + $importe, 2);
                    } elseif (in_array($cuentaId, $cuentas['perc_iibb'] ?? [], true)) {
                        $ctbPorAsiento[$aid]['perc_iibb'] = round($ctbPorAsiento[$aid]['perc_iibb'] + $importe, 2);
                    }
                }
            }
        }

        $lista = [];
        $cuadran = 0;
        $difVinculados = 0;

        foreach ($porComprobante as $erp) {
            $aid = (int) $erp['asiento_id'];
            $ctb = $ctbPorAsiento[$aid] ?? ['iva' => 0.0, 'perc_iva' => 0.0, 'perc_iibb' => 0.0];
            $difIva = round((float) $erp['erp_iva'] - (float) $ctb['iva'], 2);
            $difPercIva = round((float) $erp['erp_perc_iva'] - (float) $ctb['perc_iva'], 2);
            $difPercIibb = round((float) $erp['erp_perc_iibb'] - (float) $ctb['perc_iibb'], 2);
            $cuadra = IvaComprasConciliacionCuentaSupport::cuadra((float) $erp['erp_iva'], (float) $ctb['iva'])
                && IvaComprasConciliacionCuentaSupport::cuadra((float) $erp['erp_perc_iva'], (float) $ctb['perc_iva'])
                && IvaComprasConciliacionCuentaSupport::cuadra((float) $erp['erp_perc_iibb'], (float) $ctb['perc_iibb']);
            if ($cuadra) {
                $cuadran++;
                continue;
            }
            $difVinculados++;
            $lista[] = array_merge($erp, [
                'motivo' => 'diferencia_asiento',
                'contable_iva' => (float) $ctb['iva'],
                'contable_perc_iva' => (float) $ctb['perc_iva'],
                'contable_perc_iibb' => (float) $ctb['perc_iibb'],
                'diferencia_iva' => $difIva,
                'diferencia_perc_iva' => $difPercIva,
                'diferencia_perc_iibb' => $difPercIibb,
                'cuadra' => false,
            ]);
        }

        // Sin asiento: también son problemas del cuadre (explican días/resumen con diferencia).
        foreach ($sinAsiento as $erp) {
            $lista[] = array_merge($erp, [
                'motivo' => 'sin_asiento',
                'contable_iva' => null,
                'contable_perc_iva' => null,
                'contable_perc_iibb' => null,
                'diferencia_iva' => (float) $erp['erp_iva'],
                'diferencia_perc_iva' => (float) $erp['erp_perc_iva'],
                'diferencia_perc_iibb' => (float) $erp['erp_perc_iibb'],
                'cuadra' => false,
            ]);
        }

        usort($lista, static function (array $a, array $b): int {
            // Primero diferencias con asiento, luego sin asiento; dentro, mayor desvío.
            $prioA = (($a['motivo'] ?? '') === 'sin_asiento') ? 1 : 0;
            $prioB = (($b['motivo'] ?? '') === 'sin_asiento') ? 1 : 0;
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }
            $maxA = max(abs((float) ($a['diferencia_iva'] ?? 0)), abs((float) ($a['diferencia_perc_iva'] ?? 0)), abs((float) ($a['diferencia_perc_iibb'] ?? 0)));
            $maxB = max(abs((float) ($b['diferencia_iva'] ?? 0)), abs((float) ($b['diferencia_perc_iva'] ?? 0)), abs((float) ($b['diferencia_perc_iibb'] ?? 0)));

            return $maxB <=> $maxA;
        });

        $cantSinAsiento = count($sinAsiento);

        return [
            'habilitada' => true,
            'comprobantes' => $lista,
            'stats' => [
                'vinculados' => count($porComprobante),
                'sin_asiento' => $cantSinAsiento,
                'cuadran' => $cuadran,
                'con_diferencia' => $difVinculados,
                'a_revisar' => $difVinculados + $cantSinAsiento,
            ],
        ];
    }

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

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $porComprobante
     * @param  array<string, float>  $contable
     * @return list<string>
     */
    private function notas(array $filas, array $porComprobante, array $contable): array
    {
        $notas = [
            'El cuadre confronta IVA crédito y percepciones del libro (por tipoconcepto) contra el mayor de los asientos vinculados a esos comprobantes. El neto/exento no se confronta con cuentas de gasto.',
        ];
        $sinAsiento = (int) ($porComprobante['stats']['sin_asiento'] ?? 0);
        if ($sinAsiento > 0) {
            $notas[] = $sinAsiento.' comprobante(s) del libro sin asiento vinculado.';
        }
        $dif = (int) ($porComprobante['stats']['con_diferencia'] ?? 0);
        if ($dif > 0) {
            $notas[] = $dif.' comprobante(s) con diferencia IVA/percepciones vs asiento (revisar cuenta de imputación o cotización).';
        }
        $aRevisar = (int) ($porComprobante['stats']['a_revisar'] ?? 0);
        if ($aRevisar > 0 && $sinAsiento > 0) {
            $notas[] = 'Total a revisar en el detalle: '.$aRevisar.' (diferencias + sin asiento).';
        }
        if (count($filas) === 0) {
            $notas[] = 'Sin comprobantes en el período para conciliar.';
        }
        unset($contable);

        return $notas;
    }
}
