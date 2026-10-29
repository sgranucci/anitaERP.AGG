<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnalisisEntregasExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    /** @var list<array<string, mixed>> */
    private array $filas = [];

    private string $subtitulo = '';

    /** @var array<string, mixed> */
    private array $resumen = [];

    /** @var list<array<string, mixed>> */
    private array $logos = [];

    private bool $hayFilaLogos = false;

    private int $filasMeta = 4;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<int> */
    private array $filasCorteExcel = [];

    /** @var list<int> */
    private array $filasTotalExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resumen
     * @param  list<array<string, mixed>>  $logos
     */
    public function parametros(array $filas, string $subtitulo, array $resumen, array $logos): self
    {
        $this->filas = $filas;
        $this->subtitulo = $subtitulo;
        $this->resumen = $resumen;
        $this->logos = $logos;
        $this->hayFilaLogos = $logos !== [];
        $this->filasMeta = 4;
        $this->filaInicioMeta = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = ($this->hayFilaLogos ? 1 : 0) + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filasCorteExcel = [];
        $this->filasTotalExcel = [];
        foreach ($filas as $i => $fila) {
            $nro = $this->filaPrimeraDatosExcel + $i;
            if (($fila['tipo_fila'] ?? '') === 'subtotal') {
                $this->filasCorteExcel[] = $nro;
            }
            if (($fila['tipo_fila'] ?? '') === 'total') {
                $this->filasTotalExcel[] = $nro;
            }
        }

        return $this;
    }

    public function view(): View
    {
        return view('exports.ventas.analisisentregasindex', [
            'filas' => $this->filas,
            'subtitulo' => $this->subtitulo,
            'resumen' => $this->resumen,
            'hayFilaLogos' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Análisis de entregas';
    }

    public function columnFormats(): array
    {
        $cantidad = '#,##0.00';
        $importe = '#,##0.00';

        return [
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => $cantidad,
            'K' => $cantidad,
            'M' => $cantidad,
            'N' => $cantidad,
            'P' => $importe,
            'Q' => $cantidad,
            'R' => $cantidad,
            'U' => $importe,
            'V' => $importe,
            'W' => $importe,
            'X' => $importe,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 32, 'C' => 16, 'D' => 36, 'E' => 6, 'F' => 18,
            'G' => 12, 'H' => 12, 'I' => 8, 'J' => 12, 'K' => 12, 'L' => 6,
            'M' => 12, 'N' => 12, 'O' => 8, 'P' => 14, 'Q' => 12, 'R' => 12,
            'S' => 4, 'T' => 18, 'U' => 16, 'V' => 16, 'W' => 16, 'X' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $ultima = 'X';
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $this->dibujarLogos($sheet);
                }
                $hastaMeta = $this->filaInicioMeta + $this->filasMeta - 1;
                $sheet->mergeCells('A'.$this->filaInicioMeta.':'.$ultima.$this->filaInicioMeta);
                for ($fila = $this->filaInicioMeta + 1; $fila <= $hastaMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->applyFromArray([
                    'font' => ['name' => 'Arial', 'bold' => true, 'size' => 16, 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                if ($hastaMeta > $this->filaInicioMeta) {
                    $sheet->getStyle('A'.($this->filaInicioMeta + 1).':A'.$hastaMeta)->applyFromArray([
                        'font' => ['name' => 'Arial', 'bold' => true, 'size' => 10, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }
                $cab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => ['name' => 'Arial', 'bold' => true, 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
                $this->numeros($sheet);
                foreach ($this->filasCorteExcel as $fila) {
                    $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6EAF8']],
                    ]);
                }
                foreach ($this->filasTotalExcel as $fila) {
                    $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'AED6F1']],
                    ]);
                }
            },
        ];
    }

    private function numeros(Worksheet $sheet): void
    {
        $ultima = $this->filaPrimeraDatosExcel + count($this->filas) - 1;
        if ($ultima < $this->filaPrimeraDatosExcel) {
            return;
        }
        foreach (['J', 'K', 'M', 'N', 'P', 'Q', 'R', 'U', 'V', 'W', 'X'] as $col) {
            $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultima)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultima)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultima; $fila++) {
                $valor = $sheet->getCell($col.$fila)->getValue();
                if ($valor === null || $valor === '') {
                    continue;
                }
                $sheet->setCellValueExplicit($col.$fila, (float) $valor, DataType::TYPE_NUMERIC);
            }
        }
    }

    private function dibujarLogos(Worksheet $sheet): void
    {
        $x = 8;
        foreach (EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($this->logosParaRutas())) as $ruta) {
            if (! is_string($ruta) || ! is_file($ruta)) {
                continue;
            }
            $dibujo = new Drawing;
            $dibujo->setPath($ruta);
            $dibujo->setHeight(48);
            $dibujo->setCoordinates('A1');
            $dibujo->setOffsetX($x);
            $dibujo->setWorksheet($sheet);
            $x += 120;
        }
    }

    /**
     * @return list<object>
     */
    private function logosParaRutas(): array
    {
        $salida = [];
        foreach ($this->logos as $logo) {
            $nombre = is_array($logo) ? ($logo['nombre'] ?? '') : '';
            if ($nombre !== '') {
                $salida[] = (object) ['nombreempresa' => $nombre];
            }
        }

        return $salida;
    }
}
