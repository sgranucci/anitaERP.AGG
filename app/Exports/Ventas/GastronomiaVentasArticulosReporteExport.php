<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaVentasArticulosReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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

class GastronomiaVentasArticulosReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** Congela Artículo y Descripción (A y B): freeze arranca en C. */
    private const COL_FREEZE = 'C';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = 'Ventas de artículos';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'L';

    /** @var array<string, mixed> */
    private array $resultado = [];

    public function __construct(
        private readonly GastronomiaVentasArticulosReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo, string $subtitulo, string $empresaNombre = '', bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = trim($empresaNombre);
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $this->resultado = $this->reporteService->generar($this->filtros);
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }

        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->filaTituloExcel + $filasMeta;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.gastronomia_ventas_articulos_reporteindex', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        // C–L son numéricas (costos, precios, cantidades e importes): máscara neutra sumable/adaptable.
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => $codigo, 'D' => $codigo, 'E' => $codigo, 'F' => $codigo,
            'G' => $codigo, 'H' => $codigo, 'I' => $codigo, 'J' => $codigo,
            'K' => $codigo, 'L' => $codigo,
        ];
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 32,
            'C' => 10,
            'D' => 10,
            'E' => 12,
            'F' => 12,
            'G' => 14,
            'H' => 12,
            'I' => 12,
            'J' => 14,
            'K' => 14,
            'L' => 14,
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
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                if (trim($this->subtitulo) !== '') {
                    $filaSub = $filaTit + 1;
                    $sheet->mergeCells('A'.$filaSub.':'.$colUltima.$filaSub);
                }

                $numFilas = count($this->resultado['filas'] ?? []);
                $filaUltima = $this->filaPrimeraDatosExcel + max(0, $numFilas - 1);
                if ($numFilas > 0 && ($this->resultado['totales'] ?? []) !== []) {
                    $filaUltima++;
                }
                if ($filaUltima >= $this->filaCabecerasExcel) {
                    $sheet->getStyle('C'.$this->filaCabecerasExcel.':L'.$filaUltima)->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);
                }

                $sheet->getStyle('A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Ventas artículos';
    }
}
