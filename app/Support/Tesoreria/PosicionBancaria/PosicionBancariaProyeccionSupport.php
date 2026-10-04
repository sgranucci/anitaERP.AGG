<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hojas de proyección del día (Macro / BMA / BAPRO / Bi Bank / BIND)
 * + Resumen descubierto. Esqueletos editables enlazados al Resumen/ResumenCheques.
 *
 * Los movimientos (TRF, RRHH, impuestos, etc.) quedan en blanco para carga
 * operativa en Excel; cheques del día se prellenan desde ResumenCheques.
 */
final class PosicionBancariaProyeccionSupport
{
    /**
     * @return array{hojas:int}
     */
    public function volcarEnSpreadsheet(Spreadsheet $wb, Carbon $fechaPosicion, int $diasProyectados = 5, int $saltoDias = 1): array
    {
        $diasProyectados = max(0, min(31, $diasProyectados));
        $saltoDias = max(1, min(15, $saltoDias));
        $hasta = $fechaPosicion->copy()->addDays($diasProyectados * $saltoDias);
        $movimientos = $this->movimientosPorHoja(
            (new PosicionBancariaPrecargaVolcadoSupport())->lineasPorFechaYHoja($fechaPosicion, $hasta),
            (new PosicionBancariaSolicitudpagoSupport())->lineasPorFechaYHoja($fechaPosicion, $hasta),
        );
        $hojas = 0;
        foreach ($this->definiciones() as $def) {
            $this->escribirHojaBanco(
                $wb,
                $def,
                $fechaPosicion,
                $movimientos[$def['hoja']] ?? [],
                $diasProyectados,
                $saltoDias,
            );
            $hojas++;
        }
        $this->escribirResumenDescubierto($wb);
        $hojas++;

        return ['hojas' => $hojas];
    }

    /**
     * @param  array<string, array<string, list<array<string, mixed>>>>  $precargas
     * @param  array<string, array<string, list<array<string, mixed>>>>  $solicitudes
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function movimientosPorHoja(array $precargas, array $solicitudes): array
    {
        $out = [];
        foreach ([$precargas, $solicitudes] as $fuente) {
            foreach ($fuente as $fecha => $porHoja) {
                foreach ($porHoja as $hoja => $lineas) {
                    foreach ($lineas as $linea) {
                        $out[$hoja][$fecha][] = $linea;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{
     *   titulo: string,
     *   hoja: string,
     *   saldo_resumen: string,
     *   disponible_resumen: string|null,
     *   cheques_resumen: string|null,
     *   cheques_pagos_fila: string|null,
     *   con_vencidos: bool,
     *   ut_saldo: string|null
     * }>
     */
    private function definiciones(): array
    {
        return [
            [
                'titulo' => 'Macro',
                'hoja' => 'Macro',
                'saldo_resumen' => 'B6',
                'disponible_resumen' => 'B80',
                'cheques_resumen' => 'B79',
                'cheques_pagos_fila' => 'B5',
                'con_vencidos' => true,
                'ut_saldo' => 'G6',
            ],
            [
                'titulo' => 'Banco BMA (ex ITAU)',
                'hoja' => 'Macro (BMA)',
                'saldo_resumen' => 'B7',
                'disponible_resumen' => 'B84',
                'cheques_resumen' => 'B83',
                'cheques_pagos_fila' => 'B6',
                'con_vencidos' => true,
                'ut_saldo' => null,
            ],
            [
                'titulo' => 'BANCO PROVINCIA',
                'hoja' => 'BAPRO',
                'saldo_resumen' => 'B9',
                'disponible_resumen' => null,
                'cheques_resumen' => null,
                'cheques_pagos_fila' => null,
                'con_vencidos' => false,
                'ut_saldo' => null,
            ],
            [
                'titulo' => 'Bi Bank',
                'hoja' => 'Bi Bank',
                'saldo_resumen' => 'B11',
                'disponible_resumen' => null,
                'cheques_resumen' => null,
                'cheques_pagos_fila' => null,
                'con_vencidos' => false,
                'ut_saldo' => null,
            ],
            [
                'titulo' => 'BIND',
                'hoja' => 'Bind',
                'saldo_resumen' => 'B12',
                'disponible_resumen' => 'B88',
                'cheques_resumen' => 'B87',
                'cheques_pagos_fila' => 'B7',
                'con_vencidos' => true,
                'ut_saldo' => null,
            ],
        ];
    }

    /**
     * @param  array{
     *   titulo: string,
     *   hoja: string,
     *   saldo_resumen: string,
     *   disponible_resumen: string|null,
     *   cheques_resumen: string|null,
     *   cheques_pagos_fila: string|null,
     *   con_vencidos: bool,
     *   ut_saldo: string|null
     * }  $def
     * @param  array<string, list<array<string, mixed>>>  $movimientosPorFecha
     */
    private function escribirHojaBanco(
        Spreadsheet $wb,
        array $def,
        Carbon $fechaPosicion,
        array $movimientosPorFecha,
        int $diasProyectados,
        int $saltoDias,
    ): void
    {
        $nombre = $def['hoja'];
        if ($wb->sheetNameExists($nombre)) {
            $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName($nombre)));
        }
        $ws = $wb->createSheet();
        $ws->setTitle($nombre);

        $ws->setCellValue('A2', $def['titulo']);
        $ws->getStyle('A2')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('17324D');
        $ws->setCellValue('F2', 'Fecha');
        $ws->setCellValue('G2', ExcelDate::PHPToExcel($fechaPosicion));
        $ws->getStyle('G2')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        $ws->getStyle('F2:G2')->getFont()->setBold(true);

        $ws->setCellValue('A3', 'Disponible del día');
        $ws->setCellValue('B3', 'Biyemas');
        $ws->setCellValue('C3', 'Kandiko');
        $ws->setCellValue('D3', 'Rebisco');
        $ws->setCellValue('E3', 'Total');
        $ws->setCellValue('F3', 'Estado');
        if ($def['ut_saldo'] !== null) {
            $ws->setCellValue('G3', 'Biyemas Skill ON NET UT');
        }
        $this->pintarEncabezado($ws, 'A3:G3');

        $saldoCol = $def['saldo_resumen']; // e.g. B6
        $letra = substr($saldoCol, 0, 1);
        $filaSaldo = (int) substr($saldoCol, 1);

        $fila = 4;
        $ws->setCellValue("A{$fila}", 'SALDOS');
        $ws->setCellValue("B{$fila}", "=Resumen!B{$filaSaldo}");
        $ws->setCellValue("C{$fila}", "=Resumen!C{$filaSaldo}");
        $ws->setCellValue("D{$fila}", "=Resumen!D{$filaSaldo}");
        $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
        if ($def['ut_saldo'] !== null) {
            $ws->setCellValue("G{$fila}", '=Resumen!'.$def['ut_saldo']);
        }
        $filaSaldoLocal = $fila;
        $fila++;

        $filaInicioSum = $filaSaldoLocal;
        if ($def['con_vencidos'] && $def['cheques_resumen'] !== null) {
            $ch = $def['cheques_resumen']; // B79
            $fCh = (int) substr($ch, 1);
            $ws->setCellValue("A{$fila}", 'Cheques retenidos+tránsito');
            $ws->setCellValue("B{$fila}", "=Resumen!B{$fCh}");
            $ws->setCellValue("C{$fila}", "=Resumen!C{$fCh}");
            $ws->setCellValue("D{$fila}", "=Resumen!D{$fCh}");
            $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            $fila++;

            $ws->setCellValue("A{$fila}", 'Disponible del día ajustado');
            $ws->setCellValue("B{$fila}", '=B'.$filaSaldoLocal.'+B'.($fila - 1));
            $ws->setCellValue("C{$fila}", '=C'.$filaSaldoLocal.'+C'.($fila - 1));
            $ws->setCellValue("D{$fila}", '=D'.$filaSaldoLocal.'+D'.($fila - 1));
            $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            if ($def['disponible_resumen'] !== null) {
                // Control vs Resumen Disponible HOY
                $ws->setCellValue('F'.$fila, 'ctrl Resumen');
                $disp = $def['disponible_resumen'];
                $fd = (int) substr($disp, 1);
                $ws->setCellValue('G'.$fila, "=Resumen!B{$fd}");
            }
            $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true);
            $filaInicioSum = $fila;
            $fila++;
        }

        $ws->setCellValue("A{$fila}", 'MOVIMIENTOS DEL DÍA');
        $this->pintarSeccion($ws, $fila);
        $fila++;

        if ($def['cheques_pagos_fila'] !== null) {
            $fRef = (int) substr((string) $def['cheques_pagos_fila'], 1);
            $ws->setCellValue("A{$fila}", 'Pagos proyectados del día Cheque');
            $ws->setCellValue("B{$fila}", "=IFERROR(ResumenCheques!B{$fRef}*-1,0)");
            $ws->setCellValue("C{$fila}", "=IFERROR(ResumenCheques!E{$fRef}*-1,0)");
            $ws->setCellValue("D{$fila}", "=IFERROR(ResumenCheques!H{$fRef}*-1,0)");
            $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            $ws->setCellValue('F'.$fila, 'Cheques');
            $fila++;
        }

        $filasDescubierto = [];
        $fechaClave = $fechaPosicion->toDateString();
        $this->escribirMovimientos($ws, $fila, $movimientosPorFecha[$fechaClave] ?? [], true, $filasDescubierto);

        $this->escribirTotalProyectado($ws, $fila, $filaInicioSum, 'Proyectado del día');
        $filaProyectado = $fila;
        $fila++;
        $this->marcarDescubierto($ws, $fila, $filasDescubierto);

        for ($paso = 1; $paso <= $diasProyectados; $paso++) {
            $dia = $fechaPosicion->copy()->addDays($paso * $saltoDias);
            $fila++;
            $ws->setCellValue("A{$fila}", $dia->locale('es')->isoFormat('dddd DD/MM/YY'));
            $this->pintarDia($ws, $fila);
            $fila++;
            $antes = $fila;
            $sinDescubierto = [];
            $this->escribirMovimientos($ws, $fila, $movimientosPorFecha[$dia->toDateString()] ?? [], false, $sinDescubierto);
            if ($fila === $antes) {
                $ws->setCellValue("A{$fila}", 'Sin precargas ni solicitudes de pago');
                $ws->getStyle("A{$fila}")->getFont()->setItalic(true)->getColor()->setRGB('7F8C8D');
                $fila++;
            }
            $this->escribirTotalProyectado($ws, $fila, $filaInicioSum, 'Proyectado del día T+'.$paso);
            $fila++;
        }
        $filaUltima = $fila - 1;
        $fila += 1;

        // Bloque descubierto local (BAPRO / Bi Bank estilo)
        if (in_array($nombre, ['BAPRO', 'Bi Bank'], true)) {
            $filaDesc = (int) ($ws->getCell('Z1')->getValue() ?: 0);
            $ws->setCellValue("A{$fila}", 'Descubierto total');
            $ws->setCellValue("B{$fila}", 0);
            $ws->setCellValue("C{$fila}", 0);
            $ws->setCellValue("D{$fila}", 0);
            if ($nombre === 'Bi Bank') {
                $ws->setCellValue("E{$fila}", 1400000000); // cupo consolidado habitual (editable)
            }
            $filaTotalDesc = $fila;
            $fila++;
            $ws->setCellValue("A{$fila}", 'Descubierto afectado');
            if ($filaDesc > 0) {
                $ws->setCellValue("B{$fila}", "=B{$filaDesc}");
                $ws->setCellValue("C{$fila}", "=C{$filaDesc}");
                $ws->setCellValue("D{$fila}", "=D{$filaDesc}");
            }
            $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            $filaAfectado = $fila;
            $fila++;
            $ws->setCellValue("A{$fila}", 'Descubierto disponible');
            if ($nombre === 'Bi Bank') {
                $ws->setCellValue("E{$fila}", "=E{$filaTotalDesc}-E{$filaAfectado}");
            } else {
                $ws->setCellValue("E{$fila}", "=E{$filaTotalDesc}-E{$filaAfectado}");
            }
        }

        $ws->setCellValue('A'.($fila + 1), 'Cada fila es una precarga o una solicitud de pago. El estado está en la columna F.');
        $ws->getStyle('A'.($fila + 1))->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('5D6D7E');

        $ultima = max($filaUltima, $fila);
        $ws->getStyle('A4:G'.$ultima)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5D8DC');
        $ws->getStyle('A4:A'.$ultima)->getAlignment()->setWrapText(false)->setVertical(Alignment::VERTICAL_CENTER);
        for ($r = 4; $r <= $ultima; $r++) {
            foreach (['B', 'C', 'D', 'E', 'G'] as $col) {
                $v = $ws->getCell("{$col}{$r}")->getValue();
                if ($v !== null && $v !== '') {
                    $ws->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
        }

        $ws->freezePane('A4');
        $ws->getColumnDimension('A')->setWidth(68);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $ws->getColumnDimension($col)->setWidth(22);
        }
        $ws->getColumnDimension('F')->setWidth(16);
        $ws->getColumnDimension('G')->setWidth(22);
        $this->pintarBloque($ws, $filaSaldoLocal);
        // Ocultar meta
        $ws->getColumnDimension('Z')->setVisible(false);
    }

    private function escribirResumenDescubierto(Spreadsheet $wb): void
    {
        $nombre = 'Resumen descubierto';
        if ($wb->sheetNameExists($nombre)) {
            $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName($nombre)));
        }
        $ws = $wb->createSheet();
        $ws->setTitle($nombre);

        $ws->setCellValue('A3', 'Act');
        $ws->setCellValue('B3', 'Banco');
        $ws->setCellValue('C3', 'Biyemas');
        $ws->setCellValue('D3', 'Kandiko');
        $ws->setCellValue('E3', 'Rebisco');
        $ws->setCellValue('F3', 'Consolidado');
        $ws->getStyle('A3:F3')->getFont()->setBold(true);

        $filas = [
            5 => ['Macro', 'Macro'],
            6 => ['Bi Bank', 'Bi Bank'],
            7 => ['Bind', 'Bind'],
            8 => ['Cheques', null],
        ];

        foreach ($filas as $r => [$label, $hoja]) {
            $ws->setCellValue("B{$r}", $label);
            if ($hoja !== null && $wb->sheetNameExists($hoja)) {
                $filaDesc = (int) ($wb->getSheetByName($hoja)->getCell('Z1')->getValue() ?: 0);
                if ($filaDesc > 0) {
                    $ref = "'{$hoja}'";
                    if ($hoja === 'Bi Bank') {
                        $ref = "'Bi Bank'";
                    }
                    $ws->setCellValue("C{$r}", "={$ref}!B{$filaDesc}");
                    $ws->setCellValue("D{$r}", "={$ref}!C{$filaDesc}");
                    $ws->setCellValue("E{$r}", "={$ref}!D{$filaDesc}");
                } else {
                    $ws->setCellValue("C{$r}", 0);
                    $ws->setCellValue("D{$r}", 0);
                    $ws->setCellValue("E{$r}", 0);
                }
            } else {
                $ws->setCellValue("C{$r}", 0);
                $ws->setCellValue("D{$r}", 0);
                $ws->setCellValue("E{$r}", 0);
            }
            $ws->setCellValue("F{$r}", "=SUM(C{$r}:E{$r})");
        }

        $ws->setCellValue('B10', 'TOTAL');
        $ws->setCellValue('C10', '=SUM(C5:C8)');
        $ws->setCellValue('D10', '=SUM(D5:D8)');
        $ws->setCellValue('E10', '=SUM(E5:E8)');
        $ws->setCellValue('F10', '=SUM(F5:F8)');
        $ws->getStyle('B10:F10')->getFont()->setBold(true);

        $ws->setCellValue('A12', 'Cupos de descubierto se editan en cada hoja (fila Descubierto).');
        $ws->getStyle('A12')->getFont()->setItalic(true)->setSize(9);

        foreach ([5, 6, 7, 8, 10] as $r) {
            foreach (['C', 'D', 'E', 'F'] as $col) {
                $ws->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        $ws->getColumnDimension('B')->setWidth(14);
        foreach (['C', 'D', 'E', 'F'] as $col) {
            $ws->getColumnDimension($col)->setWidth(14);
        }
    }

    /**
     * @param  array{B: ?float, C: ?float, D: ?float, nota: string, tono: string}|null  $filaPrecarga
     */
    private function volcarPrecargaEnFila(Worksheet $ws, int $fila, ?array $filaPrecarga): void
    {
        if ($filaPrecarga === null) {
            return;
        }
        $color = match ((string) ($filaPrecarga['tono'] ?? '')) {
            'rrhh', 'suss' => 'F5B7B1',
            'descubierto' => 'FDEBD0',
            'trf_otros_bancos' => 'D5F5E3',
            'trf_intercompany' => 'E8DAEF',
            'otras_operaciones' => 'D6EAF8',
            default => 'EAF2F8',
        };
        foreach (['B', 'C', 'D'] as $col) {
            $valor = $filaPrecarga[$col] ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }
            $ws->setCellValue($col.$fila, (float) $valor);
            $ws->getStyle($col.$fila)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color);
        }
        $nota = trim((string) ($filaPrecarga['nota'] ?? ''));
        if ($nota !== '') {
            $ws->setCellValue('F'.$fila, mb_substr($nota, 0, 80));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<int>  $filasDescubierto
     */
    private function escribirMovimientos(Worksheet $ws, int &$fila, array $lineas, bool $marcarDescubierto, array &$filasDescubierto): void
    {
        foreach ($lineas as $linea) {
            $ws->setCellValue("A{$fila}", (string) ($linea['detalle'] ?? ''));
            foreach (['B', 'C', 'D'] as $col) {
                $valor = $linea[$col] ?? null;
                if ($valor === null || $valor === '') {
                    continue;
                }
                $ws->setCellValue($col.$fila, (float) $valor);
                $ws->getStyle($col.$fila)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($this->colorTono((string) ($linea['tono'] ?? '')));
            }
            $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            $estado = trim((string) ($linea['estado'] ?? ''));
            if ($estado !== '') {
                $ws->setCellValue('F'.$fila, $estado);
            }
            if ($marcarDescubierto && (string) ($linea['rubro'] ?? '') === 'descubierto') {
                $filasDescubierto[] = $fila;
            }
            $fila++;
        }
    }

    /**
     * @param  list<int>  $filas
     */
    private function marcarDescubierto(Worksheet $ws, int &$fila, array $filas): void
    {
        if ($filas === []) {
            return;
        }
        if (count($filas) === 1) {
            $ws->setCellValue('Z1', $filas[0]);

            return;
        }
        $ws->setCellValue("A{$fila}", 'Descubierto');
        foreach (['B', 'C', 'D'] as $col) {
            $refs = array_map(static fn (int $r): string => $col.$r, $filas);
            $ws->setCellValue($col.$fila, '='.implode('+', $refs));
        }
        $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
        $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true);
        $ws->setCellValue('Z1', $fila);
        $fila++;
    }

    private function escribirTotalProyectado(Worksheet $ws, int $fila, int $desde, string $titulo): void
    {
        $hasta = $fila - 1;
        $ws->setCellValue("A{$fila}", $titulo);
        if ($hasta >= $desde) {
            $ws->setCellValue("B{$fila}", "=SUM(B{$desde}:B{$hasta})");
            $ws->setCellValue("C{$fila}", "=SUM(C{$desde}:C{$hasta})");
            $ws->setCellValue("D{$fila}", "=SUM(D{$desde}:D{$hasta})");
            $ws->setCellValue("E{$fila}", "=SUM(E{$desde}:E{$hasta})");
        }
        $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A{$fila}:E{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1B4F72');
    }

    private function colorTono(string $tono): string
    {
        return match ($tono) {
            'rrhh', 'suss' => 'F5B7B1',
            'descubierto' => 'FDEBD0',
            'trf_otros_bancos' => 'D5F5E3',
            'trf_intercompany' => 'E8DAEF',
            'otras_operaciones' => 'D6EAF8',
            'sp' => 'FCF3CF',
            default => 'EAF2F8',
        };
    }

    private function pintarEncabezado(Worksheet $ws, string $rango): void
    {
        $ws->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('17202A');
        $ws->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
        $ws->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function pintarBloque(Worksheet $ws, int $fila): void
    {
        $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A{$fila}:E{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('17324D');
    }

    private function pintarDia(Worksheet $ws, int $fila): void
    {
        $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true);
        $ws->getStyle("A{$fila}:E{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FDEBD0');
    }

    private function pintarSeccion(Worksheet $ws, int $fila): void
    {
        $ws->getStyle("A{$fila}:E{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');
    }

    private function pintarTotal(Worksheet $ws, int $fila): void
    {
        $ws->getStyle("A{$fila}:E{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFF2CC');
    }
}
