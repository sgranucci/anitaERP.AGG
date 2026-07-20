<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorPlanoCuentaReporteService;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MayorPlanoCuentaExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly MayorPlanoCuentaReporteService $reporteService,
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
        $filas = $this->reporteService->aplanarFilas($resultado, $this->filtros, true);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $subtitulo = $this->reporteService->formatearPeriodoTexto($this->filtros)
            .' · '.$this->reporteService->formatearEmpresasTexto($this->filtros);

        return view('exports.contable.mayorplanocuentaindex', [
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => 'Mayor analítico por cuenta contable',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        // Importes con máscara neutra: cada PC los muestra según su config regional.
        $formato = ExcelFormatoNumero::preferenciaGlobal();

        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'J' => ExcelFormatoNumero::codigoColumna($formato, 4), // Cotización
            'L' => ExcelFormatoNumero::codigoColumna($formato, 2), // Debe
            'M' => ExcelFormatoNumero::codigoColumna($formato, 2), // Haber
            'N' => ExcelFormatoNumero::codigoColumna($formato, 2), // Saldo del mes
            'O' => ExcelFormatoNumero::codigoColumna($formato, 2), // Saldo ejercicio
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 10, 'C' => 6, 'D' => 14, 'E' => 10, 'F' => 14,
            'G' => 28, 'H' => 10, 'I' => 6, 'J' => 10, 'K' => 12,
            'L' => 12, 'M' => 12, 'N' => 12, 'O' => 12, 'P' => 6,
        ];
    }

    public function title(): string
    {
        return 'Mayor plano cuenta';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $col = 'A';
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(42);
                        $drawing->setCoordinates(chr(ord('A') + $idx).'1');
                        $drawing->setWorksheet($sheet);
                    }
                }
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
