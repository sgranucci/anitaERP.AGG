<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketCanjeCajaReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filasMeta = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $filas
     * @param  array{cantidad:int,monto_venta:float,monto_ticket:float}  $totales
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private $filas,
        private array $totales,
        private string $titulo,
        private string $subtitulo = '',
        private array $filtros = [],
    ) {
        $coleccion = $this->filas instanceof Collection ? $this->filas : collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $this->filasMeta++;
        }
        $this->filasMeta++; // totales
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function view(): View
    {
        return view('exports.caja.ticket_canje_caja_reporteindex', [
            'filas' => $this->filas,
            'totales' => $this->totales,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'filtros' => $this->filtros,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Informe canjes';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 12,
            'D' => 14,
            'E' => 22,
            'F' => 22,
            'G' => 14,
            'H' => 14,
            'I' => 14,
            'J' => 6,
            'K' => 8,
            'L' => 12,
            'M' => 8,
            'N' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $offsetLogo = $this->hayFilaLogos ? 1 : 0;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                $filaInicioMeta = $offsetLogo + 1;
                for ($f = $filaInicioMeta; $f < $filaInicioMeta + $this->filasMeta; $f++) {
                    $sheet->mergeCells('A'.$f.':'.self::COL_ULTIMA.$f);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($f = $this->filaTituloExcel + 1; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->getStyle('A'.$f)->applyFromArray([
                        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
