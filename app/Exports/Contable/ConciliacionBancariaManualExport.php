<?php

namespace App\Exports\Contable;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConciliacionBancariaManualExport
{
    /** @var array<string, mixed> */
    private array $tablero = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /**
     * @param  array<string, mixed>  $tablero
     * @param  array<string, mixed>  $meta
     */
    public function parametros(array $tablero, array $meta = []): self
    {
        $this->tablero = $tablero;
        $this->meta = $meta;

        return $this;
    }

    public function descargar(string $nombreArchivo): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'conc_manual_');
        if ($ruta === false) {
            throw new \RuntimeException('No hay carpeta temporal para armar el Excel.');
        }
        $xlsx = $ruta.'.xlsx';
        @rename($ruta, $xlsx);
        $ss = $this->generar();
        (new Xlsx($ss))->save($xlsx);

        return response()->download($xlsx, $nombreArchivo)->deleteFileAfterSend(true);
    }

    private function generar(): Spreadsheet
    {
        $ss = new Spreadsheet;
        $ss->removeSheetByIndex(0);

        $this->hojaLista($ss, 'Pendientes contable', $this->tablero['contables'] ?? [], true, 'FF1B4F72');
        $this->hojaLista($ss, 'Pendientes banco', $this->tablero['banco'] ?? [], false, 'FF148F77');
        $this->hojaPares($ss);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function hojaLista(Spreadsheet $ss, string $titulo, array $filas, bool $contable, string $color): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle(mb_substr($titulo, 0, 31));
        $ws->setCellValue('A1', $titulo);
        $ws->setCellValue('A2', sprintf(
            '%s · %s · %02d/%d',
            $this->meta['cuenta'] ?? '',
            $this->meta['empresa'] ?? '',
            (int) ($this->meta['mes'] ?? 0),
            (int) ($this->meta['anio'] ?? 0),
        ));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = $contable
            ? ['Fecha', 'Tipo', 'Asiento', 'Nro', 'Descripción', 'Importe', 'Hash', 'Estado']
            : ['Fecha', 'Concepto IB', 'Concepto banco', 'Comprobante', 'Importe', 'Hash', 'Estado'];
        $col = 1;
        foreach ($headers as $h) {
            $ws->setCellValueByColumnAndRow($col, 4, $h);
            $col++;
        }
        $ws->getStyle('A4:'.$ws->getCellByColumnAndRow(count($headers), 4)->getCoordinate())
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
        $ws->getStyle('A4:'.$ws->getCellByColumnAndRow(count($headers), 4)->getCoordinate())
            ->getFont()->getColor()->setARGB('FFFFFFFF');

        $hashesC = array_flip($this->tablero['hashes_contables'] ?? []);
        $hashesB = array_flip($this->tablero['hashes_banco'] ?? []);
        $row = 5;
        foreach ($filas as $mov) {
            if (! is_array($mov)) {
                continue;
            }
            $hash = (string) ($mov['hash'] ?? '');
            $conciliado = $contable ? isset($hashesC[$hash]) : isset($hashesB[$hash]);
            if ($contable) {
                $ws->fromArray([
                    (string) ($mov['fecha_fmt'] ?? $mov['fecha'] ?? ''),
                    (string) ($mov['tipo_comp'] ?? ''),
                    (string) ($mov['nro_asiento_fmt'] ?? ''),
                    (string) ($mov['nro'] ?? ''),
                    (string) ($mov['descripcion'] ?? ''),
                    (float) ($mov['importe'] ?? 0),
                    $hash,
                    $conciliado ? 'conciliado' : 'pendiente',
                ], null, 'A'.$row);
            } else {
                $ws->fromArray([
                    substr((string) ($mov['process_date'] ?? ''), 0, 10),
                    (string) ($mov['code_description_ib'] ?? ''),
                    (string) ($mov['code_description_bank'] ?? ''),
                    (string) ($mov['voucher_number'] ?? ''),
                    (float) ($mov['importe'] ?? 0),
                    $hash,
                    $conciliado ? 'conciliado' : 'pendiente',
                ], null, 'A'.$row);
            }
            $row++;
        }
        foreach (range(1, count($headers)) as $c) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }

    private function hojaPares(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('Pares del periodo');
        $ws->setCellValue('A1', 'Conciliados del período');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $headers = ['Fecha contable', 'Detalle contable', 'Fecha banco', 'Detalle banco', 'Importe', 'Modo', 'Par ID'];
        foreach ($headers as $i => $h) {
            $ws->setCellValueByColumnAndRow($i + 1, 3, $h);
        }
        $row = 4;
        foreach ($this->tablero['pares'] ?? [] as $par) {
            if (! is_array($par)) {
                continue;
            }
            $ws->fromArray([
                (string) ($par['fecha_contable'] ?? ''),
                (string) ($par['detalle_contable'] ?? ''),
                (string) ($par['fecha_banco'] ?? ''),
                (string) ($par['detalle_banco'] ?? ''),
                (float) ($par['importe'] ?? 0),
                (string) ($par['modo'] ?? ''),
                (int) ($par['id'] ?? 0),
            ], null, 'A'.$row);
            $row++;
        }
        foreach (range(1, 7) as $c) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }
}
