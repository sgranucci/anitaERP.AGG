<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Tesoreria\PosicionBancariaCheque;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera hojas de cheques livianas desde stock ERP y enlaza Disponible HOY en Resumen.
 */
final class PosicionBancariaChequePlantillaExportSupport
{
    /** @var array<int, string> */
    private const EMPRESA_HOJA = [
        1 => 'Cheques BSA',
        2 => 'Cheques KSA',
        3 => 'Cheques RSA',
    ];

    /** @var list<string> */
    private const BANCOS = [
        PosicionBancariaChequeAgingSupport::BANCO_MACRO,
        PosicionBancariaChequeAgingSupport::BANCO_ITAU,
        PosicionBancariaChequeAgingSupport::BANCO_BIND,
    ];

    /**
     * @return array{hojas:int,filas:int}
     */
    public function volcarEnSpreadsheet(Spreadsheet $wb, Carbon $fechaPosicion): array
    {
        $aging = new PosicionBancariaChequeAgingSupport();
        $stats = ['hojas' => 0, 'filas' => 0];

        // Precalcular aging por empresa (activos diferidos/blank).
        /** @var array<int, array{retenidos:array{count:int,importe:float},transito:array{count:int,importe:float},diferidos:array{count:int,importe:float},por_banco:array<string,array{retenidos:float,transito:float,diferidos:float,total:float}>}> $agingPorEmp */
        $agingPorEmp = [];
        foreach ([1, 2, 3] as $empresaId) {
            $agingPorEmp[$empresaId] = $aging->resumir($fechaPosicion, null, $empresaId, true);
        }

        foreach (self::EMPRESA_HOJA as $empresaId => $nombreHoja) {
            if ($wb->sheetNameExists($nombreHoja)) {
                $idx = $wb->getIndex($wb->getSheetByName($nombreHoja));
                $wb->removeSheetByIndex($idx);
            }
            $ws = $wb->createSheet();
            $ws->setTitle($nombreHoja);

            $ws->setCellValue('A1', 'Posición de cheques (ERP) al');
            $ws->setCellValue('E1', $fechaPosicion->format('Y-m-d'));
            $ws->getStyle('A1')->getFont()->setBold(true);

            $resumen = $agingPorEmp[$empresaId];
            // Totales empresa (todas las bancas canónicas)
            $ws->setCellValue('A3', 'Retenidos');
            $ws->setCellValue('E3', $resumen['retenidos']['importe']);
            $ws->setCellValue('A4', 'Tránsito');
            $ws->setCellValue('E4', $resumen['transito']['importe']);
            $ws->setCellValue('A5', 'Diferidos');
            $ws->setCellValue('E5', $resumen['diferidos']['importe']);
            $ws->setCellValue('A6', 'Total');
            $ws->setCellValue('E6', '=E3+E4+E5');
            $ws->getStyle('A6')->getFont()->setBold(true);
            foreach (['E3', 'E4', 'E5', 'E6'] as $cell) {
                $ws->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            // Por banco (celdas estables para Resumen)
            $ws->setCellValue('J2', 'Banco');
            $ws->setCellValue('K2', 'Retenidos');
            $ws->setCellValue('L2', 'Transito');
            $ws->setCellValue('M2', 'Diferidos');
            $ws->getStyle('J2:M2')->getFont()->setBold(true);
            $filaBanco = 3;
            foreach (self::BANCOS as $banco) {
                $vals = $resumen['por_banco'][$banco] ?? [
                    'retenidos' => 0.0, 'transito' => 0.0, 'diferidos' => 0.0, 'total' => 0.0,
                ];
                $ws->setCellValue("J{$filaBanco}", $banco);
                $ws->setCellValue("K{$filaBanco}", $vals['retenidos']);
                $ws->setCellValue("L{$filaBanco}", $vals['transito']);
                $ws->setCellValue("M{$filaBanco}", $vals['diferidos']);
                foreach (["K{$filaBanco}", "L{$filaBanco}", "M{$filaBanco}"] as $cell) {
                    $ws->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $filaBanco++;
            }
            // J3=MACRO K3/L3, J4=ITAU, J5=BIND

            $headers = ['Tip', 'Numero', 'Emision', 'Vencimiento', 'Detalle', 'Banco', 'Importe', 'Bucket', 'Estado', 'CuentacajaId'];
            foreach ($headers as $i => $h) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $ws->setCellValue($col.'8', $h);
                $ws->getStyle($col.'8')->getFont()->setBold(true);
            }

            $cheques = PosicionBancariaCheque::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('en_portfolio_posicion', true)
                ->where(function ($w) {
                    $w->whereNull('estado')->orWhere('estado', '')->orWhere('estado', ' ');
                })
                ->whereIn('banco_canonico', self::BANCOS)
                ->orderBy('banco_canonico')
                ->orderBy('fecha_cheque')
                ->get();

            $corteRet = $fechaPosicion->copy()->subDays(30)->startOfDay();
            $corteHoy = $fechaPosicion->copy()->startOfDay();
            $row = 9;
            foreach ($cheques as $ch) {
                $venc = $ch->fecha_cheque ? Carbon::parse($ch->fecha_cheque)->startOfDay() : null;
                if ($venc === null) {
                    continue;
                }
                if ($venc->lte($corteRet)) {
                    $bucket = 'retenidos';
                } elseif ($venc->lte($corteHoy)) {
                    $bucket = 'transito';
                } else {
                    $bucket = 'diferidos';
                }
                $ws->setCellValue("A{$row}", $ch->tip ?: 'CHP');
                $ws->setCellValue("B{$row}", $ch->numero_cheque);
                $ws->setCellValue("C{$row}", $ch->fecha_emision?->format('Y-m-d'));
                $ws->setCellValue("D{$row}", $ch->fecha_cheque?->format('Y-m-d'));
                $ws->setCellValue("E{$row}", $ch->entregado_a);
                $ws->setCellValue("F{$row}", $ch->banco_canonico);
                $ws->setCellValue("G{$row}", (float) $ch->importe);
                $ws->setCellValue("H{$row}", $bucket);
                $ws->setCellValue("I{$row}", $ch->estado);
                $ws->setCellValue("J{$row}", $ch->cuentacaja_id);
                $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
                $stats['filas']++;
            }
            $stats['hojas']++;
        }

        $this->escribirResumenCheques($wb, $fechaPosicion, $agingPorEmp);
        if ($wb->sheetNameExists('Resumen')) {
            $this->escribirDisponibleHoy($wb->getSheetByName('Resumen'), $fechaPosicion);
        }

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $agingPorEmp
     */
    private function escribirResumenCheques(Spreadsheet $wb, Carbon $fechaPosicion, array $agingPorEmp): void
    {
        if ($wb->sheetNameExists('ResumenCheques')) {
            $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName('ResumenCheques')));
        }
        $wr = $wb->createSheet();
        $wr->setTitle('ResumenCheques');

        $wr->setCellValue('A1', 'Resumen cheques ERP');
        $wr->setCellValue('B1', $fechaPosicion->format('Y-m-d'));
        $wr->getStyle('A1')->getFont()->setBold(true);

        // Matriz fija:
        //   B     C     D      E     F     G      H     I     J
        //   BIY Ret/Tr/Dif   KAN Ret/Tr/Dif   REB Ret/Tr/Dif
        // MACRO row 4, ITAU row 5, BIND row 6
        $wr->setCellValue('B3', 'Biyemas');
        $wr->mergeCells('B3:D3');
        $wr->setCellValue('E3', 'Kandiko');
        $wr->mergeCells('E3:G3');
        $wr->setCellValue('H3', 'Rebisco');
        $wr->mergeCells('H3:J3');
        $wr->setCellValue('A4', '');
        $wr->fromArray(['Banco', 'Ret', 'Tr', 'Dif', 'Ret', 'Tr', 'Dif', 'Ret', 'Tr', 'Dif'], null, 'A4');
        $wr->getStyle('A3:J4')->getFont()->setBold(true);

        $fila = 5;
        foreach (self::BANCOS as $banco) {
            $wr->setCellValue("A{$fila}", $banco);
            $cols = [
                1 => ['B', 'C', 'D'],
                2 => ['E', 'F', 'G'],
                3 => ['H', 'I', 'J'],
            ];
            foreach ($cols as $empId => [$cRet, $cTr, $cDif]) {
                $vals = $agingPorEmp[$empId]['por_banco'][$banco] ?? [
                    'retenidos' => 0.0, 'transito' => 0.0, 'diferidos' => 0.0,
                ];
                $wr->setCellValue("{$cRet}{$fila}", $vals['retenidos']);
                $wr->setCellValue("{$cTr}{$fila}", $vals['transito']);
                $wr->setCellValue("{$cDif}{$fila}", $vals['diferidos']);
                foreach (["{$cRet}{$fila}", "{$cTr}{$fila}", "{$cDif}{$fila}"] as $cell) {
                    $wr->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
            $fila++;
        }
        // Filas: 5=MACRO 6=ITAU 7=BIND

        $wr->setCellValue('A9', 'Ret+Tr (para Disponible HOY)');
        $wr->getStyle('A9')->getFont()->setBold(true);
        $wr->setCellValue('B9', 'Biyemas');
        $wr->setCellValue('C9', 'Kandiko');
        $wr->setCellValue('D9', 'Rebisco');
        $wr->setCellValue('A10', 'MACRO');
        $wr->setCellValue('B10', '=B5+C5');
        $wr->setCellValue('C10', '=E5+F5');
        $wr->setCellValue('D10', '=H5+I5');
        $wr->setCellValue('A11', 'ITAU');
        $wr->setCellValue('B11', '=B6+C6');
        $wr->setCellValue('C11', '=E6+F6');
        $wr->setCellValue('D11', '=H6+I6');
        $wr->setCellValue('A12', 'BIND');
        $wr->setCellValue('B12', '=B7+C7');
        $wr->setCellValue('C12', '=E7+F7');
        $wr->setCellValue('D12', '=H7+I7');
        foreach (['B10', 'C10', 'D10', 'B11', 'C11', 'D11', 'B12', 'C12', 'D12'] as $cell) {
            $wr->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (range('A', 'J') as $col) {
            $wr->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Bloque 4) Disponible HOY = saldo pesos − (retenidos + tránsito).
     */
    private function escribirDisponibleHoy(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $wr, Carbon $fechaPosicion): void
    {
        // La plantilla base trae merges en la zona de notas (ej. A79:G80).
        foreach ($wr->getMergeCells() as $range) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $m)) {
                $r1 = (int) $m[2];
                $r2 = (int) $m[4];
                if ($r2 >= 76 && $r1 <= 95) {
                    $wr->unmergeCells($range);
                }
            }
        }

        // Limpiar placeholder viejo
        for ($r = 76; $r <= 95; $r++) {
            foreach (range('A', 'G') as $col) {
                $wr->setCellValue("{$col}{$r}", null);
            }
        }

        $wr->setCellValue('A76', '4) Disponible HOY (saldo pesos − cheques retenidos − tránsito)');
        $wr->getStyle('A76')->getFont()->setBold(true);
        $wr->setCellValue('G76', $fechaPosicion->format('Y-m-d'));

        $wr->setCellValue('A77', 'Concepto');
        $wr->setCellValue('B77', 'Biyemas');
        $wr->setCellValue('C77', 'Kandiko');
        $wr->setCellValue('D77', 'Rebisco');
        $wr->setCellValue('E77', 'Total');
        $wr->getStyle('A77:E77')->getFont()->setBold(true);

        // MACRO
        $wr->setCellValue('A78', 'Saldo Macro (PESOS)');
        $wr->setCellValue('B78', '=B45');
        $wr->setCellValue('C78', '=C45');
        $wr->setCellValue('D78', '=D45');
        $wr->setCellValue('E78', '=SUM(B78:D78)');

        $wr->setCellValue('A79', 'Cheques Macro retenidos+tránsito');
        $wr->setCellValue('B79', '=ResumenCheques!B10*-1');
        $wr->setCellValue('C79', '=ResumenCheques!C10*-1');
        $wr->setCellValue('D79', '=ResumenCheques!D10*-1');
        $wr->setCellValue('E79', '=SUM(B79:D79)');

        $wr->setCellValue('A80', 'Disponible MACRO - HOY');
        $wr->setCellValue('B80', '=B78+B79');
        $wr->setCellValue('C80', '=C78+C79');
        $wr->setCellValue('D80', '=D78+D79');
        $wr->setCellValue('E80', '=SUM(B80:D80)');
        $wr->getStyle('A80:E80')->getFont()->setBold(true);

        // ITAU / BMA
        $wr->setCellValue('A82', 'Saldo ITAU/BMA (PESOS)');
        $wr->setCellValue('B82', '=B46');
        $wr->setCellValue('C82', '=C46');
        $wr->setCellValue('D82', '=D46');
        $wr->setCellValue('E82', '=SUM(B82:D82)');

        $wr->setCellValue('A83', 'Cheques ITAU retenidos+tránsito');
        $wr->setCellValue('B83', '=ResumenCheques!B11*-1');
        $wr->setCellValue('C83', '=ResumenCheques!C11*-1');
        $wr->setCellValue('D83', '=ResumenCheques!D11*-1');
        $wr->setCellValue('E83', '=SUM(B83:D83)');

        $wr->setCellValue('A84', 'Disponible ITAU/BMA - HOY');
        $wr->setCellValue('B84', '=B82+B83');
        $wr->setCellValue('C84', '=C82+C83');
        $wr->setCellValue('D84', '=D82+D83');
        $wr->setCellValue('E84', '=SUM(B84:D84)');
        $wr->getStyle('A84:E84')->getFont()->setBold(true);

        // BIND
        $wr->setCellValue('A86', 'Saldo BIND (PESOS)');
        $wr->setCellValue('B86', '=B50');
        $wr->setCellValue('C86', '=C50');
        $wr->setCellValue('D86', '=D50');
        $wr->setCellValue('E86', '=SUM(B86:D86)');

        $wr->setCellValue('A87', 'Cheques BIND retenidos+tránsito');
        $wr->setCellValue('B87', '=ResumenCheques!B12*-1');
        $wr->setCellValue('C87', '=ResumenCheques!C12*-1');
        $wr->setCellValue('D87', '=ResumenCheques!D12*-1');
        $wr->setCellValue('E87', '=SUM(B87:D87)');

        $wr->setCellValue('A88', 'Disponible BIND - HOY');
        $wr->setCellValue('B88', '=B86+B87');
        $wr->setCellValue('C88', '=C86+C87');
        $wr->setCellValue('D88', '=D86+D87');
        $wr->setCellValue('E88', '=SUM(B88:D88)');
        $wr->getStyle('A88:E88')->getFont()->setBold(true);

        $wr->setCellValue('A90', 'Notas');
        $wr->setCellValue(
            'A91',
            'Disponible HOY = saldo pesos (bloque pesificado) − (cheques retenidos + tránsito) del PORTFOLIO de posición '
            .'(en_portfolio_posicion=1, planilla tesorería). El padrón completo 2026 queda en ERP para conciliación. '
            .'Retenidos = venc. ≤ fecha−30d; tránsito = (fecha−30d, fecha]. Completar cotizaciones B41/B42.',
        );
        $wr->mergeCells('A91:E93');
        $wr->getStyle('A91')->getAlignment()->setWrapText(true);

        foreach (range(78, 88) as $r) {
            foreach (range('B', 'E') as $col) {
                $cell = $wr->getCell("{$col}{$r}");
                if ($cell->getValue() !== null && $cell->getValue() !== '') {
                    $wr->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
        }
    }

    public function generarArchivo(string $rutaSalida, Carbon $fechaPosicion, ?string $plantillaBase = null): array
    {
        if ($plantillaBase !== null && is_file($plantillaBase)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            // Evitar re-cargar hojas cheques enormes si la base ya las tiene
            $wb = $reader->load($plantillaBase);
            foreach (['Cheques BSA', 'Cheques KSA', 'Cheques RSA', 'ResumenCheques'] as $hoja) {
                if ($wb->sheetNameExists($hoja)) {
                    $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName($hoja)));
                }
            }
        } else {
            $wb = new Spreadsheet();
            $wb->getActiveSheet()->setTitle('Saldos');
        }

        if ($wb->sheetNameExists('Resumen')) {
            $wb->getSheetByName('Resumen')->setCellValue('E1', $fechaPosicion->format('Y-m-d'));
        }

        $stats = $this->volcarEnSpreadsheet($wb, $fechaPosicion);
        $writer = new Xlsx($wb);
        $writer->save($rutaSalida);
        $stats['out'] = $rutaSalida;

        return $stats;
    }
}
