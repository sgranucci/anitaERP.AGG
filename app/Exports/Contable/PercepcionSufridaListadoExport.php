<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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

class PercepcionSufridaListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'H';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 8;

    private int $filaPrimeraDatosExcel = 9;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<object>  $filasParaLogo
     * @param  list<array<string, mixed>>  $diferencias
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $filasParaLogo,
        private array $diferencias,
        private array $totales,
        private string $titulo,
        private string $subtitulo = '',
        private bool $esIibb = false,
    ) {
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($this->filasParaLogo));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $filasTotales = $this->esIibb ? 4 : 3;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + 4 + $filasTotales;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.percepcion_sufridaindex', [
            'diferencias' => $this->diferencias,
            'totales' => $this->totales,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'esIibb' => $this->esIibb,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $mascara = ExcelFormatoNumero::mascara(2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => $mascara,
            'G' => $mascara,
            'H' => $mascara,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 22,
            'C' => 36,
            'D' => 16,
            'E' => 42,
            'F' => 16,
            'G' => 16,
            'H' => 16,
        ];
    }

    public function title(): string
    {
        return 'Diferencias';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 160;
                    }
                }
                $ultimaMeta = $this->filaCabecerasExcel - 1;
                for ($fila = $this->filaTituloExcel; $fila <= $ultimaMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                }
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                ]);
                $sheet->getStyle('E'.$this->filaPrimeraDatosExcel.':E'.($this->filaPrimeraDatosExcel + max(0, count($this->diferencias))))
                    ->getAlignment()->setWrapText(true);
                $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':H'.($this->filaPrimeraDatosExcel + max(0, count($this->diferencias))))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
