<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SicoreListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'I';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<object>  $filasParaLogo
     * @param  list<array<string, mixed>>  $registros
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $conciliacion
     */
    public function __construct(
        private array $filasParaLogo,
        private array $registros,
        private array $totales,
        private array $conciliacion,
        private string $titulo,
        private string $subtitulo = '',
    ) {
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($this->filasParaLogo));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++; // contador registros

        $mostrarConciliacion = ! empty($this->conciliacion['habilitada'])
            && ! empty($this->conciliacion['items']);
        if ($mostrarConciliacion) {
            // título conciliación + thead + ítems + título detalle
            $filasMeta += 2 + count($this->conciliacion['items']) + 1;
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        // Cabecera azul = thead del detalle SICORE (última fila meta antes de datos)
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.sicoreindex', [
            'registros' => $this->registros,
            'totales' => $this->totales,
            'conciliacion' => $this->conciliacion,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 8,
            'C' => 12,
            'D' => 16,
            'E' => 28,
            'F' => 12,
            'G' => 14,
            'H' => 14,
            'I' => 36,
        ];
    }

    public function title(): string
    {
        return 'SICORE';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 130;
                    }
                }

                $filaInicioMeta = $this->hayFilaLogos ? 2 : 1;
                for ($f = $filaInicioMeta; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($f = $this->filaTituloExcel + 1; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->getStyle('A'.$f)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
