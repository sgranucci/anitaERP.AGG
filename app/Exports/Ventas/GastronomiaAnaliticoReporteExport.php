<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaAnaliticoReporteService;
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

class GastronomiaAnaliticoReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'X';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = 'Reporte analítico gastronomía';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    public function __construct(
        private readonly GastronomiaAnaliticoReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo, string $subtitulo, string $empresaNombre = ''): self
    {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = trim($empresaNombre);

        return $this;
    }

    public function view(): View
    {
        $this->resultado = $this->reporteService->generar($this->filtros, false);
        $filas = $this->resultado['filas'];
        $coleccionLogo = $filas
            ->filter(static fn ($f) => ($f->tipo_fila ?? 'detalle') !== 'header_empresa')
            ->map(static fn ($f) => (object) [
                'nombreempresa' => trim((string) ($f->nombreempresa ?? $f->sala ?? '')),
            ])
            ->filter(static fn ($r) => $r->nombreempresa !== '')
            ->unique('nombreempresa')
            ->values();
        if ($coleccionLogo->isEmpty() && $this->empresaNombre !== '') {
            $coleccionLogo = collect([(object) ['nombreempresa' => $this->empresaNombre]]);
        }
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }

        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->filaTituloExcel + $filasMeta;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.gastronomia_analitico_reporteindex', [
            'resultado' => $this->resultado,
            'filas' => $filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_NUMBER_00,
            'O' => NumberFormat::FORMAT_NUMBER_00,
            'P' => NumberFormat::FORMAT_NUMBER_00,
            'Q' => NumberFormat::FORMAT_NUMBER_00,
            'U' => NumberFormat::FORMAT_TEXT,
            'V' => NumberFormat::FORMAT_TEXT,
            'W' => NumberFormat::FORMAT_TEXT,
            'X' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 12, 'C' => 12, 'D' => 18, 'E' => 10, 'F' => 10,
            'G' => 14, 'H' => 10, 'I' => 18, 'J' => 10, 'K' => 12, 'L' => 28,
            'M' => 10, 'N' => 10, 'O' => 10, 'P' => 10, 'Q' => 10, 'R' => 16,
            'S' => 14, 'T' => 22, 'U' => 8, 'V' => 8, 'W' => 6, 'X' => 6,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
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
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Analítico gastro';
    }
}
