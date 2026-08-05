<?php

namespace App\Exports\Ventas;

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
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SuitecrmNotaAuditoriaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'F';

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private int $totalFilas = 0,
    ) {}

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        // Orden meta: título, [subtítulo], generado/registros
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 1
            : 0;

        return view('exports.ventas.suitecrm_nota_auditoriaindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totalFilas' => $this->totalFilas > 0 ? $this->totalFilas : count($this->filas),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
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

    public function columnWidths(): array
    {
        // Total 132; Nota (F) = 75 → ~57%. Columnas angostas fuerzan wrap a 2 líneas.
        return [
            'A' => 11,
            'B' => 18,
            'C' => 5,
            'D' => 6,
            'E' => 17,
            'F' => 75,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $setup = $event->sheet->getDelegate()->getPageSetup();
                $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $setup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
                $setup->setFitToPage(true);
                $setup->setFitToWidth(1);
                $setup->setFitToHeight(0);

                $margins = $event->sheet->getDelegate()->getPageMargins();
                $margins->setLeft(0.3);
                $margins->setRight(0.3);
                $margins->setTop(0.4);
                $margins->setBottom(0.35);
                $margins->setHeader(0.2);
                $margins->setFooter(0.2);
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Forzar anchos (FromView a veces los iguala); Nota ≥ 50%.
                foreach ($this->columnWidths() as $col => $ancho) {
                    $dim = $sheet->getColumnDimension($col);
                    $dim->setAutoSize(false);
                    $dim->setWidth($ancho);
                }

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    $saltoXp = 160;
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
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $colUltima = self::COL_ULTIMA;
                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaInicioMeta.':'.$colUltima.$this->filaInicioMeta)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $ultimaMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                if ($ultimaMeta > $this->filaInicioMeta) {
                    $sheet->getStyle('A'.($this->filaInicioMeta + 1).':'.$colUltima.$ultimaMeta)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                if ($this->filaSubtituloExcel > 0) {
                    $sheet->getRowDimension($this->filaSubtituloExcel)->setRowHeight(22);
                    $sheet->getStyle('A'.$this->filaSubtituloExcel)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'name' => 'Arial',
                            'color' => ['rgb' => '333333'],
                        ],
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

                $highest = $sheet->getHighestRow();
                if ($highest >= $this->filaPrimeraDatosExcel) {
                    // Wrap en columnas angostas (vendedor/empresa/asunto) y nota.
                    $sheet->getStyle('A'.$this->filaPrimeraDatosExcel.':'.$colUltima.$highest)
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Notas CRM';
    }

    private function contarFilasMetaEncabezado(): int
    {
        // título + generado/registros; subtítulo opcional (rango de fechas).
        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }

        return $filasMeta;
    }
}
