<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\MayorConceptoExcelFormatoNumero;
use App\Support\Contable\MayorConceptoListadoFiltros;
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

    private function colUltima(): string
    {
        return $this->esMultiempresa() ? 'R' : 'Q';
    }

    /** @return list<string> Columnas de importes (cotiz / debe / haber). */
    private function columnasImportes(): array
    {
        return $this->esMultiempresa() ? ['P', 'Q', 'R'] : ['O', 'P', 'Q'];
    }

    /** @return list<string> Solo Debe y Haber. */
    private function columnasDebeHaber(): array
    {
        return $this->esMultiempresa() ? ['Q', 'R'] : ['P', 'Q'];
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
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;

        $datosResumen = $agrupacionResumen === 'cuenta_concepto'
            ? (is_array($resumenPorCuenta) ? $resumenPorCuenta : [])
            : (is_array($resumen) ? $resumen : []);
        $tieneBloqueResumen = (is_array($resumen) && $resumen !== [])
            || (is_array($resumenPorCuenta) && $resumenPorCuenta !== []);

        $this->calcularFilasEncabezado(
            $tieneBloqueResumen,
            $datosResumen,
            $agrupacionResumen,
            is_array($auditoriaPanel) ? $auditoriaPanel : null,
        );

        $multiempresa = $this->esMultiempresa();

        $totalesReporte = [
            'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
            'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
            'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
        ];
        if (MayorConceptoListadoFiltros::tieneFiltroDetalle($this->filtros)) {
            $totalesVisibles = MayorConceptoListadoFiltros::totalesDesdeFilasVisibles($filas);
            $totalesReporte = array_merge($totalesReporte, $totalesVisibles, ['filtrado' => true]);
        }

        return view('exports.contable.mayorconceptoindex', [
            'filas' => $filas,
            'resumen' => $resumen,
            'resumenPorCuenta' => $resumenPorCuenta,
            'agrupacionResumen' => $agrupacionResumen,
            'auditoriaPanel' => $auditoriaPanel,
            'filtros' => $this->filtros,
            'excel_formato_numero' => MayorConceptoExcelFormatoNumero::normalizar(
                $this->filtros['excel_formato_numero'] ?? MayorConceptoExcelFormatoNumero::AR
            ),
            'totales' => $totalesReporte,
            'titulo' => 'Mayor por concepto',
            'subtitulo' => $this->reporteService->formatearEmpresasTexto($this->filtros)
                .' · '.$this->reporteService->formatearPeriodoTexto($this->filtros),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'multiempresa' => $multiempresa,
            'colSpanExcel' => $multiempresa ? 18 : 17,
            'puede_ver_asiento' => can('listar-asiento', false) || can('editar-asiento', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
            'puede_ver_concepto' => can('listar-conceptos-de-gastos', false) || can('editar-conceptos-de-gastos', false),
            'puede_ver_ordencompra' => can('listar-ordencompra', false) || can('editar-ordencompra', false),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $datosResumen
     * @param  array<string, mixed>|null  $auditoriaPanel
     */
    private function calcularFilasEncabezado(
        bool $tieneBloqueResumen,
        array $datosResumen,
        string $agrupacionResumen,
        ?array $auditoriaPanel,
    ): void {
        $fila = $this->filaTituloExcel;
        $this->filaCabecerasResumenExcel = 0;

        if ($tieneBloqueResumen) {
            $fila++; // título del bloque resumen
            $fila++; // thead resumen
            $this->filaCabecerasResumenExcel = $fila;
            $fila += $this->contarFilasResumenDatos($datosResumen, $agrupacionResumen);
            $fila++; // spacer
            if (! empty($auditoriaPanel['conciliacion'])) {
                $fila++;
            }
            $fila++; // "Detalle de movimientos"
        }

        $fila++; // thead detalle
        $this->filaCabecerasExcel = $fila;
        $this->filaPrimeraDatosExcel = $fila + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $datosResumen
     */
    private function contarFilasResumenDatos(array $datosResumen, string $agrupacionResumen): int
    {
        $count = 0;
        foreach ($datosResumen as $grupo) {
            $hijos = $agrupacionResumen === 'cuenta_concepto'
                ? ($grupo['conceptos'] ?? [])
                : ($grupo['cuentas'] ?? []);
            $count += (is_array($hijos) ? count($hijos) : 0) + 1; // + fila total del grupo
        }

        return $count;
    }

    public function columnFormats(): array
    {
        // Montos van preformateados (AR/INTL) como texto para no pelear con la locale de Excel.
        $fmt = [];
        foreach (array_merge(['A'], $this->columnasImportes()) as $col) {
            $fmt[$col] = NumberFormat::FORMAT_TEXT;
        }
        if ($this->esMultiempresa()) {
            $fmt['B'] = NumberFormat::FORMAT_TEXT;
            $fmt['F'] = NumberFormat::FORMAT_TEXT;
            $fmt['G'] = NumberFormat::FORMAT_TEXT;
        } else {
            $fmt['E'] = NumberFormat::FORMAT_TEXT;
            $fmt['F'] = NumberFormat::FORMAT_TEXT;
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
        if ($this->esMultiempresa()) {
            return [
                'A' => 14,
                'B' => 8,
                'C' => 22,
                'D' => 12,
                'E' => 22,
                'F' => 10,
                'G' => 10,
                'H' => 5,
                'I' => 16,
                'J' => 10,
                'K' => 8,
                'L' => 16,
                'M' => 12,
                'N' => 28,
                'O' => 5,
                'P' => 11,  // Cotiz.
                'Q' => 16,  // Debe (ancho fijo)
                'R' => 16,  // Haber (ancho fijo)
            ];
        }

        return [
            'A' => 8,
            'B' => 22,
            'C' => 12,
            'D' => 22,
            'E' => 10,
            'F' => 10,
            'G' => 5,
            'H' => 16,
            'I' => 10,
            'J' => 8,
            'K' => 16,
            'L' => 12,
            'M' => 28,
            'N' => 5,
            'O' => 11,  // Cotiz.
            'P' => 16,  // Debe (ancho fijo)
            'Q' => 16,  // Haber (ancho fijo)
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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
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

                // Por si el conteo de filas meta no coincide con el HTML parseado, localizar thead detalle.
                $filaCabDetalle = $this->localizarFilaCabeceraDetalle($sheet) ?? $this->filaCabecerasExcel;
                $this->filaCabecerasExcel = $filaCabDetalle;
                $this->filaPrimeraDatosExcel = $filaCabDetalle + 1;

                $estiloCabecera = [
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
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ];

                if ($this->filaCabecerasResumenExcel > 0) {
                    $sheet->getStyle('A'.$this->filaCabecerasResumenExcel.':'.$colUltima.$this->filaCabecerasResumenExcel)
                        ->applyFromArray($estiloCabecera);
                }

                $sheet->getStyle('A'.$filaCabDetalle.':'.$colUltima.$filaCabDetalle)
                    ->applyFromArray($estiloCabecera);

                $this->aplicarAlineacionImportes($sheet);

                // Congelar solo filas "cortas". Si el resumen ocupa 100+ filas y congelamos
                // en el thead del detalle, Excel deja el panel superior gigante y no se puede scrollear.
                if ($filaCabDetalle > 0 && $filaCabDetalle <= 15) {
                    $sheet->freezePane('A'.($filaCabDetalle + 1));
                } else {
                    // Resumen largo: congelar solo logos/título para poder recorrer el archivo.
                    $sheet->freezePane('A'.($this->filaTituloExcel + 1));
                }

                $this->aplicarEstilosFilasTotalesRapido($sheet);
            },
        ];
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

        // Refuerzo de ancho fijo en Debe/Haber (por si AutoSize residual de otro concern).
        foreach ($this->columnasDebeHaber() as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(false);
            $sheet->getColumnDimension($col)->setWidth(16);
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
