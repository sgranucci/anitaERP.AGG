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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MayorPlanoCuentaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** Resultado ya calculado (cache de pantalla); evita regenerar Anita al exportar. */
    private ?array $resultado = null;

    private bool $hayFilaLogos = false;

    private bool $multiempresa = false;

    private string $sheetTitle = 'Mayor plano cuenta';

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
     * @param  array<string, mixed>|null  $resultado  Cache de pantalla; si falta se regenera
     */
    public function parametros(array $filtros, ?array $resultado = null): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;

        return $this;
    }

    public function setTitle(string $title): self
    {
        $sanitizado = MayorPlanoCuentaReporteService::sanitizarNombreHojaExcel($title);
        $this->sheetTitle = $sanitizado !== '' ? $sanitizado : 'Mayor plano cuenta';

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $this->resultado = $resultado;

        $filas = $this->reporteService->aplanarFilas($resultado, $this->filtros, true);
        $totales = [
            'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
            'cantidad_cuentas' => (int) ($resultado['totales']['cuentas'] ?? 0),
            'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
            'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
        ];

        $this->multiempresa = count($this->filtros['empresa_ids'] ?? []) > 1
            || empty($this->filtros['consolidar_empresas']);

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

        return view('exports.contable.mayorplanocuentaindex', [
            'filas' => $filas,
            'totales' => $totales,
            'filtros' => $this->filtros,
            'titulo' => 'Mayor analítico por cuenta contable',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'multiempresa' => $this->multiempresa,
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

        $filasMeta = 2; // título + Generado
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
        $conCc = $this->mostrarColumnaCentrocosto();
        if ($this->multiempresa) {
            return $conCc ? 'Q' : 'P';
        }

        return $conCc ? 'P' : 'O';
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::preferenciaGlobal();
        if ($this->mostrarColumnaCentrocosto()) {
            $fmt = [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => NumberFormat::FORMAT_TEXT,
                'E' => NumberFormat::FORMAT_TEXT,
                'H' => NumberFormat::FORMAT_TEXT,
                'I' => NumberFormat::FORMAT_TEXT,
                'K' => ExcelFormatoNumero::codigoColumna($formato, 4),
                'L' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'M' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'N' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'O' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'P' => ExcelFormatoNumero::codigoColumna($formato, 2),
            ];
        } else {
            $fmt = [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => NumberFormat::FORMAT_TEXT,
                'E' => NumberFormat::FORMAT_TEXT,
                'H' => NumberFormat::FORMAT_TEXT,
                'J' => ExcelFormatoNumero::codigoColumna($formato, 4),
                'K' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'L' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'M' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'N' => ExcelFormatoNumero::codigoColumna($formato, 2),
                'O' => ExcelFormatoNumero::codigoColumna($formato, 2),
            ];
        }

        return $fmt;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function columnWidths(): array
    {
        // Anchos para importes con separadores (ej. -12.345.678,90) sin #####.
        // No achicar L–O: Excel muestra ##### si el ancho no alcanza.
        $widths = [
            'A' => 11,  // Fecha
            'B' => 11,  // N.Asi.
            'C' => 5,   // Tip
            'D' => 15,  // Comprobante
            'E' => 30,  // Emisor (código — nombre)
            'F' => 13,  // CUIT
            'G' => 32,  // Descripción mov.
        ];
        if ($this->mostrarColumnaCentrocosto()) {
            $widths['H'] = 20;  // Centro de costo
            $widths['I'] = 11;  // O.Compra
            $widths['J'] = 5;   // Mon
            $widths['K'] = 11;  // Cotiz.
            $widths['L'] = 13;  // Mon. Ref.
            $widths['M'] = 16;  // Debe
            $widths['N'] = 16;  // Haber
            $widths['O'] = 16;  // Saldo del mes
            $widths['P'] = 18;  // Saldo ejerc.
            if ($this->multiempresa) {
                $widths['Q'] = 8;
            }
        } else {
            $widths['H'] = 11;  // O.Compra
            $widths['I'] = 5;   // Mon
            $widths['J'] = 11;  // Cotiz.
            $widths['K'] = 13;  // Mon. Ref.
            $widths['L'] = 16;  // Debe
            $widths['M'] = 16;  // Haber
            $widths['N'] = 16;  // Saldo del mes
            $widths['O'] = 18;  // Saldo ejerc.
            if ($this->multiempresa) {
                $widths['P'] = 8;
            }
        }

        return $widths;
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
                        'color' => ['rgb' => '17202A'],
                        'size' => 10,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ];

                $sheet->getStyle('A'.$filaCabDetalle.':'.$colUltima.$filaCabDetalle)
                    ->applyFromArray($estiloCabecera);
                $sheet->getRowDimension($filaCabDetalle)->setRowHeight(30);

                if ($filaCabDetalle <= 40) {
                    $sheet->freezePane('A'.($filaCabDetalle + 1));
                } else {
                    $sheet->freezePane('A'.($this->filaTituloExcel + 1));
                }

                $this->aplicarAlineacionImportes($sheet);
                $this->aplicarEstilosFilasEstructura($sheet);

                // Anchos al final: el HTML/colspan suele dejar O (Saldo ejerc.) colapsada → #####.
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
            $b = trim((string) ($sheet->getCell('B'.$row)->getValue() ?? ''));
            if ($a === 'Fecha' && (str_starts_with($b, 'N.Asi') || $b === 'N.Asi.')) {
                return $row;
            }
            if ($a === 'Fecha') {
                $c = trim((string) ($sheet->getCell('C'.$row)->getValue() ?? ''));
                if ($c === 'Tip') {
                    return $row;
                }
            }
        }

        return null;
    }

    private function aplicarAlineacionImportes(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 1) {
            return;
        }

        $desde = max(1, $this->filaPrimeraDatosExcel);
        $colsImportes = $this->mostrarColumnaCentrocosto()
            ? ['K', 'L', 'M', 'N', 'O', 'P']
            : ['J', 'K', 'L', 'M', 'N', 'O'];
        foreach ($colsImportes as $col) {
            $sheet->getStyle($col.$desde.':'.$col.$highestRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }

    private function aplicarEstilosFilasEstructura(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $colUltima = $this->colUltima();
        $desde = max(1, $this->filaPrimeraDatosExcel);

        $estiloEmpresa = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFF3CD']],
        ];
        $estiloCuenta = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D6EAF8']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
        $estiloTotal = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E9ECEF']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'ADB5BD']],
            ],
        ];
        $estiloSaldo = [
            'font' => ['italic' => true, 'name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '555555']],
        ];

        for ($row = $desde; $row <= $highestRow; $row++) {
            $valor = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            if ($valor === '') {
                // Puede ser total_cuenta (colspan en A vacío en algunos parsers) — mirar texto "Total cuenta"
                $merged = trim((string) ($sheet->getCell('A'.$row)->getCalculatedValue() ?? ''));
                $valor = $merged;
            }

            if (str_starts_with($valor, 'Empresa:')) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloEmpresa);
            } elseif (str_starts_with($valor, 'Cuenta:')) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloCuenta);
            } elseif (str_starts_with($valor, 'Centro de costo:')) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloCuenta);
            } elseif (str_starts_with($valor, 'Total cuenta')) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloTotal);
            } elseif (str_starts_with($valor, 'Total centro de costo')) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloTotal);
            } elseif ($valor === 'Saldo Inicial') {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estiloSaldo);
            }
        }
    }
}
