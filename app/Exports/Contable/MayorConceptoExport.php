<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorConceptoReporteService;
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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MayorConceptoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly MayorConceptoReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->reporteService->generarDesdeFiltros($this->filtros);
        $filas = $this->reporteService->aplanarFilasConTotales($resultado);
        $resumen = $this->reporteService->resumenSegunAgrupacion($resultado, $this->filtros);
        $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
        $auditoriaPanel = $this->reporteService->armarAuditoriaPanel($resultado);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.mayorconceptoindex', [
            'filas' => $filas,
            'resumen' => $resumen,
            'resumenPorCuenta' => $resumenPorCuenta,
            'agrupacionResumen' => $this->filtros['agrupacion_resumen'] ?? 'concepto_cuenta',
            'auditoriaPanel' => $auditoriaPanel,
            'filtros' => $this->filtros,
            'totales' => [
                'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
                'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
                'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
            ],
            'titulo' => 'Mayor por concepto',
            'subtitulo' => $this->reporteService->formatearPeriodoTexto($this->filtros),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'L' => NumberFormat::FORMAT_NUMBER_00,
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
            'A' => 8,
            'B' => 22,
            'C' => 12,
            'D' => 22,
            'E' => 10,
            'F' => 5,
            'G' => 16,
            'H' => 28,
            'I' => 6,
            'J' => 14,
            'K' => 12,
            'L' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
                $this->aplicarGrisadoFilasTotales($sheet);
            },
        ];
    }

    private function aplicarGrisadoFilasTotales(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $textoFila = '';
            foreach (range('A', self::COL_ULTIMA) as $col) {
                $valor = $sheet->getCell($col.$row)->getValue();
                if (is_string($valor) || is_numeric($valor)) {
                    $textoFila .= ' '.(string) $valor;
                }
            }
            $textoFila = trim($textoFila);

            if ($textoFila === '') {
                continue;
            }

            $estilo = null;

            if (stripos($textoFila, 'Total concepto') !== false) {
                $estilo = [
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'CED4DA'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['rgb' => '6C757D'],
                        ],
                    ],
                ];
            } elseif (stripos($textoFila, 'Total cuenta') !== false) {
                $estilo = [
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E9ECEF'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'ADB5BD'],
                        ],
                    ],
                ];
            } elseif (trim((string) ($sheet->getCell('A'.$row)->getValue() ?? '')) === 'Totales') {
                $estilo = [
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'ADB5BD'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['rgb' => '495057'],
                        ],
                    ],
                ];
            }

            if ($estilo !== null) {
                $sheet->getStyle('A'.$row.':'.self::COL_ULTIMA.$row)->applyFromArray($estilo);
            }
        }
    }

    public function title(): string
    {
        return 'Mayor por concepto';
    }
}
