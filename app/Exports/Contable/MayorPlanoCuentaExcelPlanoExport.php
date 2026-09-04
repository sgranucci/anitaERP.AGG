<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
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

/**
 * Excel plano de l-mayor.c (opción 3) + observación de OC y números de factura.
 */
class MayorPlanoCuentaExcelPlanoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private ?array $resultado = null;

    private bool $hayFilaLogos = false;

    private string $sheetTitle = 'Mayor plano';

    private int $filaTituloExcel = 1;

    private int $filaInicioMeta = 1;

    private int $filasMeta = 2;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly MayorPlanoCuentaReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     */
    public function parametros(array $filtros, ?array $resultado = null): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $this->resultado = $resultado;

        $filas = $this->reporteService->aplanarFilasExcelPlano($resultado, $this->filtros);
        $totales = [
            'cantidad_filas' => count($filas),
            'total_debe' => array_sum(array_map(fn (array $f) => (float) ($f['debe'] ?? 0), $filas)),
            'total_haber' => array_sum(array_map(fn (array $f) => (float) ($f['haber'] ?? 0), $filas)),
        ];

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $subtituloPartes = [
            $this->reporteService->formatearEmpresasTexto($this->filtros),
            $this->reporteService->formatearPeriodoTexto($this->filtros),
            $this->reporteService->formatearInclusionAsientosTexto($this->filtros),
            $this->reporteService->formatearCentrocostosTexto($this->filtros),
            $this->reporteService->formatearOrigenMovimientosTexto($this->filtros),
        ];
        $subtitulo = trim(implode(' · ', array_filter($subtituloPartes, fn ($p) => trim((string) $p) !== '')));

        $this->calcularFilasEncabezado($subtitulo, $totales);

        return view('exports.contable.mayorplanocuentaexcelplanoindex', [
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => $this->filtros,
            'titulo' => 'Mayor plano (Anita)',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'excel_formato_numero' => ExcelFormatoNumero::preferenciaGlobal(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $totales
     */
    private function calcularFilasEncabezado(string $subtitulo, array $totales): void
    {
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaTituloExcel = $this->filaInicioMeta;

        $filasMeta = 2;
        if (trim($subtitulo) !== '') {
            $filasMeta++;
        }
        if ((int) ($totales['cantidad_filas'] ?? 0) > 0) {
            $filasMeta++;
        }
        $this->filasMeta = $filasMeta;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    private function mostrarColumnaCentrocosto(): bool
    {
        return MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($this->filtros);
    }

    private function colUltima(): string
    {
        return $this->mostrarColumnaCentrocosto() ? 'S' : 'R';
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::preferenciaGlobal();
        if ($this->mostrarColumnaCentrocosto()) {
            return [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => NumberFormat::FORMAT_TEXT,
                'C' => NumberFormat::FORMAT_TEXT,
                'D' => NumberFormat::FORMAT_TEXT,
                'E' => NumberFormat::FORMAT_TEXT,
                'F' => NumberFormat::FORMAT_TEXT,
                'G' => NumberFormat::FORMAT_TEXT,
                'H' => ExcelFormatoNumero::codigoColumna($formato, 4),
                'I' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'J' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'K' => NumberFormat::FORMAT_TEXT,
                'L' => NumberFormat::FORMAT_TEXT,
                'M' => NumberFormat::FORMAT_TEXT,
                'N' => NumberFormat::FORMAT_TEXT,
                'O' => NumberFormat::FORMAT_TEXT,
                'P' => NumberFormat::FORMAT_TEXT,
                'Q' => NumberFormat::FORMAT_TEXT,
                'R' => NumberFormat::FORMAT_TEXT,
                'S' => NumberFormat::FORMAT_TEXT,
            ];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => ExcelFormatoNumero::codigoColumna($formato, 4),
            'H' => ExcelFormatoNumero::codigoColumna($formato, 2),
            'I' => ExcelFormatoNumero::codigoColumna($formato, 2),
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
            'O' => NumberFormat::FORMAT_TEXT,
            'P' => NumberFormat::FORMAT_TEXT,
            'Q' => NumberFormat::FORMAT_TEXT,
            'R' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function columnWidths(): array
    {
        if ($this->mostrarColumnaCentrocosto()) {
            return [
                'A' => 10,  // Empresa
                'B' => 11,  // Nro.Asi.
                'C' => 11,  // Fecha
                'D' => 13,  // Cuenta
                'E' => 28,  // Descripcion
                'F' => 10,  // C.Costo
                'G' => 6,   // Mon
                'H' => 11,  // Cotizacion
                'I' => 16,  // Debe
                'J' => 16,  // Haber
                'K' => 28,  // Detalle
                'L' => 12,  // Cód. emisor
                'M' => 28,  // Nombre emisor
                'N' => 12,  // Usuario
                'O' => 14,  // fecha ult. mod
                'P' => 11,  // O.Compra
                'Q' => 16,  // proyecto CAPEX
                'R' => 32,  // Observación de la OC
                'S' => 36,  // Numeros de Facturas
            ];
        }

        return [
            'A' => 10,
            'B' => 11,
            'C' => 11,
            'D' => 13,
            'E' => 28,
            'F' => 6,
            'G' => 11,
            'H' => 16,
            'I' => 16,
            'J' => 28,
            'K' => 12,  // Cód. emisor
            'L' => 28,  // Nombre emisor
            'M' => 12,
            'N' => 14,
            'O' => 11,
            'P' => 16,
            'Q' => 32,
            'R' => 36,
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima();

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
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

                $filaCabDetalle = $this->localizarFilaCabeceraDetalle($sheet) ?? $this->filaCabecerasExcel;
                $this->filaCabecerasExcel = $filaCabDetalle;
                $this->filaPrimeraDatosExcel = $filaCabDetalle + 1;

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel)->applyFromArray([
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

                for ($i = 1; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($fila)->setRowHeight(18);
                }

                $estiloCabecera = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 10,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '5D6D7E'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ];
                $estiloNuevas = $estiloCabecera;
                $estiloNuevas['fill']['color'] = ['rgb' => 'C0392B'];

                $colUltima = $this->colUltima();
                $sheet->getStyle('A'.$filaCabDetalle.':'.$colUltima.$filaCabDetalle)->applyFromArray($estiloCabecera);
                $colObs = $this->mostrarColumnaCentrocosto() ? 'P' : 'O';
                $sheet->getStyle($colObs.$filaCabDetalle.':'.$colUltima.$filaCabDetalle)->applyFromArray($estiloNuevas);
                $sheet->getRowDimension($filaCabDetalle)->setRowHeight(30);

                if ($filaCabDetalle <= 40) {
                    $sheet->freezePane('A'.($filaCabDetalle + 1));
                } else {
                    $sheet->freezePane('A'.($this->filaTituloExcel + 1));
                }

                $highestRow = $sheet->getHighestRow();
                $desde = max(1, $this->filaPrimeraDatosExcel);
                $colDebe = $this->mostrarColumnaCentrocosto() ? 'I' : 'H';
                $colHaber = $this->mostrarColumnaCentrocosto() ? 'J' : 'I';
                $colObs = $this->mostrarColumnaCentrocosto() ? 'P' : 'O';
                $colFact = $this->colUltima();
                if ($highestRow >= $desde) {
                    $sheet->getStyle($colDebe.$desde.':'.$colHaber.$highestRow)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getStyle($colObs.$desde.':'.$colFact.$highestRow)->applyFromArray([
                        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                foreach ($this->columnWidths() as $col => $ancho) {
                    $dim = $sheet->getColumnDimension($col);
                    $dim->setAutoSize(false);
                    $dim->setWidth($ancho);
                }
            },
        ];
    }

    private function localizarFilaCabeceraDetalle(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 800);

        for ($row = 1; $row <= $highestRow; $row++) {
            $a = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            $c = trim((string) ($sheet->getCell('C'.$row)->getValue() ?? ''));
            if ($a === 'Empresa' && $c === 'Fecha') {
                return $row;
            }
        }

        return null;
    }
}
