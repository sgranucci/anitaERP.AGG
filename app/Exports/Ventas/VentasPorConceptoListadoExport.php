<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Services\Ventas\VentasPorConceptoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasPorConceptoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, float|int> */
    private array $totales = [];

    private int $totalLineas = 0;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'L';

    private string $titulo = 'Ventas por concepto';

    private string $subtitulo = '';

    public function __construct(
        private readonly VentasPorConceptoReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo = 'Ventas por concepto', string $subtitulo = ''): self
    {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->reporteService->generar($this->filtros);
        $filas = $resultado['filas'];
        $this->totales = $resultado['totales'];
        $this->totalLineas = (int) ($this->totales['cantidad_detalle'] ?? 0);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($filas));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 2
            : 0;

        return view('exports.ventas.ventasporconceptoindex', [
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->totalLineas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_pdf' => true,
            'puede_ver_venta' => false,
            'puede_ver_cliente' => false,
            'puede_ver_concepto' => false,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
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
        return [
            'A' => 12,
            'B' => 8,
            'C' => 18,
            'D' => 28,
            'E' => 12,
            'F' => 22,
            'G' => 28,
            'H' => 12,
            'I' => 12,
            'J' => 14,
            'K' => 12,
            'L' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
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

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
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

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 42 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
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

                $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':G'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Ventas por concepto';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;

        if (trim($this->subtitulo) !== '') {
            $filas++;
        }

        if ($this->totales !== []) {
            $filas++;
        }

        if ($this->totalLineas > 0) {
            $filas++;
        }

        return $filas;
    }
}
