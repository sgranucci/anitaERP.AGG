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
        // Total 100 → entra en A4 landscape sin achicar tanto la letra.
        // Vendedor/Empresa/Asunto angostas → wrap a 2 líneas; Nota ≥ 50%.
        return [
            'A' => 8,
            'B' => 12,
            'C' => 5,
            'D' => 6,
            'E' => 11,
            'F' => 58,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $setup = $event->sheet->getDelegate()->getPageSetup();
                $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $setup->setFitToPage(true);
                $setup->setFitToWidth(1);
                $setup->setFitToHeight(0);

                $margins = $event->sheet->getDelegate()->getPageMargins();
                $margins->setLeft(0.25);
                $margins->setRight(0.25);
                $margins->setTop(0.35);
                $margins->setBottom(0.3);
                $margins->setHeader(0.15);
                $margins->setFooter(0.15);
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

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

                if ($this->filaSubtituloExcel > 0) {
                    $sheet->getRowDimension($this->filaSubtituloExcel)->setRowHeight(22);
                    $sheet->getStyle('A'.$this->filaSubtituloExcel.':'.$colUltima.$this->filaSubtituloExcel)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 12,
                            'name' => 'Arial',
                            'color' => ['rgb' => '333333'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $filaRegistros = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                if ($filaRegistros > $this->filaInicioMeta && $filaRegistros !== $this->filaSubtituloExcel) {
                    $sheet->getStyle('A'.$filaRegistros.':'.$colUltima.$filaRegistros)->applyFromArray([
                        'font' => [
                            'bold' => false,
                            'size' => 9,
                            'name' => 'Arial',
                            'color' => ['rgb' => '666666'],
                        ],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 10,
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
                    $sheet->getStyle('A'.$this->filaPrimeraDatosExcel.':'.$colUltima.$highest)->applyFromArray([
                        'font' => [
                            'size' => 10,
                            'name' => 'Arial',
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                    ]);
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
        // título; subtítulo (rango); opcional registros.
        $filasMeta = 1;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        if (($this->totalFilas > 0 ? $this->totalFilas : count($this->filas)) > 0) {
            $filasMeta++;
        }

        return $filasMeta;
    }
}
