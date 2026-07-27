<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaVentaHoraReporteService;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class GastronomiaVentaHoraReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $tituloReporte = 'Venta hora por hora';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaTitulo = 1;

    private int $filaCabeceras = 5;

    private int $filaPrimeraDatos = 6;

    private string $colUltima = 'AB';

    private int $cantidadColumnas = 28;

    /** @var list<string> */
    private array $rutasLogos = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    public function __construct(
        private readonly GastronomiaVentaHoraReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(
        array $filtros,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
        bool $esCsv = false,
    ): self {
        $this->filtros = $filtros;
        $this->tituloReporte = $titulo;
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
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogos) > 0;

        $this->cantidadColumnas = 2 + count($this->resultado['horas'] ?? []) + 2;
        $this->colUltima = Coordinate::stringFromColumnIndex($this->cantidadColumnas);
        $this->filaTitulo = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabeceras = $this->filaTitulo + 4;
        $this->filaPrimeraDatos = $this->filaCabeceras + 1;

        return view('exports.ventas.gastronomia_venta_hora_reporteindex', [
            'resultado' => $this->resultado,
            'titulo' => $this->tituloReporte,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
            'cantidadColumnas' => $this->cantidadColumnas,
        ]);
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $formatos = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];

        for ($indice = 3; $indice <= $this->cantidadColumnas; $indice++) {
            $formatos[Coordinate::stringFromColumnIndex($indice)] = $formato;
        }

        return $formatos;
    }

    public function columnWidths(): array
    {
        $anchos = ['A' => 12, 'B' => 14];
        for ($indice = 3; $indice <= $this->cantidadColumnas; $indice++) {
            $anchos[Coordinate::stringFromColumnIndex($indice)] = $indice > $this->cantidadColumnas - 2 ? 16 : 13;
        }

        return $anchos;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabeceras => [
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
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogos as $indice => $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX(6 + $indice * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                for ($fila = $this->filaTitulo; $fila < $this->filaCabeceras; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$this->colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaTitulo.':'.$this->colUltima.$this->filaTitulo)
                    ->applyFromArray([
                        'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                $sheet->getRowDimension($this->filaTitulo)->setRowHeight(28);

                $sheet->getStyle('A'.($this->filaTitulo + 1).':'.$this->colUltima.($this->filaCabeceras - 1))
                    ->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                $sheet->getStyle('A'.$this->filaCabeceras.':'.$this->colUltima.$this->filaCabeceras)
                    ->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    ]);

                $sheet->freezePane('C'.$this->filaPrimeraDatos);
            },
        ];
    }

    public function title(): string
    {
        return 'Venta por hora';
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}
