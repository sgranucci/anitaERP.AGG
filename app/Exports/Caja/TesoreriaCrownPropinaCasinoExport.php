<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TesoreriaCrownPropinaCasinoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents
{
    use Exportable;

    /** @var list<string> */
    private array $letrasImporte = [];

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTotalExcel = 0;

    private string $colUltima = 'C';

    /**
     * @param  list<array{id: int, codigo: string, nombre: string, etiqueta: string}>  $casinos
     * @param  list<array<string, mixed>>  $filas
     * @param  array{total: float, por_casino: array<int, float>}  $totales
     */
    public function __construct(
        private readonly array $casinos,
        private readonly array $filas,
        private readonly array $totales,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly int $totalDias,
    ) {
        $cantidadColumnas = 3 + count($this->casinos);
        $this->colUltima = Coordinate::stringFromColumnIndex($cantidadColumnas);
        for ($i = 3; $i <= $cantidadColumnas; $i++) {
            $this->letrasImporte[] = Coordinate::stringFromColumnIndex($i);
        }
    }

    public function view(): View
    {
        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
        $hayFilaLogos = $logos !== [];
        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        if ($this->totalDias > 0) {
            $filasMeta++;
        }
        $offsetLogo = $hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaTotalExcel = $this->filaPrimeraDatosExcel + count($this->filas);

        return view('exports.caja.tesoreria_crown_propina_casinoindex', [
            'casinos' => $this->casinos,
            'filas' => $this->filas,
            'totales' => $this->totales,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totalDias' => $this->totalDias,
            'logos' => $logos,
        ]);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12,
            'B' => 12,
            'C' => 14,
        ];
        $col = 4;
        foreach ($this->casinos as $_) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $widths[$letra] = 14;
            $col++;
        }

        return $widths;
    }

    public function columnFormats(): array
    {
        $formats = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
        foreach ($this->letrasImporte as $letra) {
            $formats[$letra] = '#,##0.00';
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
                $offsetLogo = $logos !== [] ? 1 : 0;
                $filaInicioMeta = $offsetLogo + 1;
                $filasMeta = $this->filaCabecerasExcel - $offsetLogo - 1;

                if ($offsetLogo === 1) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $colLogo = 1;
                    foreach ($logos as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates(Coordinate::stringFromColumnIndex($colLogo).'1');
                        $drawing->setOffsetX(4);
                        $drawing->setWorksheet($sheet);
                        $colLogo += 2;
                    }
                }

                for ($i = 0; $i < $filasMeta; $i++) {
                    $fila = $filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$this->colUltima.$fila);
                }
                $sheet->getStyle('A'.$filaInicioMeta)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 16, 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->getRowDimension($filaInicioMeta)->setRowHeight(28);
                for ($i = 1; $i < $filasMeta; $i++) {
                    $fila = $filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila)->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$this->colUltima.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $ultimaDatos = max($this->filaPrimeraDatosExcel - 1, $this->filaTotalExcel);
                foreach ($this->letrasImporte as $letra) {
                    for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaDatos; $fila++) {
                        $celda = $sheet->getCell($letra.$fila);
                        $valor = $celda->getValue();
                        if ($valor === null || $valor === '') {
                            continue;
                        }
                        $celda->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                    }
                    $sheet->getStyle($letra.$this->filaPrimeraDatosExcel.':'.$letra.$ultimaDatos)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($letra.$this->filaPrimeraDatosExcel.':'.$letra.$ultimaDatos)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                if ($this->filaTotalExcel >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('A'.$this->filaTotalExcel.':'.$this->colUltima.$this->filaTotalExcel)
                        ->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'AED6F1'],
                            ],
                        ]);
                }
            },
        ];
    }
}
