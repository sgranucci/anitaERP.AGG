<?php

namespace App\Exports\Contable;

use App\Services\Contable\MovCcostoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\MovCcostoListadoFiltros;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class MovCcostoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private ?array $resultado = null;

    private bool $hayFilaLogos = false;

    private bool $debeHaber = false;

    private int $cantColumnasExcel = 3;

    private int $filaTituloExcel = 1;

    private int $filaInicioMeta = 1;

    private int $filasMeta = 2;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var array<int, string> fila Excel → tipo_fila (cortes) */
    private array $filasCorte = [];

    public function __construct(
        private readonly MovCcostoReporteService $reporteService,
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

        $filas = $this->reporteService->aplanarFilas($resultado, $this->filtros, true);
        $columnas = $resultado['columnas'] ?? [];
        $totales = $resultado['totales'] ?? [];

        $this->debeHaber = ($this->filtros['formato'] ?? '') === MovCcostoListadoFiltros::FORMATO_DEBE_HABER;
        $celdas = count($columnas) + (count($columnas) > 1 ? 1 : 0);
        $this->cantColumnasExcel = 2 + max(1, $celdas) * ($this->debeHaber ? 2 : 1);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $partes = [
            'Empresas: '.$this->reporteService->formatearEmpresasTexto($this->filtros),
            'Período: '.$this->reporteService->formatearPeriodoTexto($this->filtros),
            $this->reporteService->formatearAgrupacionTexto($this->filtros),
        ];
        $centros = $this->reporteService->formatearCentrosTexto($this->filtros);
        if ($centros !== '') {
            $partes[] = $centros;
        }
        $partes[] = $this->reporteService->formatearInclusionAsientosTexto($this->filtros);
        $subtitulo = implode(' · ', $partes);

        $this->calcularFilasEncabezado($subtitulo, $totales);

        $this->filasCorte = [];
        foreach ($filas as $idx => $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? 'cuenta');
            if ($tipo !== 'cuenta') {
                $this->filasCorte[$this->filaPrimeraDatosExcel + $idx] = $tipo;
            }
        }

        return view('exports.contable.movccostoindex', [
            'filas' => $filas,
            'columnas' => $columnas,
            'formato' => $this->debeHaber ? MovCcostoListadoFiltros::FORMATO_DEBE_HABER : MovCcostoListadoFiltros::FORMATO_SALDO,
            'totales' => $totales,
            'titulo' => 'Totales por centro de costo',
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

        $filasMeta = 2; // título + Generado
        if (trim($subtitulo) !== '') {
            $filasMeta++;
        }
        if ((int) ($totales['cuentas'] ?? 0) > 0) {
            $filasMeta++;
        }

        $this->filasMeta = $filasMeta;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + ($this->debeHaber ? 2 : 1);
    }

    public function title(): string
    {
        return 'Totales por CC';
    }

    public function columnWidths(): array
    {
        $cols = ['A' => 14, 'B' => 38];
        for ($i = 3; $i <= $this->cantColumnasExcel; $i++) {
            $cols[Coordinate::stringFromColumnIndex($i)] = 16;
        }

        return $cols;
    }

    public function columnFormats(): array
    {
        $fmt = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;
        $out = ['A' => NumberFormat::FORMAT_TEXT];
        for ($i = 3; $i <= $this->cantColumnasExcel; $i++) {
            $out[Coordinate::stringFromColumnIndex($i)] = $fmt;
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = Coordinate::stringFromColumnIndex($this->cantColumnasExcel);

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($i = 1; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila)->applyFromArray([
                        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $ultimaCab = $this->filaCabecerasExcel + ($this->debeHaber ? 1 : 0);
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$ultimaCab)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                ]);

                $ultimaFila = $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    // Importes numéricos con #,##0.00 (regla export-excel-importes-cortes).
                    for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaFila; $fila++) {
                        for ($c = 3; $c <= $this->cantColumnasExcel; $c++) {
                            $celda = $sheet->getCell(Coordinate::stringFromColumnIndex($c).$fila);
                            $valor = $celda->getValue();
                            if ($valor !== null && $valor !== '' && is_numeric($valor)) {
                                $celda->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                    $rango = 'C'.$this->filaPrimeraDatosExcel.':'.$colUltima.$ultimaFila;
                    $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
                    $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $colores = [
                    'titulo' => null,
                    'total_nivel' => 'EAF2F8',
                    'total_empresa' => 'D6EAF8',
                    'total_general' => 'AED6F1',
                    'header_empresa' => 'E5E7E9',
                ];
                foreach ($this->filasCorte as $fila => $tipo) {
                    $estilo = ['font' => ['bold' => true]];
                    if (($colores[$tipo] ?? null) !== null) {
                        $estilo['fill'] = [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $colores[$tipo]],
                        ];
                    }
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray($estilo);
                }

                $sheet->freezePane('C'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
