<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Compara el mayor por concepto del ERP contra export Anita y contra el mayor plano de disponibilidad.
 */
class MayorConceptoComparacionSupport
{
    public function __construct(
        private readonly MayorConceptoAuditoriaSupport $auditoriaSupport,
        private readonly MayorConceptoAnitaCsvReader $csvReader,
        private readonly MayorPlanoAnitaCsvReader $mayorPlanoCsvReader,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $lineasErp
     * @param  array<string, mixed>  $resultadoErp
     * @param  array<string, mixed>|null  $csvAnita
     * @return array<string, mixed>
     */
    public function comparar(
        array $lineasErp,
        array $resultadoErp,
        ?array $csvAnita,
        float $tolerancia = 0.05,
        ?array $mayorPlanoAnita = null,
    ): array {
        $auditoriaPlano = $this->auditoriaSupport->auditar($resultadoErp);
        $auditoriaAnalitico = $this->auditoriaSupport->auditarMayorPlanoAnalitico($resultadoErp);
        $auditoriaMayorPlanoAnita = $mayorPlanoAnita !== null
            ? $this->auditoriaSupport->auditarContraMayorPlanoAnita(
                $resultadoErp,
                $mayorPlanoAnita['totales_cuenta'] ?? [],
            )
            : null;

        $informe = [
            'parametros' => $resultadoErp['parametros'] ?? [],
            'resumen' => [
                'lineas_erp' => count($lineasErp),
                'lineas_anita' => $csvAnita ? count($csvAnita['lineas'] ?? []) : 0,
                'cuentas_mayor_plano_anita' => $mayorPlanoAnita ? count($mayorPlanoAnita['totales_cuenta'] ?? []) : 0,
                'tolerancia' => $tolerancia,
            ],
            'mayor_plano' => $this->enriquecerAuditoriaPlano($auditoriaPlano, $lineasErp, $csvAnita, $tolerancia),
            'mayor_plano_analitico' => $auditoriaAnalitico,
            'mayor_plano_anita' => $auditoriaMayorPlanoAnita,
            'erp_vs_anita' => null,
            'metadata_anita' => $csvAnita['metadata'] ?? null,
            'metadata_mayor_plano_anita' => $mayorPlanoAnita['metadata'] ?? null,
        ];

        if ($csvAnita !== null) {
            $informe['erp_vs_anita'] = [
                'lineas' => $this->compararLineas(
                    $lineasErp,
                    $csvAnita['lineas'] ?? [],
                    $tolerancia,
                ),
                'totales_cuenta' => $this->compararTotalesCuenta(
                    $resultadoErp,
                    $csvAnita['totales_cuenta'] ?? [],
                    $tolerancia,
                ),
                'totales_concepto' => $this->compararTotalesConcepto(
                    $resultadoErp,
                    $csvAnita['totales_concepto'] ?? [],
                    $tolerancia,
                ),
            ];
        }

        $informe['resumen']['cuadra_mayor_plano'] = (bool) ($informe['mayor_plano']['cuadra'] ?? false);
        $informe['resumen']['cuadra_mayor_plano_analitico'] = (bool) ($informe['mayor_plano_analitico']['cuadra'] ?? false);
        $informe['resumen']['cuentas_descuadradas_analitico'] = (int) ($informe['mayor_plano_analitico']['cuentas_descuadradas'] ?? 0);
        $informe['resumen']['cuadra_mayor_plano_anita'] = $auditoriaMayorPlanoAnita !== null
            ? (bool) ($auditoriaMayorPlanoAnita['cuadra'] ?? false)
            : null;
        $informe['resumen']['cuentas_descuadradas_mayor_plano_anita'] = $auditoriaMayorPlanoAnita !== null
            ? (int) ($auditoriaMayorPlanoAnita['cuentas_descuadradas'] ?? 0)
            : null;
        $informe['resumen']['requiere_alerta'] = ! ($informe['resumen']['cuadra_mayor_plano'] ?? true)
            || ! ($informe['resumen']['cuadra_mayor_plano_analitico'] ?? true)
            || ($auditoriaMayorPlanoAnita !== null && ! ($informe['resumen']['cuadra_mayor_plano_anita'] ?? true));

        if ($csvAnita !== null) {
            $diffLineas = $informe['erp_vs_anita']['lineas'] ?? [];
            $informe['resumen']['coincidencias_lineas'] = (int) ($diffLineas['coincidencias'] ?? 0);
            $informe['resumen']['solo_erp'] = count($diffLineas['solo_erp'] ?? []);
            $informe['resumen']['solo_anita'] = count($diffLineas['solo_anita'] ?? []);
            $informe['resumen']['cuadra_anita'] = ($informe['resumen']['solo_erp'] ?? 0) === 0
                && ($informe['resumen']['solo_anita'] ?? 0) === 0;
            $informe['resumen']['requiere_alerta'] = $informe['resumen']['requiere_alerta']
                || ! ($informe['resumen']['cuadra_anita'] ?? false);
        }

        return $informe;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leerCsvMayorPlanoAnita(?string $ruta): ?array
    {
        if ($ruta === null || trim($ruta) === '') {
            return null;
        }

        return $this->mayorPlanoCsvReader->leer(trim($ruta), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leerCsvAnita(?string $ruta): ?array
    {
        if ($ruta === null || trim($ruta) === '') {
            return null;
        }

        return $this->csvReader->leer(trim($ruta));
    }

    /**
     * @param  list<array<string, mixed>>  $lineasErp
     * @param  list<array<string, mixed>>  $lineasAnita
     * @return array<string, mixed>
     */
    private function compararLineas(array $lineasErp, array $lineasAnita, float $tolerancia): array
    {
        $erpPorClave = $this->agruparPorClave($lineasErp);
        $anitaPorClave = $this->agruparPorClave($lineasAnita);

        $todasClaves = array_unique(array_merge(array_keys($erpPorClave), array_keys($anitaPorClave)));
        sort($todasClaves);

        $coincidencias = 0;
        $soloErp = [];
        $soloAnita = [];

        foreach ($todasClaves as $clave) {
            $cntErp = $erpPorClave[$clave]['cantidad'] ?? 0;
            $cntAnita = $anitaPorClave[$clave]['cantidad'] ?? 0;
            $match = min($cntErp, $cntAnita);
            $coincidencias += $match;

            if ($cntErp > $cntAnita) {
                foreach (array_slice($erpPorClave[$clave]['ejemplos'], $cntAnita) as $ln) {
                    $soloErp[] = $this->filaDiff('solo_erp', $clave, $ln, null, $tolerancia);
                }
            }

            if ($cntAnita > $cntErp) {
                foreach (array_slice($anitaPorClave[$clave]['ejemplos'], $cntErp) as $ln) {
                    $soloAnita[] = $this->filaDiff('solo_anita', $clave, null, $ln, $tolerancia);
                }
            }
        }

        return [
            'coincidencias' => $coincidencias,
            'solo_erp' => $soloErp,
            'solo_anita' => $soloAnita,
            'clave_descripcion' => 'concepto_id|cuenta|nro_asiento|debe|haber',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, array{cantidad: int, ejemplos: list<array<string, mixed>>}>
     */
    private function agruparPorClave(array $lineas): array
    {
        $grupos = [];

        foreach ($lineas as $ln) {
            $clave = $this->claveLinea($ln);
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = ['cantidad' => 0, 'ejemplos' => []];
            }
            $grupos[$clave]['cantidad']++;
            $grupos[$clave]['ejemplos'][] = $ln;
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    public function claveLinea(array $ln): string
    {
        return implode('|', [
            (int) ($ln['concepto_id'] ?? 0),
            (int) ($ln['cuenta'] ?? 0),
            (int) ($ln['nro_asiento'] ?? 0),
            number_format((float) ($ln['debe'] ?? 0), 2, '.', ''),
            number_format((float) ($ln['haber'] ?? 0), 2, '.', ''),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $erp
     * @param  array<string, mixed>|null  $anita
     * @return array<string, mixed>
     */
    private function filaDiff(string $tipo, string $clave, ?array $erp, ?array $anita, float $tolerancia): array
    {
        $base = $erp ?? $anita ?? [];

        return [
            'tipo' => $tipo,
            'clave' => $clave,
            'concepto_id' => (int) ($base['concepto_id'] ?? 0),
            'concepto_nombre' => (string) ($base['concepto_nombre'] ?? ''),
            'cuenta' => (int) ($base['cuenta'] ?? 0),
            'cuenta_codigo' => (string) ($base['cuenta_codigo'] ?? ''),
            'nro_asiento' => (int) ($base['nro_asiento'] ?? 0),
            'fecha_fmt' => (string) ($base['fecha_fmt'] ?? ''),
            'tipo_comp' => (string) ($base['tipo_comp'] ?? ''),
            'comprobante' => (string) ($base['comprobante'] ?? ''),
            'debe_erp' => (float) ($erp['debe'] ?? 0),
            'haber_erp' => (float) ($erp['haber'] ?? 0),
            'debe_anita' => (float) ($anita['debe'] ?? 0),
            'haber_anita' => (float) ($anita['haber'] ?? 0),
            'descripcion' => (string) ($base['descripcion'] ?? ''),
            'fila_csv' => (int) ($anita['fila_csv'] ?? 0),
            'tolerancia' => $tolerancia,
        ];
    }

    /**
     * @param  array<string, mixed>  $resultadoErp
     * @param  list<array<string, mixed>>  $totalesAnita
     * @return list<array<string, mixed>>
     */
    private function compararTotalesCuenta(array $resultadoErp, array $totalesAnita, float $tolerancia): array
    {
        $erpTotales = [];
        foreach ($resultadoErp['secciones'] ?? [] as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $cuenta = (int) ($cuentaBlock['cuenta'] ?? 0);
                $clave = $conceptoId.'|'.$cuenta;
                $erpTotales[$clave] = [
                    'concepto_id' => $conceptoId,
                    'concepto_nombre' => (string) ($seccion['concepto_nombre'] ?? ''),
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => (string) ($cuentaBlock['cuenta_codigo'] ?? ''),
                    'debe' => (float) ($cuentaBlock['total_debe'] ?? 0),
                    'haber' => (float) ($cuentaBlock['total_haber'] ?? 0),
                ];
            }
        }

        $anitaTotales = [];
        foreach ($totalesAnita as $tot) {
            $clave = ((int) ($tot['concepto_id'] ?? 0)).'|'.((int) ($tot['cuenta'] ?? 0));
            $anitaTotales[$clave] = $tot;
        }

        return $this->compararMapaTotales($erpTotales, $anitaTotales, 'cuenta', $tolerancia);
    }

    /**
     * @param  array<string, mixed>  $resultadoErp
     * @param  list<array<string, mixed>>  $totalesAnita
     * @return list<array<string, mixed>>
     */
    private function compararTotalesConcepto(array $resultadoErp, array $totalesAnita, float $tolerancia): array
    {
        $erpTotales = [];
        foreach ($resultadoErp['secciones'] ?? [] as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $debe = 0.0;
            $haber = 0.0;
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $debe += (float) ($cuentaBlock['total_debe'] ?? 0);
                $haber += (float) ($cuentaBlock['total_haber'] ?? 0);
            }
            $erpTotales[(string) $conceptoId] = [
                'concepto_id' => $conceptoId,
                'concepto_nombre' => (string) ($seccion['concepto_nombre'] ?? ''),
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
            ];
        }

        $anitaTotales = [];
        foreach ($totalesAnita as $tot) {
            $anitaTotales[(string) ((int) ($tot['concepto_id'] ?? 0))] = $tot;
        }

        return $this->compararMapaTotales($erpTotales, $anitaTotales, 'concepto', $tolerancia);
    }

    /**
     * @param  array<string, array<string, mixed>>  $erp
     * @param  array<string, array<string, mixed>>  $anita
     * @return list<array<string, mixed>>
     */
    private function compararMapaTotales(array $erp, array $anita, string $nivel, float $tolerancia): array
    {
        $claves = array_unique(array_merge(array_keys($erp), array_keys($anita)));
        sort($claves);

        $diffs = [];
        foreach ($claves as $clave) {
            $e = $erp[$clave] ?? ['debe' => 0.0, 'haber' => 0.0];
            $a = $anita[$clave] ?? ['debe' => 0.0, 'haber' => 0.0];

            $dDebe = round((float) ($e['debe'] ?? 0) - (float) ($a['debe'] ?? 0), 2);
            $dHaber = round((float) ($e['haber'] ?? 0) - (float) ($a['haber'] ?? 0), 2);

            if (abs($dDebe) < $tolerancia && abs($dHaber) < $tolerancia) {
                continue;
            }

            $diffs[] = [
                'nivel' => $nivel,
                'clave' => $clave,
                'concepto_id' => (int) ($e['concepto_id'] ?? $a['concepto_id'] ?? 0),
                'concepto_nombre' => (string) ($e['concepto_nombre'] ?? $a['concepto_nombre'] ?? ''),
                'cuenta' => (int) ($e['cuenta'] ?? $a['cuenta'] ?? 0),
                'cuenta_codigo' => (string) ($e['cuenta_codigo'] ?? $a['cuenta_codigo'] ?? ''),
                'debe_erp' => (float) ($e['debe'] ?? 0),
                'haber_erp' => (float) ($e['haber'] ?? 0),
                'debe_anita' => (float) ($a['debe'] ?? 0),
                'haber_anita' => (float) ($a['haber'] ?? 0),
                'diff_debe' => $dDebe,
                'diff_haber' => $dHaber,
            ];
        }

        return $diffs;
    }

    /**
     * Cruza mayor plano vs imputado y, si hay CSV Anita, vs totales Anita por cuenta disp.
     *
     * @param  list<array<string, mixed>>  $lineasErp
     * @param  array<string, mixed>|null  $csvAnita
     * @return array<string, mixed>
     */
    private function enriquecerAuditoriaPlano(
        array $auditoria,
        array $lineasErp,
        ?array $csvAnita,
        float $tolerancia,
    ): array {
        $filas = $auditoria['filas'] ?? [];

        $anitaPorDisp = [];
        if ($csvAnita !== null) {
            foreach ($csvAnita['lineas'] ?? [] as $ln) {
                $cuenta = (int) ($ln['cuenta'] ?? 0);
                if ($cuenta <= 0 || $cuenta > MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                    continue;
                }
                if (! isset($anitaPorDisp[$cuenta])) {
                    $anitaPorDisp[$cuenta] = ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
                }
                $anitaPorDisp[$cuenta]['debe'] += (float) ($ln['debe'] ?? 0);
                $anitaPorDisp[$cuenta]['haber'] += (float) ($ln['haber'] ?? 0);
                $anitaPorDisp[$cuenta]['lineas']++;
            }
        }

        foreach ($filas as $idx => $fila) {
            $cuenta = (int) ($fila['cuenta'] ?? 0);
            $anita = $anitaPorDisp[$cuenta] ?? null;
            if ($anita !== null) {
                $filas[$idx]['anita_debe'] = round((float) $anita['debe'], 2);
                $filas[$idx]['anita_haber'] = round((float) $anita['haber'], 2);
                $filas[$idx]['anita_lineas'] = (int) $anita['lineas'];
                $filas[$idx]['diff_anita_plano_debe'] = round(
                    (float) ($fila['plano_debe'] ?? 0) - (float) $anita['debe'],
                    2,
                );
                $filas[$idx]['diff_anita_plano_haber'] = round(
                    (float) ($fila['plano_haber'] ?? 0) - (float) $anita['haber'],
                    2,
                );
            }
        }

        $auditoria['filas'] = $filas;
        $auditoria['nota'] = 'Plano = subdiario+ctamov de la cuenta caja/banco. Imput. = mayor por concepto totalizado por esa cuenta (todos los conceptos), con el movimiento real del banco (disp_debe/disp_haber).';

        return $auditoria;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return array{resumen: string, lineas: string, plano: string}
     */
    public function exportarArchivos(array $informe, string $directorio, string $prefijo): array
    {
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $resumenPath = $directorio.'/'.$prefijo.'_resumen.md';
        $lineasPath = $directorio.'/'.$prefijo.'_lineas.csv';
        $planoPath = $directorio.'/'.$prefijo.'_mayor_plano.csv';

        file_put_contents($resumenPath, $this->renderResumenMarkdown($informe));
        file_put_contents($lineasPath, $this->renderDiffLineasCsv($informe));
        file_put_contents($planoPath, $this->renderMayorPlanoCsv($informe));

        return [
            'resumen' => $resumenPath,
            'lineas' => $lineasPath,
            'plano' => $planoPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function renderResumenMarkdown(array $informe): string
    {
        $p = $informe['parametros'] ?? [];
        $r = $informe['resumen'] ?? [];
        $meta = $informe['metadata_anita'] ?? [];

        $lines = [
            '# Comparación Mayor por Concepto',
            '',
            '## Parámetros ERP',
            '- Empresa: '.($p['empresa_id'] ?? ''),
            '- Período: '.($p['fecha_desde'] ?? '').' — '.($p['fecha_hasta'] ?? ''),
            '- Moneda reporte: '.($p['moneda_abreviatura'] ?? $p['moneda_reporte_id'] ?? ''),
            '- Tolerancia: '.($r['tolerancia'] ?? ''),
            '',
        ];

        if ($meta) {
            $lines[] = '## CSV Anita';
            $lines[] = '- Ruta: '.($meta['ruta'] ?? '');
            $lines[] = '- Período Anita: '.($meta['periodo'] ?? '');
            $lines[] = '- Empresas: '.($meta['empresas'] ?? '');
            $lines[] = '';
        }

        $lines[] = '## Resumen';
        $lines[] = '| Métrica | Valor |';
        $lines[] = '|---|---|';
        foreach ([
            'lineas_erp' => 'Líneas ERP',
            'lineas_anita' => 'Líneas Anita',
            'coincidencias_lineas' => 'Coincidencias línea a línea',
            'solo_erp' => 'Solo en ERP',
            'solo_anita' => 'Solo en Anita',
            'cuadra_anita' => 'Cuadra vs Anita',
            'cuadra_mayor_plano' => 'Cuadra vs mayor plano (bridge)',
            'cuadra_mayor_plano_anita' => 'Cuadra vs l_mayor Anita',
            'cuentas_descuadradas_mayor_plano_anita' => 'Cuentas Δ l_mayor',
            'requiere_alerta' => 'Requiere alerta',
        ] as $k => $label) {
            if (array_key_exists($k, $r)) {
                $v = $r[$k];
                if (is_bool($v)) {
                    $v = $v ? 'sí' : 'no';
                }
                $lines[] = '| '.$label.' | '.$v.' |';
            }
        }

        $plano = $informe['mayor_plano'] ?? [];
        $lines[] = '';
        $lines[] = '## Mayor plano (bridge subdiario+ctamov)';
        $lines[] = '- Cuadra imputado: '.(! empty($plano['cuadra']) ? 'sí' : 'no');
        $lines[] = '- Δ Debe global: '.($plano['diferencia_debe'] ?? '');
        $lines[] = '- Δ Haber global: '.($plano['diferencia_haber'] ?? '');

        $planoAnita = $informe['mayor_plano_anita'] ?? null;
        if (is_array($planoAnita)) {
            $metaPlano = $informe['metadata_mayor_plano_anita'] ?? [];
            $lines[] = '';
            $lines[] = '## Objetivo: imputado vs l_mayor Anita (disponibilidades)';
            $lines[] = '- CSV: '.($metaPlano['ruta'] ?? '');
            $lines[] = '- Cuadra: '.(! empty($planoAnita['cuadra']) ? 'sí' : 'no');
            $lines[] = '- Cuentas descuadradas: '.($planoAnita['cuentas_descuadradas'] ?? 0);
            $lines[] = '- '.($planoAnita['nota'] ?? '');
            $diffsAnita = array_filter($planoAnita['filas'] ?? [], fn ($f) => empty($f['cuadra']));
            foreach (array_slice(array_values($diffsAnita), 0, 20) as $f) {
                $lines[] = sprintf(
                    '- %s: Anita D=%.2f H=%.2f | Imput. D=%.2f H=%.2f | Bridge D=%.2f H=%.2f',
                    $f['cuenta_codigo'] ?? '',
                    $f['anita_debe'] ?? 0,
                    $f['anita_haber'] ?? 0,
                    $f['imputado_debe'] ?? 0,
                    $f['imputado_haber'] ?? 0,
                    $f['plano_bridge_debe'] ?? 0,
                    $f['plano_bridge_haber'] ?? 0,
                );
            }
        }

        $lines[] = '';
        $lines[] = '- '.($plano['nota'] ?? '');

        $diffsPlano = array_filter($plano['filas'] ?? [], fn ($f) => empty($f['cuadra']));
        if ($diffsPlano !== []) {
            $lines[] = '';
            $lines[] = '### Cuentas con diferencia plano vs imputado';
            foreach (array_slice($diffsPlano, 0, 30) as $f) {
                $lines[] = sprintf(
                    '- %s %s: plano D=%.2f H=%.2f | imputado D=%.2f H=%.2f',
                    $f['cuenta_codigo'] ?? '',
                    $f['cuenta_nombre'] ?? '',
                    $f['plano_debe'] ?? 0,
                    $f['plano_haber'] ?? 0,
                    $f['imputado_debe'] ?? 0,
                    $f['imputado_haber'] ?? 0,
                );
            }
        }

        $erpVsAnita = $informe['erp_vs_anita'] ?? null;
        if ($erpVsAnita !== null) {
            $totCuenta = $erpVsAnita['totales_cuenta'] ?? [];
            $totConcepto = $erpVsAnita['totales_concepto'] ?? [];
            if ($totCuenta !== [] || $totConcepto !== []) {
                $lines[] = '';
                $lines[] = '## Diferencias de totales Anita';
                $lines[] = '- Totales cuenta distintos: '.count($totCuenta);
                $lines[] = '- Totales concepto distintos: '.count($totConcepto);
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function renderDiffLineasCsv(array $informe): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, [
            'tipo', 'clave', 'concepto_id', 'concepto_nombre', 'cuenta_codigo', 'nro_asiento',
            'fecha', 'tipo_comp', 'comprobante', 'debe_erp', 'haber_erp', 'debe_anita', 'haber_anita',
            'descripcion', 'fila_csv',
        ]);

        $diff = $informe['erp_vs_anita']['lineas'] ?? null;
        if ($diff !== null) {
            foreach (array_merge($diff['solo_erp'] ?? [], $diff['solo_anita'] ?? []) as $fila) {
                fputcsv($out, [
                    $fila['tipo'] ?? '',
                    $fila['clave'] ?? '',
                    $fila['concepto_id'] ?? '',
                    $fila['concepto_nombre'] ?? '',
                    $fila['cuenta_codigo'] ?? '',
                    $fila['nro_asiento'] ?? '',
                    $fila['fecha_fmt'] ?? '',
                    $fila['tipo_comp'] ?? '',
                    $fila['comprobante'] ?? '',
                    $fila['debe_erp'] ?? '',
                    $fila['haber_erp'] ?? '',
                    $fila['debe_anita'] ?? '',
                    $fila['haber_anita'] ?? '',
                    $fila['descripcion'] ?? '',
                    $fila['fila_csv'] ?? '',
                ]);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function renderMayorPlanoCsv(array $informe): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, [
            'cuenta_codigo', 'cuenta_nombre', 'plano_debe', 'plano_haber',
            'imputado_debe', 'imputado_haber', 'diferencia_debe', 'diferencia_haber',
            'lineas_imputadas', 'anita_debe', 'anita_haber', 'diff_anita_plano_debe', 'diff_anita_plano_haber', 'cuadra',
        ]);

        foreach ($informe['mayor_plano']['filas'] ?? [] as $f) {
            fputcsv($out, [
                $f['cuenta_codigo'] ?? '',
                $f['cuenta_nombre'] ?? '',
                $f['plano_debe'] ?? '',
                $f['plano_haber'] ?? '',
                $f['imputado_debe'] ?? '',
                $f['imputado_haber'] ?? '',
                $f['diferencia_debe'] ?? '',
                $f['diferencia_haber'] ?? '',
                $f['lineas_imputadas'] ?? '',
                $f['anita_debe'] ?? '',
                $f['anita_haber'] ?? '',
                $f['diff_anita_plano_debe'] ?? '',
                $f['diff_anita_plano_haber'] ?? '',
                ! empty($f['cuadra']) ? '1' : '0',
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }
}
