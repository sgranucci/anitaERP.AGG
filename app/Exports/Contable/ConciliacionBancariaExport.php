<?php

namespace App\Exports\Contable;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConciliacionBancariaExport
{
    private const NAVY = 'FF1B4F72';

    private const TEAL = 'FF148F77';

    private const ICE = 'FF85C1E9';

    private const INK = 'FF17202A';

    private const PAPER = 'FFF4F6F7';

    private const BAND = 'FFEAF2F8';

    private const OK = 'FFD5F5E3';

    private const WARN = 'FFFDEBD0';

    /** @var array<string, mixed> */
    private array $resultado;

    public function parametros(array $resultado): self
    {
        $this->resultado = $resultado;

        return $this;
    }

    public function descargar(string $nombreArchivo): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'conciliacion_');
        if ($ruta === false) {
            throw new \RuntimeException('No hay carpeta temporal para armar el Excel.');
        }
        $xlsx = $ruta.'.xlsx';
        @rename($ruta, $xlsx);
        $ruta = $xlsx;
        $writer = new Xlsx($this->generarSpreadsheet());
        $writer->setPreCalculateFormulas(false);
        $writer->save($ruta);

        return response()->download($ruta, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function guardarEn(string $rutaAbsoluta): void
    {
        $writer = new Xlsx($this->generarSpreadsheet());
        $writer->setPreCalculateFormulas(false);
        $writer->save($rutaAbsoluta);
    }

    private function generarSpreadsheet(): Spreadsheet
    {
        $ss = new Spreadsheet;
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $ss->removeSheetByIndex(0);

        $this->hojaCaratula($ss);
        $this->hojaMes($ss);
        $this->hojaMayor($ss);
        $this->hojaExtracto($ss);
        $this->hojaSaldo($ss);
        $this->hojaPendientesCheques($ss);
        $this->hojaPendientesOtros($ss);
        $this->hojaGastos($ss);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private function hojaCaratula(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('CARATULA');
        $ws->getTabColor()->setARGB(self::NAVY);
        $c = $this->resultado['caratula'] ?? [];
        $meses = $this->meses();
        $mes = (int) ($this->resultado['mes'] ?? 0);
        $anio = (int) ($this->resultado['anio'] ?? 0);

        $this->banda($ws, 'A1:F1', 'CONCILIACIÓN BANCARIA', 18, self::NAVY);
        $ws->getRowDimension(1)->setRowHeight(28);
        $this->banda($ws, 'A2:F2', (string) ($c['empresa'] ?? ''), 12, 'FF2471A3');
        $ws->mergeCells('A3:F3');
        $ws->setCellValue('A3', 'Período '.($meses[$mes] ?? '').' '.$anio.'  ·  Corte '.$this->txt($c['fecha_corte'] ?? ''));
        $ws->getStyle('A3')->getFont()->setItalic(true)->getColor()->setARGB('FF566573');

        $ws->mergeCells('A5:F5');
        $ws->setCellValue('A5', 'Cuenta');
        $this->relleno($ws, 'A5:F5', self::BAND);
        $ws->getStyle('A5')->getFont()->setBold(true)->getColor()->setARGB(self::NAVY);

        $ws->setCellValue('A6', 'Código');
        $ws->setCellValue('B6', $this->txt($c['cuenta_codigo'] ?? $c['cuentacaja_codigo'] ?? ''));
        $ws->setCellValue('C6', 'Nombre');
        $ws->mergeCells('D6:F6');
        $ws->setCellValue('D6', $this->txt($c['cuenta_nombre'] ?? $c['cuentacaja_nombre'] ?? ''));
        $ws->mergeCells('A7:F7');
        $ws->setCellValue('A7', 'Cta. cte. '.$this->txt($c['cuenta_interbanking'] ?? '').'    CBU '.$this->txt($c['cbu'] ?? ''));

        $filas = [
            ['Saldo del banco según extracto', (float) ($c['saldo_banco_extracto'] ?? 0), false],
            ['Cheques emitidos y no acreditados', (float) ($c['cheques_no_acreditados'] ?? 0), false],
            ['Movimientos pendientes de soporte', (float) ($c['movimientos_pendientes_banco'] ?? 0), false],
            ['Saldo del banco ajustado', (float) ($c['saldo_banco_ajustado'] ?? 0), true],
            ['Saldo contable', (float) ($c['saldo_contable'] ?? 0), true],
            ['Diferencia', (float) ($c['diferencia'] ?? 0), true],
        ];

        $ws->setCellValue('A9', 'Concepto');
        $ws->setCellValue('E9', 'Importe');
        $this->estiloCabecera($ws, 9, ['A' => 'Concepto', 'E' => 'Importe']);
        $ws->mergeCells('A9:D9');
        $ws->mergeCells('E9:F9');

        $row = 10;
        foreach ($filas as [$etiqueta, $importe, $fuerte]) {
            $ws->mergeCells('A'.$row.':D'.$row);
            $ws->mergeCells('E'.$row.':F'.$row);
            $ws->setCellValue('A'.$row, $etiqueta);
            $ws->setCellValue('E'.$row, $importe);
            $ws->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getStyle('A'.$row.':F'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD5D8DC');
            if ($fuerte) {
                $ws->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
            }
            $row++;
        }

        $dif = abs((float) ($c['diferencia'] ?? 0));
        $this->relleno($ws, 'A'.($row - 1).':F'.($row - 1), $dif < 1 ? self::OK : self::WARN);
        $this->relleno($ws, 'A13:F13', self::BAND);

        $ws->mergeCells('A17:F17');
        $ws->setCellValue('A17', 'Generado el '.now()->format('d/m/Y H:i').' · anitaERP');
        $ws->getStyle('A17')->getFont()->setSize(9)->getColor()->setARGB('FF7F8C8D');

        foreach (['A' => 28, 'B' => 18, 'C' => 16, 'D' => 22, 'E' => 18, 'F' => 16] as $col => $ancho) {
            $ws->getColumnDimension($col)->setWidth($ancho);
        }
        $ws->getStyle('E10:F15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getPageSetup()->setFitToWidth(1)->setFitToHeight(1);
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $ws->setShowGridlines(false);
    }

    private function hojaMes(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $meses = $this->meses();
        $mes = (int) ($this->resultado['mes'] ?? 0);
        $anio = (int) ($this->resultado['anio'] ?? 0);
        $c = $this->resultado['caratula'] ?? [];
        $titulo = $meses[$mes] ?? 'MES';
        $ws->setTitle(substr($titulo, 0, 31));
        $ws->getTabColor()->setARGB(self::TEAL);

        $this->banda($ws, 'A1:G1', $titulo.' '.$anio, 16, self::NAVY);
        $ws->mergeCells('A2:G2');
        $ws->setCellValue('A2', $this->txt($c['empresa'] ?? '').' · '.$this->datoCuenta('nombre').' · Cta. '.$this->txt($c['cuenta_interbanking'] ?? ''));
        $ws->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF566573');

        $resumen = [
            ['B4', 'Saldo del banco', 'E4', (float) ($c['saldo_banco_extracto'] ?? 0)],
            ['B5', 'Cheques no acreditados', 'E5', (float) ($c['cheques_no_acreditados'] ?? 0)],
            ['B6', 'Gastos bancarios del mes', 'E6', (float) array_sum(array_column($this->resultado['gastos_resumen'] ?? [], 'importe'))],
            ['B7', 'Saldo banco ajustado', 'E7', (float) ($c['saldo_banco_ajustado'] ?? 0)],
            ['B8', 'Saldo contable', 'E8', (float) ($c['saldo_contable'] ?? 0)],
            ['B9', 'Diferencia del mes', 'E9', (float) ($c['diferencia'] ?? 0)],
        ];
        foreach ($resumen as [$celEtiq, $etiq, $celImp, $imp]) {
            $ws->mergeCells(substr($celEtiq, 0, 1).substr($celEtiq, 1).':D'.substr($celEtiq, 1));
            $ws->setCellValue($celEtiq, $etiq);
            $ws->setCellValue($celImp, $imp);
            $ws->getStyle($celImp)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $this->relleno($ws, 'B4:E8', self::PAPER);
        $dif = abs((float) ($c['diferencia'] ?? 0));
        $this->relleno($ws, 'B9:E9', $dif < 1 ? self::OK : self::WARN);
        $ws->getStyle('B9:E9')->getFont()->setBold(true);
        $ws->getStyle('B4:E9')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD5D8DC');

        $ws->mergeCells('A11:G11');
        $ws->setCellValue('A11', 'Movimientos bancarios pendientes de contabilizar');
        $this->relleno($ws, 'A11:G11', self::BAND);
        $ws->getStyle('A11')->getFont()->setBold(true)->getColor()->setARGB(self::NAVY);

        $encabezados = ['Fecha', 'Referencia', 'Código', 'Concepto', 'Descripción', 'Importe', ''];
        $filas = [];
        $pendientesMayo = $this->resultado['pendientes_banco_caratula']
            ?? \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport::filtrarPendientesBancoParaSolapaMes(
                $this->resultado['pendientes_banco'] ?? []
            );
        foreach ($pendientesMayo as $mov) {
            $clasif = \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCodificacionSupport::clasificarMovimientoBanco($mov);
            $filas[] = [
                isset($mov['process_date']) ? date('d/m/Y', strtotime((string) $mov['process_date'])) : '',
                \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMovimientoBancoSupport::formatearReferencia($mov['voucher_number'] ?? null),
                $clasif['codigo'],
                $mov['code_description_ib'] ?? '',
                $mov['code_description_bank'] ?? '',
                \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport::importeFirmadoBanco($mov),
                '',
            ];
        }
        if ($filas === []) {
            $filas[] = ['Sin pendientes de soporte en el mes', '', '', '', '', 0, ''];
        }
        $this->volcar($ws, 12, $encabezados, $filas, [6]);
        $ws->getColumnDimension('A')->setWidth(14);
        $ws->getColumnDimension('B')->setWidth(16);
        $ws->getColumnDimension('C')->setWidth(12);
        $ws->getColumnDimension('D')->setWidth(22);
        $ws->getColumnDimension('E')->setWidth(36);
        $ws->getColumnDimension('F')->setWidth(20);
        $ws->setShowGridlines(false);
    }

    private function hojaMayor(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Mayor');
        $ws->getTabColor()->setARGB('FF5D6D7E');
        $this->banda($ws, 'A1:O1', 'Mayor analítico', 14, self::NAVY);
        $ws->mergeCells('A2:O2');
        $ws->setCellValue('A2', 'Desde '.$this->txt($this->resultado['fecha_desde'] ?? '').' hasta '.$this->txt($this->resultado['fecha_hasta'] ?? ''));

        $encabezados = ['Fecha', 'N.Asi.', 'Tip', 'Comprobante', 'Emisor', 'CUIT', 'Descripcion', 'O.Compra', 'Mon', 'Cotizacion', 'Mon.Referencia', 'Debe', 'Haber', 'Saldo mes', 'Saldo ejerc.'];
        $filas = [];
        foreach ($this->resultado['mayor'] ?? [] as $fila) {
            $tipo = $fila['tipo_fila'] ?? '';
            if ($tipo === 'header_cuenta') {
                $filas[] = ['Cuenta: '.($fila['cuenta_codigo'] ?? '').' '.($fila['cuenta_nombre'] ?? ''), '', '', '', '', '', '', '', '', '', '', null, null, null, null];
                continue;
            }
            if ($tipo === 'saldo_inicial') {
                $filas[] = ['Saldo inicial', '', '', '', '', '', '', '', '', '', '', null, null, null, (float) ($fila['saldo_ejercicio'] ?? 0)];
                continue;
            }
            if ($tipo !== 'detalle') {
                continue;
            }
            $filas[] = [
                $fila['fecha_fmt'] ?? '',
                $fila['nro_asiento_fmt'] ?? '',
                $fila['tipo_comp'] ?? '',
                $fila['comprobante'] ?? '',
                $fila['emisor'] ?? '',
                $fila['cuit'] ?? '',
                $fila['descripcion'] ?? '',
                $fila['orden_compra'] ?? '',
                $fila['moneda_abrev'] ?? '',
                $fila['cotizacion'] ?? '',
                $fila['mon_referencia'] ?? '',
                (float) ($fila['debe'] ?? 0),
                (float) ($fila['haber'] ?? 0),
                (float) ($fila['saldo_mes'] ?? 0),
                (float) ($fila['saldo_ejercicio'] ?? 0),
            ];
        }
        $this->volcar($ws, 3, $encabezados, $filas, [12, 13, 14, 15]);
        $ws->getColumnDimension('G')->setWidth(42);
        $ws->freezePane('A4');
    }

    private function hojaExtracto(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('EXTRACTO');
        $ws->getTabColor()->setARGB('FF1A5276');
        $c = $this->resultado['caratula'] ?? [];
        $this->banda($ws, 'A1:K1', 'Extracto Interbanking', 14, self::NAVY);
        $ws->mergeCells('A2:K2');
        $ws->setCellValue('A2', 'CC $ '.$this->txt($c['cuenta_interbanking'] ?? '').' · '.$this->txt($c['empresa'] ?? '').' · actualizado '.now()->format('d/m/Y H:i'));

        $encabezados = ['Fecha valor', 'Fecha ingreso', 'Concepto', 'Cód. op.', 'Comprobante', 'Deposit.', 'Suc.', 'Importe', 'Descripción', 'Cód. op. bco.', 'P.C.C'];
        $filas = [];
        foreach ($this->resultado['movimientos_extracto'] ?? [] as $mov) {
            $filas[] = [
                $mov['fecha_valor'] ?? '',
                $mov['fecha_ingreso'] ?? '',
                $mov['concepto'] ?? '',
                $mov['cod_op'] ?? '',
                $mov['comprobante'] ?? '',
                $mov['depositor_description'] ?? '',
                $mov['sucursal'] ?? '',
                (float) ($mov['importe'] ?? 0),
                $mov['descripcion'] ?? '',
                $mov['cod_op_bco'] ?? '',
                $mov['pcc'] ?? '',
            ];
        }
        $this->volcar($ws, 3, $encabezados, $filas, [8]);
        $ws->getColumnDimension('I')->setWidth(36);
        $ws->freezePane('A4');
    }

    private function hojaSaldo(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Saldo');
        $ws->getTabColor()->setARGB('FF117A65');
        $c = $this->resultado['caratula'] ?? [];
        $this->banda($ws, 'A1:F1', 'Saldo codificado', 14, self::NAVY);
        $ws->setCellValue('A2', $this->txt($c['empresa'] ?? '').' · '.$this->datoCuenta('nombre'));
        $ws->setCellValue('E2', 'Saldo inicial');
        $ws->setCellValue('F2', (float) ($this->resultado['saldo_inicial_periodo'] ?? 0));
        $ws->setCellValue('E3', 'Saldo final');
        $ws->setCellValue('F3', (float) ($this->resultado['saldo_final_periodo'] ?? $c['saldo_banco_extracto'] ?? 0));
        $ws->getStyle('F2:F3')->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle('E2:F3')->getFont()->setBold(true);

        $encabezados = ['Fecha', 'Referencia', 'Código', 'Concepto', 'Importe', 'Saldo'];
        $filas = [];
        foreach ($this->resultado['movimientos_saldo'] ?? [] as $fila) {
            $filas[] = [
                $fila['fecha'] ?? '',
                $fila['referencia'] ?? '',
                $fila['codigo'] ?? '',
                $fila['concepto'] ?? '',
                (float) ($fila['importe'] ?? 0),
                (float) ($fila['saldo'] ?? 0),
            ];
        }
        $this->volcar($ws, 5, $encabezados, $filas, [5, 6]);
        $this->anchos($ws, ['A' => 12, 'B' => 18, 'C' => 10, 'D' => 32, 'E' => 20, 'F' => 22]);
        $ws->freezePane('A6');
    }

    private function hojaPendientesCheques(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Pend. cheques');
        $ws->getTabColor()->setARGB('FFB9770E');
        $this->banda($ws, 'A1:J1', 'Pendientes de cheques', 14, self::NAVY);
        $ws->mergeCells('A2:J2');
        $ws->setCellValue('A2', 'Cheques propios sin conciliar · hasta '.$this->txt($this->resultado['fecha_hasta'] ?? '').' · '.$this->datoCuenta('codigo').' '.$this->datoCuenta('nombre'));

        $encabezados = ['Tip', 'Número', 'F.Mov.', 'F.Dev.', 'F.Entr.', 'F.Conc.', 'Detalle', 'Débitos', 'Créditos', 'Carátula'];
        $filas = [];
        foreach ($this->resultado['pendientes_cheques_cpromae'] ?? [] as $ch) {
            $filas[] = [
                $ch['tip'] ?? 'CHP',
                $ch['numero_cheque'] ?? '',
                $ch['fecha_emision'] ?? '',
                $ch['fecha_cheque'] ?? '',
                $ch['fecha_entrega'] ?? '',
                $ch['fecha_conciliacion'] ?? '',
                $ch['entregado_a'] ?? '',
                null,
                abs((float) ($ch['importe'] ?? 0)),
                ! empty($ch['incluye_caratula']) ? 'Sí' : '',
            ];
        }
        $this->volcar($ws, 3, $encabezados, $filas, [8, 9]);
        $this->anchos($ws, [
            'A' => 8, 'B' => 14, 'C' => 13, 'D' => 13, 'E' => 13, 'F' => 13,
            'G' => 40, 'H' => 18, 'I' => 18, 'J' => 12,
        ]);
        $ws->freezePane('A4');
    }

    private function hojaPendientesOtros(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Pend. otros');
        $ws->getTabColor()->setARGB('FF6C3483');
        $this->banda($ws, 'A1:F1', 'Pendientes de otros movimientos', 14, self::NAVY);
        $ws->mergeCells('A2:F2');
        $ws->setCellValue('A2', 'Mayor sin match que no es cheque. No entran en la carátula de cheques.');

        $encabezados = ['F.Mov.', 'Tip', 'Asiento', 'Detalle', 'Débitos', 'Créditos'];
        $filas = [];
        $lista = $this->resultado['pendientes_contables_otros'] ?? [];
        if ($lista === []) {
            foreach ($this->resultado['pendientes_contables'] ?? [] as $mov) {
                if (\App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport::esChequeNoAcreditado($mov)) {
                    continue;
                }
                $lista[] = $mov;
            }
        }
        foreach ($lista as $mov) {
            $importe = \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            $filas[] = [
                $mov['fecha_fmt'] ?? '',
                $mov['tipo_comp'] ?? '',
                $mov['nro_asiento_fmt'] ?? '',
                $mov['descripcion'] ?? '',
                $importe >= 0 ? $importe : null,
                $importe < 0 ? abs($importe) : null,
            ];
        }
        $this->volcar($ws, 3, $encabezados, $filas, [5, 6]);
        $this->anchos($ws, ['A' => 12, 'B' => 8, 'C' => 14, 'D' => 52, 'E' => 20, 'F' => 20]);
        $ws->freezePane('A4');
    }

    private function hojaGastos(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('ING-GTOS DIARIOS');
        $ws->getTabColor()->setARGB('FF922B21');
        $c = $this->resultado['caratula'] ?? [];
        $meses = $this->meses();
        $mes = (int) ($this->resultado['mes'] ?? 0);
        $anio = (int) ($this->resultado['anio'] ?? 0);
        $doc = $this->resultado['documento_gastos'] ?? [];

        $this->banda($ws, 'A1:F1', 'Gastos bancarios', 14, self::NAVY);
        $ws->mergeCells('A2:F2');
        $ws->setCellValue('A2', $this->txt($c['empresa'] ?? '').' · '.$this->datoCuenta('codigo').' · '.($meses[$mes] ?? '').' '.$anio);

        $encabezados = ['Código', 'Concepto', 'Cuenta', 'Importe', 'Documento', ''];
        $filas = [];
        $lineasDoc = $doc['lineas'] ?? [];
        if ($lineasDoc !== []) {
            foreach ($lineasDoc as $g) {
                $filas[] = [
                    $g['codigo'] ?? '',
                    $g['descripcion'] ?? '',
                    trim(($g['cuenta_codigo'] ?? '').' '.($g['cuenta_nombre'] ?? '')),
                    (float) ($g['importe'] ?? 0),
                    ! empty($g['en_ico']) ? 'ICO' : 'Egreso',
                    '',
                ];
            }
        } else {
            foreach ($this->resultado['gastos_resumen'] ?? [] as $g) {
                $filas[] = [$g['codigo'] ?? '', $g['descripcion'] ?? '', '', abs((float) ($g['importe'] ?? 0)), '', ''];
            }
        }
        $ultima = $this->volcar($ws, 4, $encabezados, $filas, [4]);
        $totalRow = $ultima + 1;
        $ws->setCellValue('A'.$totalRow, 'Total egreso');
        $ws->setCellValue('D'.$totalRow, (float) ($doc['total'] ?? array_sum(array_column($this->resultado['gastos_resumen'] ?? [], 'importe'))));
        $ws->getStyle('D'.$totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle('A'.$totalRow.':D'.$totalRow)->getFont()->setBold(true);
        $this->relleno($ws, 'A'.$totalRow.':F'.$totalRow, self::BAND);

        $pie = $totalRow + 2;
        $ws->mergeCells('A'.$pie.':F'.$pie);
        $tipo = $this->txt($doc['tipo_caja'] ?? 'EGR');
        $ico = $this->txt($doc['tipo_compra'] ?? 'ICO');
        $estado = ! empty($doc['ya_generado']['numero'])
            ? 'Ya generado: egreso '.$doc['ya_generado']['numero']
            : (! empty($doc['listo']) ? 'Listo para generar '.$tipo.' + '.$ico : 'Falta configurar: '.implode(' ', $doc['faltantes'] ?? []));
        $ws->setCellValue('A'.$pie, 'Documento de cierre: un '.$tipo.' por el total y un '.$ico.' con los conceptos impositivos. '.$estado);
        $ws->getStyle('A'.$pie)->getAlignment()->setWrapText(true);
        $ws->getRowDimension($pie)->setRowHeight(32);
        $this->anchos($ws, ['A' => 12, 'B' => 42, 'C' => 48, 'D' => 20, 'E' => 14, 'F' => 4]);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     * @param  list<int>  $columnasImporte  1-based
     */
    private function volcar(Worksheet $ws, int $filaEncabezado, array $encabezados, array $filas, array $columnasImporte): int
    {
        $ws->fromArray($encabezados, null, 'A'.$filaEncabezado);
        $ultimaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezados));
        $this->relleno($ws, 'A'.$filaEncabezado.':'.$ultimaCol.$filaEncabezado, self::ICE);
        $ws->getStyle('A'.$filaEncabezado.':'.$ultimaCol.$filaEncabezado)->getFont()->setBold(true)->getColor()->setARGB(self::INK);
        $ws->getStyle('A'.$filaEncabezado.':'.$ultimaCol.$filaEncabezado)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($filas !== []) {
            $ws->fromArray($filas, null, 'A'.($filaEncabezado + 1));
        }

        $ultima = $filaEncabezado + max(count($filas), 1);
        foreach ($columnasImporte as $n) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
            $ws->getStyle($col.($filaEncabezado + 1).':'.$col.$ultima)->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getStyle($col.($filaEncabezado + 1).':'.$col.$ultima)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ($ws->getColumnDimension($col)->getWidth() < 18) {
                $ws->getColumnDimension($col)->setWidth(20);
            }
        }
        $ws->setAutoFilter('A'.$filaEncabezado.':'.$ultimaCol.$ultima);
        $ws->freezePane('A'.($filaEncabezado + 1));

        return $ultima;
    }

    /**
     * @param  array<string, float>  $anchos
     */
    private function anchos(Worksheet $ws, array $anchos): void
    {
        foreach ($anchos as $col => $ancho) {
            $ws->getColumnDimension($col)->setWidth($ancho);
        }
    }

    private function banda(Worksheet $ws, string $rango, string $texto, int $tamano, string $fondo): void
    {
        $inicio = explode(':', $rango)[0];
        $ws->mergeCells($rango);
        $ws->setCellValue($inicio, $texto);
        $this->relleno($ws, $rango, $fondo);
        $ws->getStyle($rango)->getFont()->setBold(true)->setSize($tamano)->getColor()->setARGB('FFFFFFFF');
        $ws->getStyle($rango)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
    }

    private function relleno(Worksheet $ws, string $rango, string $argb): void
    {
        $ws->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    /**
     * @param  array<string, string>  $columnas
     */
    private function estiloCabecera(Worksheet $ws, int $row, array $columnas): void
    {
        foreach ($columnas as $col => $titulo) {
            $ws->setCellValue($col.$row, $titulo);
        }
        $this->relleno($ws, 'A'.$row.':F'.$row, self::ICE);
        $ws->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true)->getColor()->setARGB(self::INK);
    }

    /**
     * @return array<int, string>
     */
    private function meses(): array
    {
        return ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
    }

    private function datoCuenta(string $campo): string
    {
        $cuenta = $this->resultado['cuentacaja'] ?? null;
        if (is_array($cuenta)) {
            return $this->txt($cuenta[$campo] ?? '');
        }
        if (is_object($cuenta)) {
            return $this->txt($cuenta->{$campo} ?? '');
        }

        return '';
    }

    private function txt(mixed $valor): string
    {
        return trim((string) $valor);
    }
}
