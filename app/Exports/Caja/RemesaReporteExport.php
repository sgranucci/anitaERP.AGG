<?php

declare(strict_types=1);

namespace App\Exports\Caja;

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

class RemesaReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
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
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private readonly array $filas,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly array $resultado,
    ) {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $filasMeta = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function view(): View
    {
        return view('exports.caja.remesa_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'resultado' => $this->resultado,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'puede_ver_remesa' => false,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => '#,##0.0000',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 8,
            'D' => 14,
            'E' => 16,
            'F' => 16,
            'G' => 12,
            'H' => 8,
            'I' => 10,
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
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filasMeta = $this->contarFilasMetaEncabezado();
                $filaTit = $this->filaTituloExcel;
                $ultimaMeta = $filaTit + $filasMeta - 1;
                for ($f = $filaTit; $f <= $ultimaMeta; $f++) {
                    $sheet->mergeCells('A'.$f.':'.self::COL_ULTIMA.$f);
                }
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
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
                $sheet->getStyle('A'.($filaTit + 1).':'.self::COL_ULTIMA.$ultimaMeta)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                ]);

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$this->filaCabecerasExcel)
                    ->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '85C1E9'],
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '17202A'],
                            'name' => 'Arial',
                            'size' => 11,
                        ],
                    ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Remesas x cuenta caja';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        if ((int) ($this->resultado['total_movimientos'] ?? 0) > 0) {
            $filasMeta++;
        }

        return $filasMeta;
    }
}
