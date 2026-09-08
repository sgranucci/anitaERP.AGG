<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
    public function volcarEnSpreadsheet(Spreadsheet $wb, Carbon $fechaPosicion): array
    {
        $hojas = 0;
        foreach ($this->definiciones() as $def) {
            $this->escribirHojaBanco($wb, $def, $fechaPosicion);
            $hojas++;
        }
        $this->escribirResumenDescubierto($wb);
        $hojas++;

        return ['hojas' => $hojas];
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
     *   ut_saldo: string|null,
     *   conceptos: list<string>,
     *   descubierto_fila: int|null
     * }>
     */
    private function definiciones(): array
    {
        $movimientosMacro = [
            'Cheques emitidos que no están en las posiciones',
            'Cheques Debitados que no están en las posiciones',
            'MOVIMIENTOS DEL DÍA',
            'Debitos a cubrir en el dia',
            'Pagos proyectados del día Cheque',
            'Pagos adicionales del día TRF',
            'Pagos adicionales del día TRF',
            'Impuestos',
            'Canon',
            'Canon',
            'Otros',
            'Otros',
            'Otros',
            'Otros',
            'Otros',
            'Impuestos/SP',
            'Impuestos/SP',
            'RRHH',
            'RRHH',
            'SUSS',
            'Descubierto',
            'TRF desde otros bancos',
            'TRF desde otros bancos',
            'TRF desde otros bancos',
            'Otras operaciones',
            'TRF intercompany',
            'Imp. Deb/Cred.',
            'TRF intercompany',
            'Imp. Deb/Cred.',
            'T.Coin Maquinas',
            'T.Coin Caja',
            'MP Gastro/Parking',
            'MP Juego',
            'MP Juego',
            'Interdepósitos',
            'Planes AFIP/ARBA/MDA',
            'Planes AFIP/ARBA/MDA',
            'Rescate/(suscripcion)',
        ];

        $movimientosBma = [
            'Cheques emitidos que no están en las posiciones',
            'Cheques Debitados que no están en las posiciones',
            'MOVIMIENTOS DEL DÍA',
            'Pagos proyectados del día Cheque',
            'Pagos adicionales del día TRF',
            'Impuestos',
            'Canon',
            'Otros',
            'Otros',
            'Impuestos',
            'Licencias',
            'RRHH',
            'RRHH',
            'Descubierto',
            'TRF desde otros bancos',
            'TRF desde otros bancos',
            'TRF intercompany',
            'MP Gastro/Parking',
            'MP Juego',
            'Interdepósitos',
            'Interdepósitos',
            'Planes AFIP/ARBA/MDA',
            'Planes AFIP/ARBA/MDA',
            'Rescate/(suscripcion)',
        ];

        $movimientosGenerico = [
            'MOVIMIENTOS DEL DÍA',
            'Pagos proyectados del día TRF',
            'Pagos adicionales del día TRF',
            'Pagos adicionales del día CHQ',
            'Sueldos/acuerdos/SAC',
            'Embargos',
            'Autonomos',
            'SUSS',
            'RRHH',
            'Canon Lotería',
            'Sailing',
            'Sindicatos Aleara',
            'Sindicatos Uthgra',
            'Policia',
            'Servicios',
            'Impuestos',
            'Impuestos',
            'Impuestos',
            'Municipalidad',
            'Licencias',
            'Otros',
            'Otros',
            'Otros',
            'Descubierto',
            'TRF desde otros bancos',
            'TRF desde otros bancos',
            'TRF intercompany',
            'TRF intercompany',
            'MP Gastro/Parking',
            'MP Juego',
            'Planes AFIP/ARBA/MDA',
            'Rescate/(suscripcion)',
        ];

        $movimientosBibank = array_merge(
            [
                'MOVIMIENTOS DEL DÍA',
                'Pagos proyectados del día TRF',
                'Pagos adicionales del día TRF',
                'Pagos adicionales del día CHQ',
                'Debitos a cubrir en el dia',
                'Sueldos/acuerdos/SAC',
                'Embargos',
                'Autonomos',
                'SUSS',
                'RRHH',
                'Canon Lotería',
                'Sailing',
                'Sindicatos Aleara',
                'Sindicatos Uthgra',
                'Policia',
                'Servicios',
                'Impuestos',
                'Impuestos',
                'Impuestos',
                'Municipalidad',
                'Licencias',
                'Otros',
                'Otros',
                'Otros',
                'Descubierto',
                'TRF desde otros bancos',
                'TRF desde otros bancos',
                'TRF desde otros bancos',
                'TRF intercompany',
                'Imp. Deb/Cred.',
                'TRF desde otros bancos',
                'TRF desde otros bancos',
                'T.Coin Maquinas',
                'MP Gastro/Parking',
                'MP Juego',
                'Planes AFIP/ARBA/MDA',
                'Rescate/(suscripcion)',
            ],
            []
        );

        $movimientosBind = [
            'MOVIMIENTOS DEL DÍA',
            'Pagos proyectados del día TRF',
            'Pagos adicionales del día TRF',
            'Pagos adicionales del día CHQ',
            'Sueldos/acuerdos/SAC',
            'Embargos',
            'Autonomos',
            'SUSS',
            'RRHH',
            'Canon Lotería',
            'Sailing',
            'Sindicatos Aleara',
            'Sindicatos Uthgra',
            'Policia',
            'Servicios',
            'Impuestos',
            'Impuestos',
            'Impuestos',
            'Municipalidad',
            'Licencias',
            'Otros',
            'Descubierto',
            'Caucion',
            'TRF desde otros bancos',
            'TRF desde otros bancos',
            'TRF intercompany',
            'TRF intercompany',
            'MP Gastro/Parking',
            'MP Juego',
            'Planes AFIP/ARBA/MDA',
            'Rescate/(suscripcion)',
        ];

        return [
            [
                'titulo' => 'Macro',
                'hoja' => 'Macro',
                'saldo_resumen' => 'B6', // C6 D6
                'disponible_resumen' => 'B80',
                'cheques_resumen' => 'B79', // ret+tr negativos en Resumen
                'cheques_pagos_fila' => 'B5', // ResumenCheques retenidos MACRO fila 5
                'con_vencidos' => true,
                'ut_saldo' => 'G6',
                'conceptos' => $movimientosMacro,
                'descubierto_fila' => null, // se calcula
            ],
            [
                'titulo' => 'Banco BMA (ex ITAU)',
                'hoja' => 'Macro (BMA)',
                'saldo_resumen' => 'B7',
                'disponible_resumen' => 'B84',
                'cheques_resumen' => 'B83',
                'cheques_pagos_fila' => 'B6', // ResumenCheques ITAU
                'con_vencidos' => true,
                'ut_saldo' => null,
                'conceptos' => $movimientosBma,
                'descubierto_fila' => null,
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
                'conceptos' => $movimientosGenerico,
                'descubierto_fila' => null,
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
                'conceptos' => $movimientosBibank,
                'descubierto_fila' => null,
            ],
            [
                'titulo' => 'BIND',
                'hoja' => 'Bind',
                'saldo_resumen' => 'B12',
                'disponible_resumen' => 'B88',
                'cheques_resumen' => 'B87',
                'cheques_pagos_fila' => 'B7', // ResumenCheques BIND
                'con_vencidos' => true,
                'ut_saldo' => null,
                'conceptos' => $movimientosBind,
                'descubierto_fila' => null,
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
     *   ut_saldo: string|null,
     *   conceptos: list<string>,
     *   descubierto_fila: int|null
     * }  $def
     */
    private function escribirHojaBanco(Spreadsheet $wb, array $def, Carbon $fechaPosicion): void
    {
        $nombre = $def['hoja'];
        if ($wb->sheetNameExists($nombre)) {
            $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName($nombre)));
        }
        $ws = $wb->createSheet();
        $ws->setTitle($nombre);

        $ws->setCellValue('A2', $def['titulo']);
        $ws->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $ws->setCellValue('F2', 'Fecha');
        $ws->setCellValue('G2', $fechaPosicion->format('Y-m-d'));

        $ws->setCellValue('A3', 'Disponible del día');
        $ws->setCellValue('B3', 'Biyemas');
        $ws->setCellValue('C3', 'Kandiko');
        $ws->setCellValue('D3', 'Rebisco');
        $ws->setCellValue('E3', 'Total');
        if ($def['ut_saldo'] !== null) {
            $ws->setCellValue('G3', 'Biyemas Skill ON NET UT');
        }
        $ws->getStyle('A3:E3')->getFont()->setBold(true);

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

        $filaDescubierto = null;
        foreach ($def['conceptos'] as $concepto) {
            $ws->setCellValue("A{$fila}", $concepto);
            $esSeccion = in_array($concepto, ['MOVIMIENTOS DEL DÍA'], true);
            if ($esSeccion) {
                $ws->getStyle("A{$fila}")->getFont()->setBold(true);
                $this->pintarSeccion($ws, $fila);
            } else {
                // Totales de fila vacíos suman B:D (editable)
                $ws->setCellValue("E{$fila}", "=SUM(B{$fila}:D{$fila})");
            }

            if ($concepto === 'Pagos proyectados del día Cheque' && $def['cheques_pagos_fila'] !== null) {
                // ResumenCheques: fila banco con Ret en B (BIY), E (KAN), H (REB)
                $fRef = (int) substr($def['cheques_pagos_fila'], 1);
                $ws->setCellValue("B{$fila}", "=IFERROR(ResumenCheques!B{$fRef}*-1,0)");
                $ws->setCellValue("C{$fila}", "=IFERROR(ResumenCheques!E{$fRef}*-1,0)");
                $ws->setCellValue("D{$fila}", "=IFERROR(ResumenCheques!H{$fRef}*-1,0)");
                $ws->setCellValue('F'.$fila, 'auto retenidos ERP');
            }

            if ($concepto === 'Descubierto') {
                $filaDescubierto = $fila;
            }

            $fila++;
        }

        // Meta: fila de Descubierto (para Resumen descubierto)
        if ($filaDescubierto !== null) {
            $ws->setCellValue('Z1', $filaDescubierto);
        }

        $ws->setCellValue("A{$fila}", 'Proyectado del día');
        $ws->setCellValue("B{$fila}", '=SUM(B'.$filaInicioSum.':B'.($fila - 1).')');
        $ws->setCellValue("C{$fila}", '=SUM(C'.$filaInicioSum.':C'.($fila - 1).')');
        $ws->setCellValue("D{$fila}", '=SUM(D'.$filaInicioSum.':D'.($fila - 1).')');
        $ws->setCellValue("E{$fila}", '=SUM(E'.$filaInicioSum.':E'.($fila - 1).')');
        $ws->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true);
        $this->pintarTotal($ws, $fila);
        $filaProyectado = $fila;
        $fila += 2;

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

        $ws->setCellValue('A'.($filaProyectado + 1), 'Completar TRF/RRHH/impuestos a mano. Cheques retenidos se prellenan desde ERP.');
        $ws->getStyle('A'.($filaProyectado + 1))->getFont()->setItalic(true)->setSize(9);

        foreach (range(4, $filaProyectado) as $r) {
            foreach (['B', 'C', 'D', 'E', 'G'] as $col) {
                $v = $ws->getCell("{$col}{$r}")->getValue();
                if ($v !== null && $v !== '') {
                    $ws->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
        }

        $ws->getColumnDimension('A')->setWidth(48);
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $ws->getColumnDimension($col)->setWidth(14);
        }
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
