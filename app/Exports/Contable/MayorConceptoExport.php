<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\MayorConceptoExcelFormatoNumero;
use App\Support\Contable\MayorConceptoListadoFiltros;
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

class MayorConceptoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed>|null Resultado ya calculado (cache) para no regenerar Anita. */
    private ?array $resultado = null;

    /** @var array<string, mixed>|null Pack de pantalla (resumen/auditoría) si viene de cache. */
    private ?array $pack = null;

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filasMeta = 2;

    private int $filaCabecerasExcel = 2;

    private int $filaCabecerasResumenExcel = 0;

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
     * @param  array<string, mixed>|null  $packOResultado  Pack cache `{resultado, resumen, …}` o solo `resultado`
     */
    public function parametros(array $filtros, ?array $packOResultado = null): self
    {
        $this->filtros = $filtros;
        $this->pack = null;
        $this->resultado = null;

        if ($packOResultado !== null) {
            if (isset($packOResultado['resultado']) && is_array($packOResultado['resultado'])) {
                $this->pack = $packOResultado;
                $this->resultado = $packOResultado['resultado'];
            } else {
                $this->resultado = $packOResultado;
            }
        }

        return $this;
    }

    private function esMultiempresa(): bool
    {
        return MayorConceptoListadoFiltros::esMultiempresa($this->filtros);
    }

    /** Formato numérico efectivo (auto|ar|intl), con default en la preferencia global. */
    private function formatoNumero(): string
    {
        return ExcelFormatoNumero::normalizar(
            $this->filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal()
        );
    }

    private function colUltima(): string
    {
        return $this->esMultiempresa() ? 'S' : 'R';
    }

    /** @return list<string> Columnas de importes (cotiz / debe / haber). */
    private function columnasImportes(): array
    {
        return $this->esMultiempresa() ? ['Q', 'R', 'S'] : ['P', 'Q', 'R'];
    }

    /** @return list<string> Solo Debe y Haber. */
    private function columnasDebeHaber(): array
    {
        return $this->esMultiempresa() ? ['R', 'S'] : ['Q', 'R'];
    }

    public function view(): View
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $this->resultado = $resultado;

        $filas = $this->reporteService->aplanarFilasConTotalesFiltradas($resultado, $this->filtros);

        $agrupacionResumen = $this->filtros['agrupacion_resumen'] ?? 'concepto_cuenta';
        if (is_array($this->pack) && isset($this->pack['resumen'], $this->pack['resumen_por_cuenta'])) {
            $resumen = $agrupacionResumen === 'cuenta_concepto'
                ? $this->reporteService->resumenSegunAgrupacion($resultado, $this->filtros)
                : $this->pack['resumen'];
            $resumenPorCuenta = $this->pack['resumen_por_cuenta'];
        } else {
            $resumen = $this->reporteService->resumenSegunAgrupacion($resultado, $this->filtros);
            $resumenPorCuenta = $this->reporteService->resumenAgrupadoPorCuenta($resultado);
        }

        $auditoriaPanel = is_array($this->pack) && isset($this->pack['auditoria_panel'])
            ? $this->pack['auditoria_panel']
            : $this->reporteService->armarAuditoriaPanel($resultado);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $multiempresa = $this->esMultiempresa();

        $cantidadDetalle = 0;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') === 'detalle') {
                $cantidadDetalle++;
            }
        }

        $totalesReporte = [
            'cantidad_filas' => $cantidadDetalle,
            'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
            'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
        ];
        if (MayorConceptoListadoFiltros::tieneFiltroDetalle($this->filtros)) {
            $totalesVisibles = MayorConceptoListadoFiltros::totalesDesdeFilasVisibles($filas);
            $totalesReporte = array_merge($totalesReporte, $totalesVisibles, [
                'cantidad_filas' => (int) ($totalesVisibles['cantidad_filas'] ?? $cantidadDetalle),
                'filtrado' => true,
            ]);
        }

        $subtitulo = trim(
            $this->reporteService->formatearEmpresasTexto($this->filtros)
            .' · '.$this->reporteService->formatearPeriodoTexto($this->filtros)
        );

        $this->calcularFilasEncabezado($subtitulo, $totalesReporte);

        return view('exports.contable.mayorconceptoindex', [
            'filas' => $filas,
            'resumen' => $resumen,
            'resumenPorCuenta' => $resumenPorCuenta,
            'agrupacionResumen' => $agrupacionResumen,
            'auditoriaPanel' => $auditoriaPanel,
            'filtros' => $this->filtros,
            'excel_formato_numero' => $this->formatoNumero(),
            'totales' => $totalesReporte,
            'titulo' => 'Mayor por concepto',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'multiempresa' => $multiempresa,
            'colSpanExcel' => $multiempresa ? 19 : 18,
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

        // título + Generado + formato números (+ subtítulo) (+ contador)
        $filasMeta = 3;
        if (trim($subtitulo) !== '') {
            $filasMeta++;
        }
        if ((int) ($totales['cantidad_filas'] ?? 0) > 0) {
            $filasMeta++;
        }
        $this->filasMeta = $filasMeta;

        $this->filaCabecerasResumenExcel = 0;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    /**
     * Thead del bloque resumen (al pie del archivo): fila siguiente al título
     * "Totales por concepto…" / "Totales por cuenta…".
     */
    private function localizarFilaCabeceraResumen(Worksheet $sheet): ?int
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $valor = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            if ($valor === '') {
                continue;
            }
            if (stripos($valor, 'Totales por concepto') !== false
                || stripos($valor, 'Totales por cuenta') !== false) {
                return $row + 1;
            }
        }

        return null;
    }

    private function localizarFilaCabeceraDetalle(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 500);
        $colUltima = $this->colUltima();

        for ($row = 1; $row <= $highestRow; $row++) {
            foreach (range('A', $colUltima) as $col) {
                $valor = trim((string) ($sheet->getCell($col.$row)->getValue() ?? ''));
                if ($valor === 'Fecha') {
                    return $row;
                }
            }
        }

        return null;
    }

    public function columnFormats(): array
    {
        $formato = $this->formatoNumero();

        // Columnas de texto (fecha/códigos): siempre texto.
        $fmt = ['A' => NumberFormat::FORMAT_TEXT];
        if ($this->esMultiempresa()) {
            $fmt['B'] = NumberFormat::FORMAT_TEXT;
            $fmt['F'] = NumberFormat::FORMAT_TEXT;
            $fmt['G'] = NumberFormat::FORMAT_TEXT;
            $fmt['K'] = NumberFormat::FORMAT_TEXT; // Nro.OC.
            $fmt['L'] = NumberFormat::FORMAT_TEXT; // Capex
        } else {
            $fmt['E'] = NumberFormat::FORMAT_TEXT;
            $fmt['F'] = NumberFormat::FORMAT_TEXT;
            $fmt['J'] = NumberFormat::FORMAT_TEXT; // Nro.OC.
            $fmt['K'] = NumberFormat::FORMAT_TEXT; // Capex
        }

        // Importes: en modo "auto" van como número real con máscara neutra (#,##0.00),
        // así cada PC los muestra según su config regional. En ar/intl van como texto.
        $importes = $this->columnasImportes(); // [cotización, debe, haber]
        $colCotiz = $importes[0];
        $fmt[$colCotiz] = ExcelFormatoNumero::codigoColumna($formato, 4);
        foreach ($this->columnasDebeHaber() as $col) {
            $fmt[$col] = ExcelFormatoNumero::codigoColumna($formato, 2);
        }

        return $fmt;
    }

    public function styles(Worksheet $sheet)
    {
        // El color real de cabeceras se aplica en AfterSheet (fila correcta del detalle).
        return [];
    }

    public function columnWidths(): array
    {
        // Anchos alineados al resto de reportes contables (sin ##### en importes).
        if ($this->esMultiempresa()) {
            return [
                'A' => 14,  // Empr.
                'B' => 12,  // Concepto
                'C' => 22,  // Nombre concepto
                'D' => 12,  // Cuenta
                'E' => 24,  // Descripción cuenta
                'F' => 11,  // Fecha
                'G' => 11,  // N.Asi.
                'H' => 5,   // Tip
                'I' => 15,  // Comprobante
                'J' => 11,  // Cheque
                'K' => 9,   // Nro.OC.
                'L' => 10,  // Capex
                'M' => 18,  // Emisor
                'N' => 13,  // CUIT
                'O' => 32,  // Descripción mov.
                'P' => 5,   // Mon
                'Q' => 11,  // Cotiz.
                'R' => 16,  // Debe
                'S' => 16,  // Haber
            ];
        }

        return [
            'A' => 12,  // Concepto
            'B' => 22,  // Nombre concepto
            'C' => 12,  // Cuenta
            'D' => 24,  // Descripción cuenta
            'E' => 11,  // Fecha
            'F' => 11,  // N.Asi.
            'G' => 5,   // Tip
            'H' => 15,  // Comprobante
            'I' => 11,  // Cheque
            'J' => 9,   // Nro.OC.
            'K' => 10,  // Capex
            'L' => 18,  // Emisor
            'M' => 13,  // CUIT
            'N' => 32,  // Descripción mov.
            'O' => 5,   // Mon
            'P' => 11,  // Cotiz.
            'Q' => 16,  // Debe
            'R' => 16,  // Haber
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima();

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

                $filaCabDetalle = $this->localizarFilaCabeceraDetalle($sheet) ?? $this->filaCabecerasExcel;
                $this->filaCabecerasExcel = $filaCabDetalle;
                $this->filaPrimeraDatosExcel = $filaCabDetalle + 1;
                $this->filaCabecerasResumenExcel = $this->localizarFilaCabeceraResumen($sheet) ?? 0;

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
                    $sheet->getRowDimension($fila)->setRowHeight(
                        $i === 2 && $this->filasMeta >= 4 ? 22 : 18
                    );
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

                if ($this->filaCabecerasResumenExcel > 0) {
                    $sheet->getStyle('A'.$this->filaCabecerasResumenExcel.':'.$colUltima.$this->filaCabecerasResumenExcel)
                        ->applyFromArray($estiloCabecera);
                }

                $sheet->getStyle('A'.$filaCabDetalle.':'.$colUltima.$filaCabDetalle)
                    ->applyFromArray($estiloCabecera);
                $sheet->getRowDimension($filaCabDetalle)->setRowHeight(30);

                $this->aplicarAlineacionImportes($sheet);

                // Detalle primero: congelar siempre bajo el thead.
                if ($filaCabDetalle > 0) {
                    $sheet->freezePane('A'.($filaCabDetalle + 1));
                }

                $this->aplicarEstilosFilasTotalesRapido($sheet);

                foreach ($this->columnWidths() as $col => $ancho) {
                    $dim = $sheet->getColumnDimension($col);
                    $dim->setAutoSize(false);
                    $dim->setWidth($ancho);
                }
            },
        ];
    }

    private function aplicarAlineacionImportes(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 1) {
            return;
        }

        foreach ($this->columnasImportes() as $col) {
            $sheet->getStyle($col.'1:'.$col.$highestRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }

    /**
     * Estilos de totales: solo lee columna A (O(n)), no todas las celdas.
     * Evita el OOM/timeout de junio con miles de filas.
     */
    private function aplicarEstilosFilasTotalesRapido(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $colUltima = $this->colUltima();

        $estiloConcepto = [
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
        $estiloCuenta = [
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
        $estiloEmpresa = [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'D6EAF8'],
            ],
        ];
        $estiloTotales = [
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

        for ($row = 1; $row <= $highestRow; $row++) {
            $texto = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            if ($texto === '') {
                continue;
            }

            $estilo = null;
            if (stripos($texto, 'Total concepto') !== false) {
                $estilo = $estiloConcepto;
            } elseif (stripos($texto, 'Total cuenta') !== false) {
                $estilo = $estiloCuenta;
            } elseif (stripos($texto, 'Empresa:') !== false) {
                $estilo = $estiloEmpresa;
            } elseif ($texto === 'Totales') {
                $estilo = $estiloTotales;
            }

            if ($estilo !== null) {
                $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray($estilo);
            }
        }
    }

    public function title(): string
    {
        return 'Mayor por concepto';
    }
}
