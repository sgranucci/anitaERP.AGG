<?php

namespace App\Exports\Contable;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConciliacionBancariaExport
{
    /** @var array<string, mixed> */
    private array $resultado;

    public function parametros(array $resultado): self
    {
        $this->resultado = $resultado;

        return $this;
    }

    public function descargar(string $nombreArchivo): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->generarSpreadsheet();
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function guardarEn(string $rutaAbsoluta): void
    {
        $writer = new Xlsx($this->generarSpreadsheet());
        $writer->save($rutaAbsoluta);
    }

    private function generarSpreadsheet(): Spreadsheet
    {
        $ss = new Spreadsheet;
        $ss->removeSheetByIndex(0);

        $this->hojaCaratula($ss);
        $this->hojaMayo($ss);
        $this->hojaMayor($ss);
        $this->hojaExtracto($ss);
        $this->hojaSaldo($ss);
        $this->hojaPendientes($ss);
        $this->hojaGastos($ss);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private function hojaCaratula(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('CARATULA');
        $c = $this->resultado['caratula'] ?? [];

        $ws->setCellValue('D3', $c['empresa'] ?? '');
        $ws->setCellValue('D5', 'CONCILIACIÓN DE CUENTAS');
        $ws->setCellValue('D8', 'CUENTA:');
        $ws->setCellValue('E8', $c['cuenta_codigo'] ?? '');
        $ws->setCellValue('D10', 'NOMBRE:');
        $ws->setCellValue('E10', $c['cuenta_nombre'] ?? '');
        $ws->setCellValue('D11', 'Cta.Cte. '.$c['cuenta_interbanking'].' CBU '.$c['cbu']);
        $ws->setCellValue('D15', 'Saldo del Banco según extracto al '.$c['fecha_corte']);
        $ws->setCellValue('E15', $c['saldo_banco_extracto'] ?? 0);
        $ws->setCellValue('D17', 'Cheques emitidos y entregados - no acreditados en Banco');
        $ws->setCellValue('E17', $c['cheques_no_acreditados'] ?? 0);
        $ws->setCellValue('D18', 'Movimientos pendiente a conciliar por soporte');
        $ws->setCellValue('E18', $c['movimientos_pendientes_banco'] ?? 0);
        $ws->setCellValue('D19', 'Saldo del Banco al '.$c['fecha_corte']);
        $ws->setCellValue('E19', $c['saldo_banco_ajustado'] ?? 0);
        $ws->setCellValue('D21', 'Saldo Contable al '.$c['fecha_corte']);
        $ws->setCellValue('E21', $c['saldo_contable'] ?? 0);
        $ws->setCellValue('D24', 'Diferencia:');
        $ws->setCellValue('E24', $c['diferencia'] ?? 0);
    }

    private function hojaMayo(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $mes = (int) ($this->resultado['mes'] ?? 0);
        $anio = (int) ($this->resultado['anio'] ?? 0);
        $c = $this->resultado['caratula'] ?? [];
        $cc = $this->resultado['cuentacaja'] ?? null;

        $ws->setTitle($meses[$mes] ?? 'MAYO');
        $ws->setCellValue('B2', ($c['empresa'] ?? '').' - Conciliación Bancaria '.$cc?->nombre);
        $ws->setCellValue('B3', 'Cta.Cte. Nº '.$c['cuenta_interbanking'].' - CBU '.$c['cbu']);
        $ws->setCellValue('B4', 'Mes de '.($meses[$mes] ?? '').' '.$anio);
        $ws->setCellValue('B7', 'Saldo del Banco al ');
        $ws->setCellValue('G7', $c['saldo_banco_extracto'] ?? 0);
        $ws->setCellValue('B9', 'Cheques emitidos en contab. y que no ingresaron al banco:');
        $ws->setCellValue('G9', $c['cheques_no_acreditados'] ?? 0);
        $ws->setCellValue('C11', 'Suma de registros contables no conciliados');
        $ws->setCellValue('F11', -($c['cheques_no_acreditados'] ?? 0));
        $ws->setCellValue('B18', 'Gastos Bancarios - saldo acumulados del mes:');
        $gastos = array_sum(array_column($this->resultado['gastos_resumen'] ?? [], 'importe'));
        $ws->setCellValue('G18', $gastos);
        $ws->setCellValue('B22', 'Movimientos bancarios pendientes de contabilizar:');
        $ws->setCellValue('B23', 'Fecha');
        $ws->setCellValue('C23', 'Referencia');
        $ws->setCellValue('D23', 'Codigo');
        $ws->setCellValue('E23', 'Concepto');
        $ws->setCellValue('G23', 'Importe');

        // Contaduría: solo TRF/CABAL de soporte. Cheques → Pendientes; gastos → ING-GTOS.
        $pendientesMayo = $this->resultado['pendientes_banco_caratula']
            ?? \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport::filtrarPendientesBancoParaSolapaMes(
                $this->resultado['pendientes_banco'] ?? []
            );

        $row = 24;
        foreach ($pendientesMayo as $mov) {
            $clasif = \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCodificacionSupport::clasificarMovimientoBanco($mov);
            $ws->setCellValue('B'.$row, isset($mov['process_date']) ? date('Y-m-d', strtotime((string) $mov['process_date'])) : '');
            $ws->setCellValue('C'.$row, \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaMovimientoBancoSupport::formatearReferencia($mov['voucher_number'] ?? null));
            $ws->setCellValue('D'.$row, $clasif['codigo']);
            $ws->setCellValue('E'.$row, $mov['code_description_ib'] ?? '');
            $ws->setCellValue('G'.$row, \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport::importeFirmadoBanco($mov));
            $row++;
        }

        $ws->setCellValue('G'.($row + 1), $c['movimientos_pendientes_banco'] ?? 0);
        $ws->setCellValue('D'.($row + 3), 'Saldo Contable por diferencia:');
        $ws->setCellValue('E'.($row + 3), $c['saldo_banco_ajustado'] ?? 0);
        $ws->setCellValue('D'.($row + 5), 'Saldo Contable:');
        $ws->setCellValue('E'.($row + 5), $c['saldo_contable'] ?? 0);
        $ws->setCellValue('D'.($row + 7), 'Diferencia del mes a Conciliar:');
        $ws->setCellValue('E'.($row + 7), $c['diferencia'] ?? 0);
    }

    private function hojaMayor(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Mayor');
        $this->estiloCabecera($ws, 6, [
            'A' => 'Fecha', 'B' => 'N.Asi.', 'C' => 'Tip', 'D' => 'Comprobante', 'E' => 'Emisor',
            'F' => 'CUIT', 'G' => 'Descripcion', 'H' => 'O.Compra', 'I' => 'Mon', 'J' => 'Cotizacion',
            'K' => 'Mon.Referencia', 'L' => 'Debe', 'M' => 'Haber', 'N' => 'Saldo mes', 'O' => 'Saldo ejerc.',
        ]);

        $ws->setCellValue('A2', 'Mayor analitico');
        $ws->setCellValue('A3', 'Desde '.$this->resultado['fecha_desde'].' hasta '.$this->resultado['fecha_hasta']);

        $row = 7;
        foreach ($this->resultado['mayor'] ?? [] as $fila) {
            $tipo = $fila['tipo_fila'] ?? '';
            if ($tipo === 'header_cuenta') {
                $ws->setCellValue('A'.$row, 'Cuenta: '.($fila['cuenta_codigo'] ?? '').' '.($fila['cuenta_nombre'] ?? ''));
                $row++;

                continue;
            }
            if ($tipo === 'saldo_inicial') {
                $ws->setCellValue('A'.$row, 'Saldo Inicial');
                $ws->setCellValue('O'.$row, $fila['saldo_ejercicio'] ?? 0);
                $row++;

                continue;
            }
            if ($tipo !== 'detalle') {
                continue;
            }

            $ws->setCellValue('A'.$row, $fila['fecha_fmt'] ?? '');
            $ws->setCellValue('B'.$row, $fila['nro_asiento_fmt'] ?? '');
            $ws->setCellValue('C'.$row, $fila['tipo_comp'] ?? '');
            $ws->setCellValue('D'.$row, $fila['comprobante'] ?? '');
            $ws->setCellValue('E'.$row, $fila['emisor'] ?? '');
            $ws->setCellValue('G'.$row, $fila['descripcion'] ?? '');
            $ws->setCellValue('I'.$row, $fila['moneda_abrev'] ?? '');
            $ws->setCellValue('J'.$row, $fila['cotizacion'] ?? '');
            $ws->setCellValue('K'.$row, $fila['mon_referencia'] ?? '');
            $ws->setCellValue('L'.$row, $fila['debe'] ?? 0);
            $ws->setCellValue('M'.$row, $fila['haber'] ?? 0);
            $ws->setCellValue('N'.$row, $fila['saldo_mes'] ?? 0);
            $ws->setCellValue('O'.$row, $fila['saldo_ejercicio'] ?? 0);
            $row++;
        }
    }

    private function hojaExtracto(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('EXTRACTO');
        $c = $this->resultado['caratula'] ?? [];

        $ws->setCellValue('A1', 'Movimientos Dias Anteriores');
        $ws->setCellValue('A2', 'Datos actualizados al '.now()->format('d/m/Y'));
        $ws->setCellValue('A4', 'Tipo y Nro de Cuenta');
        $ws->setCellValue('B4', 'CC $ '.$c['cuenta_interbanking']);
        $ws->setCellValue('A5', 'Denominación');
        $ws->setCellValue('B5', $c['empresa'] ?? '');

        $this->estiloCabecera($ws, 6, [
            'A' => 'Fecha Valor', 'B' => 'Fecha Ingreso', 'C' => 'Concepto', 'D' => 'Cod. Op.',
            'E' => 'Comprobante', 'F' => 'Deposit.', 'G' => 'Suc.', 'H' => 'Importe',
            'I' => 'Descripción', 'J' => 'Cod.Op.Bco.', 'K' => 'P.C.C',
        ]);

        $row = 7;
        foreach ($this->resultado['movimientos_extracto'] ?? [] as $mov) {
            $ws->setCellValue('A'.$row, $mov['fecha_valor'] ?? '');
            $ws->setCellValue('B'.$row, $mov['fecha_ingreso'] ?? '');
            $ws->setCellValue('C'.$row, $mov['concepto'] ?? '');
            $ws->setCellValue('D'.$row, $mov['cod_op'] ?? '');
            $ws->setCellValue('E'.$row, $mov['comprobante'] ?? '');
            $ws->setCellValue('G'.$row, $mov['sucursal'] ?? '');
            $ws->setCellValue('H'.$row, $mov['importe'] ?? 0);
            $ws->setCellValue('I'.$row, $mov['descripcion'] ?? '');
            $ws->setCellValue('J'.$row, $mov['cod_op_bco'] ?? '');
            $ws->setCellValue('K'.$row, $mov['pcc'] ?? '');
            $row++;
        }
    }

    private function hojaSaldo(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Saldo');
        $c = $this->resultado['caratula'] ?? [];
        $fechaCorteAnt = \Carbon\Carbon::parse($this->resultado['fecha_desde'] ?? now())->subDay();

        $ws->setCellValue('E1', 'Saldo al '.$fechaCorteAnt->format('d.m.y'));
        $ws->setCellValue('F1', $this->resultado['saldo_inicial_periodo'] ?? 0);
        $ws->setCellValue('A2', ($c['empresa'] ?? '').' - '.($c['cuentacaja_nombre'] ?? ''));
        $ws->setCellValue('E2', 'Saldo Final');
        $ws->setCellValue('F2', $this->resultado['saldo_final_periodo'] ?? $c['saldo_banco_extracto'] ?? 0);

        $this->estiloCabecera($ws, 4, [
            'A' => 'Fecha', 'B' => 'Referencia', 'C' => 'Codigo', 'D' => 'Concepto', 'E' => 'Importe', 'F' => 'Saldo',
        ]);

        $row = 5;
        foreach ($this->resultado['movimientos_saldo'] ?? [] as $fila) {
            $ws->setCellValue('A'.$row, $fila['fecha'] ?? '');
            $ws->setCellValue('B'.$row, $fila['referencia'] ?? '');
            $ws->setCellValue('C'.$row, $fila['codigo'] ?? '');
            $ws->setCellValue('D'.$row, $fila['concepto'] ?? '');
            $ws->setCellValue('E'.$row, $fila['importe'] ?? 0);
            $ws->setCellValue('F'.$row, $fila['saldo'] ?? 0);
            $row++;
        }
    }

    private function hojaPendientes(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Pendientes');
        $cc = $this->resultado['cuentacaja'] ?? null;

        $ws->setCellValue('A2', 'Movimientos de Cuentas sin conciliar');
        $ws->setCellValue('A3', 'Desde primer mov. hasta '.$this->resultado['fecha_hasta']);
        $ws->setCellValue('A4', 'Cuenta: ('.str_pad((string) ($cc?->codigo ?? ''), 8, '0', STR_PAD_LEFT).') '.$cc?->nombre);
        $ws->setCellValue('A1', 'Fuente: '.($this->resultado['pendientes_cheques_fuente'] ?? 'mayor'));

        $this->estiloCabecera($ws, 5, [
            'A' => 'Tip', 'B' => 'Numero', 'C' => 'N.Orig.', 'D' => 'F.Mov.', 'E' => 'F.Dev.',
            'F' => 'F.Entr.', 'G' => 'F.Conc.', 'H' => 'Detalle', 'I' => 'Debitos', 'J' => 'Creditos',
        ]);

        $pendientes = $this->resultado['pendientes_cheques_cpromae'] ?? null;
        $row = 6;
        if (is_array($pendientes) && $pendientes !== []) {
            foreach ($pendientes as $ch) {
                $ws->setCellValue('A'.$row, $ch['tip'] ?? 'CHP');
                $ws->setCellValue('B'.$row, $ch['numero_cheque'] ?? '');
                $ws->setCellValue('D'.$row, $ch['fecha_emision'] ?? '');
                $ws->setCellValue('E'.$row, $ch['fecha_cheque'] ?? '');
                $ws->setCellValue('F'.$row, $ch['fecha_entrega'] ?? '');
                $ws->setCellValue('G'.$row, $ch['fecha_conciliacion'] ?? '');
                $ws->setCellValue('H'.$row, $ch['entregado_a'] ?? '');
                $ws->setCellValue('J'.$row, abs((float) ($ch['importe'] ?? 0)));
                $row++;
            }

            return;
        }

        foreach ($this->resultado['pendientes_contables'] ?? [] as $mov) {
            $importe = \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport::importeFirmadoContable($mov);
            $ws->setCellValue('A'.$row, $mov['tipo_comp'] ?? '');
            $ws->setCellValue('B'.$row, $mov['nro'] ?? '');
            $ws->setCellValue('D'.$row, $mov['fecha_fmt'] ?? '');
            $ws->setCellValue('H'.$row, $mov['descripcion'] ?? '');
            if ($importe >= 0) {
                $ws->setCellValue('I'.$row, $importe);
            } else {
                $ws->setCellValue('J'.$row, abs($importe));
            }
            $row++;
        }
    }

    private function hojaGastos(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('ING-GTOS DIARIOS');
        $c = $this->resultado['caratula'] ?? [];
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $mes = (int) ($this->resultado['mes'] ?? 0);
        $anio = (int) ($this->resultado['anio'] ?? 0);

        $ws->setCellValue('A1', ($c['empresa'] ?? '').' - Conciliación Bancaria ('.($c['cuentacaja_codigo'] ?? '').')');
        $ws->setCellValue('A3', 'Mes de '.($meses[$mes] ?? '').' '.$anio);
        $ws->setCellValue('C6', 'BCO MACRO  GASTOS BANCARIOS');

        $row = 7;
        $total = 0.0;
        foreach ($this->resultado['gastos_resumen'] ?? [] as $g) {
            $ws->setCellValue('C'.$row, $g['codigo'] ?? '');
            $ws->setCellValue('D'.$row, $g['descripcion'] ?? '');
            $ws->setCellValue('G'.$row, $g['importe'] ?? 0);
            $total += (float) ($g['importe'] ?? 0);
            $row++;
        }
        $ws->setCellValue('G'.($row + 1), round($total, 2));
    }

    /**
     * @param  array<string, string>  $columnas
     */
    private function estiloCabecera(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $row, array $columnas): void
    {
        foreach ($columnas as $col => $titulo) {
            $ws->setCellValue($col.$row, $titulo);
            $ws->getStyle($col.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF85C1E9');
            $ws->getStyle($col.$row)->getFont()->setBold(true);
            $ws->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
}
