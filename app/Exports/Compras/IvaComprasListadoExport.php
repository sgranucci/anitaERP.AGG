<?php

declare(strict_types=1);

namespace App\Exports\Compras;

use App\Services\Compras\IvaComprasReporteService;
use App\Support\Compras\IvaComprasListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IvaComprasListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed>|null */
    private ?array $resultado = null;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'Q';

    private int $idxPrimerMonto = 8;

    private int $cantidadMontos = 0;

    private bool $esCsv = false;

    private const COL_FREEZE = 'C';

    /** Ancho de columnas de importe: alcanza para ##,###,###.## y cabeceras tipo "Monotributo". */
    private const ANCHO_MONTO = 14;

    public function __construct(
        private readonly IvaComprasReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     */
    public function parametros(array $filtros, ?array $resultado = null, bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $this->resultado = $resultado;
        $filas = $resultado['filas_display'] ?? $resultado['filas'] ?? [];
        $coleccionLogos = collect($filas)->map(fn (array $f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        // título + subtítulo + thead
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 4 : 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $columnasFijas = 7; // N.Pro | Proveedor | CUIT | Fec.Mov | Fec.Iva | Tip | Nro.Comp
        $this->cantidadMontos = count($resultado['columnas'] ?? []);
        $this->idxPrimerMonto = $columnasFijas + 1;
        $totalColumnas = max($columnasFijas, $columnasFijas + $this->cantidadMontos);
        $this->colUltima = Coordinate::stringFromColumnIndex($totalColumnas);

        $subtitulo = 'Período: '.IvaComprasListadoFiltros::formatearPeriodoTexto($this->filtros)
            .' · Orden: '.IvaComprasListadoFiltros::formatearOrdenTexto($this->filtros)
            .' · '.IvaComprasListadoFiltros::formatearSubdiarioTexto($this->filtros);

        return view('exports.compras.iva_comprasindex', [
            'resultado' => $resultado,
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => 'IVA COMPRAS',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        $codigoMonto = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $cols = [];

        for ($i = 1; $i < $this->idxPrimerMonto; $i++) {
            $cols[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_TEXT;
        }
        for ($i = 0; $i < $this->cantidadMontos; $i++) {
            $cols[Coordinate::stringFromColumnIndex($this->idxPrimerMonto + $i)] = $codigoMonto;
        }

        return $cols;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
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
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 10,
            'B' => 28,
            'C' => 14,
            'D' => 11,
            'E' => 11,
            'F' => 6,
            'G' => 15,
        ];
        for ($i = 0; $i < $this->cantidadMontos; $i++) {
            $widths[Coordinate::stringFromColumnIndex($this->idxPrimerMonto + $i)] = self::ANCHO_MONTO;
        }

        return $widths;
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
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp);
                        $drawing->setWorksheet($sheet);
                        $offsetXp += 120;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel);
                if ($this->filaCabecerasExcel > $this->filaTituloExcel + 1) {
                    $sheet->mergeCells('A'.($this->filaTituloExcel + 1).':'.$colUltima.($this->filaTituloExcel + 1));
                }
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setBold(true)->setSize(16)->setName('Arial');
                $sheet->getRowDimension($this->filaCabecerasExcel)->setRowHeight(30);
                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);

                if ($this->esCsv || $this->cantidadMontos < 1) {
                    return;
                }

                $ultimaFila = max($this->filaPrimeraDatosExcel, (int) $sheet->getHighestRow());
                if ($ultimaFila < $this->filaPrimeraDatosExcel) {
                    return;
                }

                $colDesde = Coordinate::stringFromColumnIndex($this->idxPrimerMonto);
                $colHasta = Coordinate::stringFromColumnIndex($this->idxPrimerMonto + $this->cantidadMontos - 1);
                $rangoMontos = $colDesde.$this->filaPrimeraDatosExcel.':'.$colHasta.$ultimaFila;
                $codigoMonto = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

                $sheet->getStyle($rangoMontos)->getNumberFormat()->setFormatCode($codigoMonto);
                $sheet->getStyle($rangoMontos)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // FromView deja strings; forzar numérico para que el formato #,##0.00 se vea y no salga #####.
                if (ExcelFormatoNumero::esAuto(ExcelFormatoNumero::preferenciaGlobal())) {
                    for ($r = $this->filaPrimeraDatosExcel; $r <= $ultimaFila; $r++) {
                        for ($c = 0; $c < $this->cantidadMontos; $c++) {
                            $coord = Coordinate::stringFromColumnIndex($this->idxPrimerMonto + $c).$r;
                            $cell = $sheet->getCell($coord);
                            $raw = $cell->getValue();
                            if ($raw === null || $raw === '') {
                                continue;
                            }
                            if (is_numeric($raw)) {
                                $cell->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                }
            },
        ];
    }

    public function title(): string
    {
        return 'IVA compras';
    }
}
