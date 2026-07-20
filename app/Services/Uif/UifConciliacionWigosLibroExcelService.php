<?php

namespace App\Services\Uif;

use App\Models\Uif\UifConciliacionWigosPeriodo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Uif\UifWigosConciliacionEmpresaSupport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera el libro Excel por empresa: solapa Titos, solapa PM y solapa UNIFICADO
 * (misma estructura que la muestra "Prueba global").
 */
final class UifConciliacionWigosLibroExcelService
{
    public function guardarEnTemporal(UifConciliacionWigosPeriodo $periodo): string
    {
        $empresaId = (int) $periodo->empresa_id;
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $titos = $periodo->titos()->orderBy('id')->get();
        $pm = $periodo->premiosMaquina()->orderBy('id')->get();
        $unificado = $periodo->unificado()->orderBy('orden')->get();

        $this->armarSolapaTitos(
            $spreadsheet,
            UifWigosConciliacionEmpresaSupport::nombreSolapaTitos($empresaId),
            $titos,
        );
        $this->armarSolapaPm(
            $spreadsheet,
            UifWigosConciliacionEmpresaSupport::nombreSolapaPm($empresaId),
            $pm,
        );
        $this->armarSolapaUnificado(
            $spreadsheet,
            UifWigosConciliacionEmpresaSupport::nombreSolapaUnificado($empresaId),
            $unificado,
        );

        return $this->persistirSpreadsheet($spreadsheet);
    }

    /**
     * Libro global del período: Titos y PM de cada empresa, luego UNIFICADO de cada una
     * (misma estructura que "Prueba global Abril 26.xlsx").
     *
     * @param  list<int>  $empresaIdsOrdenados
     */
    public function guardarEnTemporalGlobal(int $anio, int $mes, array $empresaIdsOrdenados): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $periodosPorEmpresa = UifConciliacionWigosPeriodo::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('empresa_id', $empresaIdsOrdenados)
            ->get()
            ->keyBy('empresa_id');

        foreach ($empresaIdsOrdenados as $empresaId) {
            $periodo = $periodosPorEmpresa->get($empresaId);
            $titos = $periodo !== null ? $periodo->titos()->orderBy('id')->get() : collect();
            $pm = $periodo !== null ? $periodo->premiosMaquina()->orderBy('id')->get() : collect();

            $this->armarSolapaTitos(
                $spreadsheet,
                UifWigosConciliacionEmpresaSupport::nombreSolapaTitos($empresaId),
                $titos,
            );
            $this->armarSolapaPm(
                $spreadsheet,
                UifWigosConciliacionEmpresaSupport::nombreSolapaPm($empresaId),
                $pm,
            );
        }

        foreach ($empresaIdsOrdenados as $empresaId) {
            $periodo = $periodosPorEmpresa->get($empresaId);
            $unificado = $periodo !== null ? $periodo->unificado()->orderBy('orden')->get() : collect();

            $this->armarSolapaUnificado(
                $spreadsheet,
                UifWigosConciliacionEmpresaSupport::nombreSolapaUnificado($empresaId),
                $unificado,
            );
        }

        return $this->persistirSpreadsheet($spreadsheet);
    }

    private function persistirSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'uif_wigos_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }
        $pathXlsx = $path.'.xlsx';
        @unlink($path);

        $writer = new Xlsx($spreadsheet);
        $writer->save($pathXlsx);
        $spreadsheet->disconnectWorksheets();

        return $pathXlsx;
    }

    private function armarSolapaTitos(Spreadsheet $spreadsheet, string $titulo, $titos): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->truncarTituloSolapa($titulo));

        $cantidad = $titos->count();
        $sheet->setCellValue('A1', 'Total');
        $sheet->setCellValue('B1', $cantidad);

        $headers = [
            'Número', 'Secuencia', 'Tipo', 'Promoción asociada', 'Monto', 'Estado',
            'Terminal', 'Cuenta', 'Fecha Emision', 'Terminal', 'Fecha Pago', 'Observaciones',
        ];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 3, $header);
        }
        $this->estiloEncabezado($sheet, 3, count($headers));

        $row = 4;
        foreach ($titos as $t) {
            $sheet->setCellValueExplicit('A'.$row, (string) $t->numero, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, $t->secuencia);
            $sheet->setCellValue('C'.$row, $t->tipo);
            $sheet->setCellValue('D'.$row, $t->promocion);
            $sheet->setCellValue('E'.$row, $t->monto);
            $sheet->setCellValue('F'.$row, $t->estado);
            $sheet->setCellValue('G'.$row, $t->terminal);
            $sheet->setCellValue('H'.$row, $t->cuenta);
            $this->setFechaCelda($sheet, 'I'.$row, $t->fecha_emision);
            $sheet->setCellValue('J'.$row, $t->terminal_caja);
            $this->setFechaCelda($sheet, 'K'.$row, $t->fecha_pago);
            $sheet->setCellValue('L'.$row, $t->observaciones);
            $row++;
        }

        if ($row > 4) {
            $sheet->getStyle('E4:E'.($row - 1))->getNumberFormat()->setFormatCode(
                ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
            );
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function armarSolapaPm(Spreadsheet $spreadsheet, string $titulo, $pm): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->truncarTituloSolapa($titulo));

        $cantidad = $pm->count();
        $sheet->setCellValue('B1', 'Total');
        $sheet->setCellValue('C1', $cantidad);

        $headers = [
            null, 'Fecha', 'Proveedor', 'Nombre', 'ID en planta', 'Monto original',
            'Monto Pagado', 'Tipo', 'Estado', 'Observaciones',
        ];
        foreach ($headers as $i => $header) {
            if ($header !== null) {
                $sheet->setCellValueByColumnAndRow($i + 1, 3, $header);
            }
        }
        $this->estiloEncabezado($sheet, 3, 9, 'B');

        $row = 4;
        foreach ($pm as $p) {
            $this->setFechaCelda($sheet, 'B'.$row, $p->fecha);
            $sheet->setCellValue('C'.$row, $p->proveedor);
            $sheet->setCellValue('D'.$row, $p->nombre);
            $sheet->setCellValue('E'.$row, $p->id_planta);
            $sheet->setCellValue('F'.$row, $p->monto_original);
            $sheet->setCellValue('G'.$row, $p->monto_pagado);
            $sheet->setCellValue('H'.$row, $p->tipo);
            $sheet->setCellValue('I'.$row, $p->estado);
            $sheet->setCellValue('J'.$row, $p->observaciones);
            $row++;
        }

        if ($row > 4) {
            $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
            $sheet->getStyle('F4:F'.($row - 1))->getNumberFormat()->setFormatCode($mascara);
            $sheet->getStyle('G4:G'.($row - 1))->getNumberFormat()->setFormatCode($mascara);
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function armarSolapaUnificado(Spreadsheet $spreadsheet, string $titulo, $unificado): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->truncarTituloSolapa($titulo));

        $headers = ['Fecha Pago', 'Fecha Emision', 'Monto', 'Terminal', 'Número'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }
        $this->estiloEncabezado($sheet, 1, count($headers));

        $row = 2;
        $totalMonto = 0.0;

        foreach ($unificado as $u) {
            $this->setFechaCelda($sheet, 'A'.$row, $u->fecha_pago);
            $this->setFechaCelda($sheet, 'B'.$row, $u->fecha_emision);
            if ($u->monto !== null) {
                $monto = (float) $u->monto;
                $this->setMontoCeldaNumero($sheet, 'C'.$row, $monto);
                $totalMonto += $monto;
            }
            $sheet->setCellValue('D'.$row, $u->terminal);
            if ($u->numero) {
                $sheet->setCellValueExplicit('E'.$row, (string) $u->numero, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $row++;
        }

        if ($totalMonto > 0 || $row > 2) {
            $sheet->setCellValue('B'.$row, 'Total premios');
            $this->setMontoCeldaNumero($sheet, 'C'.$row, $totalMonto);
            $sheet->getStyle('B'.$row)->getFont()->setBold(true);
            $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        }

        $ultimaFila = $row;
        if ($ultimaFila >= 2) {
            $sheet->getStyle('C1:C'.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Escribe el monto como número real con máscara neutra: sumable en Excel y
     * adaptable a la configuración regional de la PC que abre el archivo.
     */
    private function setMontoCeldaNumero($sheet, string $coord, float $monto): void
    {
        $sheet->setCellValue($coord, $monto);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode(
            ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
        );
    }

    private function setFechaCelda($sheet, string $coord, $fecha): void
    {
        if ($fecha === null) {
            return;
        }

        if ($fecha instanceof \DateTimeInterface) {
            $sheet->setCellValue($coord, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha));
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');

            return;
        }

        $sheet->setCellValue($coord, (string) $fecha);
    }

    private function estiloEncabezado($sheet, int $fila, int $columnas, string $desdeCol = 'A'): void
    {
        $desdeIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($desdeCol);
        $hastaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($desdeIdx + $columnas - 1);
        $rango = $desdeCol.$fila.':'.$hastaCol.$fila;
        $sheet->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '85C1E9'],
            ],
        ]);
    }

    private function truncarTituloSolapa(string $titulo): string
    {
        return mb_substr($titulo, 0, 31);
    }
}
