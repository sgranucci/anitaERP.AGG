<?php

namespace App\Support\Contable\Efe;

/**
 * Compara totales ERP vs Excel de referencia (solapa Resumen de pagos col B).
 */
class EfeComparacionExcelSupport
{
    /**
     * @param  list<array<string, mixed>>  $resumenPagos
     * @return array{
     *   referencia: string,
     *   filas: list<array<string, mixed>>,
     *   totales: array{coincidencias: int, desvios: int, umbral: float}
     * }
     */
    public function compararResumenPagos(array $resumenPagos, string $rutaReferencia): array
    {
        $referencia = $this->leerReferenciaResumenPagos($rutaReferencia);
        $erpPorConcepto = [];
        foreach ($resumenPagos as $fila) {
            $erpPorConcepto[(int) ($fila['concepto_id'] ?? -1)] = (float) ($fila['neto'] ?? 0);
        }

        $filas = [];
        $coincidencias = 0;
        $desvios = 0;
        $umbral = 1.0;

        $conceptos = array_unique(array_merge(array_keys($referencia), array_keys($erpPorConcepto)));
        sort($conceptos);

        foreach ($conceptos as $conceptoId) {
            $excel = (float) ($referencia[$conceptoId] ?? 0.0);
            $erp = (float) ($erpPorConcepto[$conceptoId] ?? 0.0);
            if (abs($excel) < 0.005 && abs($erp) < 0.005) {
                continue;
            }

            $diff = round($erp - $excel, 2);
            $ok = abs($diff) <= $umbral;
            if ($ok) {
                $coincidencias++;
            } else {
                $desvios++;
            }

            $filas[] = [
                'concepto_id' => $conceptoId,
                'excel' => $excel,
                'erp' => $erp,
                'diff' => $diff,
                'ok' => $ok,
            ];
        }

        usort($filas, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));

        return [
            'referencia' => $rutaReferencia,
            'filas' => $filas,
            'totales' => [
                'coincidencias' => $coincidencias,
                'desvios' => $desvios,
                'umbral' => $umbral,
            ],
        ];
    }

    /**
     * @return array<int, float>
     */
    public function leerReferenciaResumenPagos(string $ruta): array
    {
        if (str_ends_with(strtolower($ruta), '.json') && is_file($ruta)) {
            $payload = json_decode((string) file_get_contents($ruta), true);
            $mapa = [];
            foreach ($payload['resumen_pagos_col_b'] ?? [] as $id => $valor) {
                $mapa[(int) $id] = (float) $valor;
            }

            return $mapa;
        }

        return $this->leerColumnaBExcel($ruta);
    }

    /**
     * @return array<int, float>
     */
    public function leerColumnaBExcel(string $rutaExcel): array
    {
        if (! is_file($rutaExcel)) {
            return [];
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Resumen de pagos']);
        $sheet = $reader->load($rutaExcel)->getSheetByName('Resumen de pagos');
        if ($sheet === null) {
            return [];
        }

        $out = [];
        for ($fila = 3; $fila <= 120; $fila++) {
            $a = trim((string) $sheet->getCell('A'.$fila)->getValue());
            if (! str_starts_with($a, 'Concepto:')) {
                continue;
            }

            $partes = preg_split('/\s+/', $a);
            $conceptoId = isset($partes[1]) && ctype_digit($partes[1]) ? (int) $partes[1] : null;
            if ($conceptoId === null) {
                continue;
            }

            $valor = $sheet->getCell('B'.$fila)->getValue();
            $out[$conceptoId] = is_numeric($valor) ? (float) $valor : 0.0;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $sumariasErp
     * @return array{total_e68: float, excel_e68: float, diff: float}
     */
    public function compararSumariasTotal(array $sumariasErp, string $rutaReferencia): array
    {
        $totalErp = 0.0;
        foreach ($sumariasErp as $fila) {
            $totalErp += (float) ($fila['saldo_ajustado'] ?? 0);
        }
        $totalErp /= 1000;

        $excelE68 = 0.0;
        if (str_ends_with(strtolower($rutaReferencia), '.json') && is_file($rutaReferencia)) {
            $payload = json_decode((string) file_get_contents($rutaReferencia), true);
            $excelE68 = (float) ($payload['sumarias_e68'] ?? 0);
        } elseif (is_file($rutaReferencia)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(['Sumarias']);
            $sheet = $reader->load($rutaReferencia)->getSheetByName('Sumarias');
            if ($sheet !== null) {
                $v = $sheet->getCell('E68')->getValue();
                $excelE68 = is_numeric($v) ? (float) $v : 0.0;
            }
        }

        return [
            'total_e68' => round($totalErp, 2),
            'excel_e68' => round($excelE68, 2),
            'diff' => round($totalErp - $excelE68, 2),
        ];
    }

    /**
     * @return array{erp: ?float, excel: ?float, diff: ?float}
     */
    public function compararPosFinSaldoFinal(?float $saldoFinalErp, string $rutaReferencia): array
    {
        $excel = null;
        if (str_ends_with(strtolower($rutaReferencia), '.json') && is_file($rutaReferencia)) {
            $payload = json_decode((string) file_get_contents($rutaReferencia), true);
            $excel = isset($payload['posfin_saldo_final']) ? (float) $payload['posfin_saldo_final'] : null;
        } elseif (is_file($rutaReferencia)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(['pos fin Biy']);
            $sheet = $reader->load($rutaReferencia)->getSheetByName('pos fin Biy');
            if ($sheet !== null) {
                for ($fila = 200; $fila <= 230; $fila++) {
                    $a = trim((string) $sheet->getCell('A'.$fila)->getValue());
                    if (stripos($a, 'Saldo final') === 0) {
                        $v = $sheet->getCell('B'.$fila)->getValue();
                        $excel = is_numeric($v) ? (float) $v : null;
                        break;
                    }
                }
            }
        }

        if ($saldoFinalErp === null || $excel === null) {
            return ['erp' => $saldoFinalErp, 'excel' => $excel, 'diff' => null];
        }

        return [
            'erp' => round($saldoFinalErp, 2),
            'excel' => round($excel, 2),
            'diff' => round($saldoFinalErp - $excel, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $resumenPagos
     * @return array{concepto_id: int, pagos: float, cobros: float, neto: float, lineas: int}
     */
    public function resumenConcepto53(array $resumenPagos): array
    {
        foreach ($resumenPagos as $fila) {
            if ((int) ($fila['concepto_id'] ?? 0) === 53) {
                return [
                    'concepto_id' => 53,
                    'pagos' => (float) ($fila['pagos'] ?? 0),
                    'cobros' => (float) ($fila['cobros'] ?? 0),
                    'neto' => (float) ($fila['neto'] ?? 0),
                    'lineas' => (int) ($fila['cantidad_lineas'] ?? 0),
                ];
            }
        }

        return ['concepto_id' => 53, 'pagos' => 0.0, 'cobros' => 0.0, 'neto' => 0.0, 'lineas' => 0];
    }
}
