<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaDescuentoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\GastronomiaDescuentoReporteExcelLayout;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GastronomiaDescuentoReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = '';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $soloTotales = false;

    /** @var array<string, mixed> */
    private array $resultado = [];

    private bool $hayFilaLogos = false;

    private GastronomiaDescuentoReporteExcelLayout $layout;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
    ) {
        $this->layout = new GastronomiaDescuentoReporteExcelLayout(false, 2);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(
        array $filtros,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
        bool $soloTotales = false,
        ?array $resultadoPrecalculado = null,
    ): self {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = $empresaNombre;
        $this->soloTotales = $soloTotales;
        $this->resultado = $resultadoPrecalculado ?? $this->reporteService->generar($filtros);

        return $this;
    }

    public function view(): View
    {
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        if ($this->soloTotales) {
            return view('exports.ventas.gastronomia_descuento_reporte_totales', [
                'resultado' => $this->resultado,
                'empresa_nombre' => $this->empresaNombre,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $bloques = $this->resultado['bloques'] ?? [];
        $filasMeta = GastronomiaDescuentoReporteExcelLayout::contarFilasMeta(
            $this->subtitulo,
            count($bloques) > 0,
            count($bloques) > 0,
        );
        $this->layout = new GastronomiaDescuentoReporteExcelLayout(
            $this->hayFilaLogos,
            $filasMeta,
            count($bloques) > 0 ? 1 : 0,
            1,
        );
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->layout->filaInicioMeta() + 2
            : 0;

        return view('exports.ventas.gastronomia_descuento_reporteindex', [
            'resultado' => $this->resultado,
            'filtros' => $this->filtros,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'empresa_nombre' => $this->empresaNombre,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'bloques' => $bloques,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->soloTotales) {
            return [];
        }

        return [
            $this->layout->filaCabecerasExcel() => [
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

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 34,
            'C' => 12,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->soloTotales) {
                    $this->aplicarEstilosTotales($sheet);

                    return;
                }

                $this->layout->aplicarLogos($sheet, $this->rutasLogosExcel);
                $this->layout->aplicarMetaEncabezado($sheet, self::COL_ULTIMA, $this->filaSubtituloExcel);
                $this->layout->aplicarEstiloThead($sheet, self::COL_ULTIMA);
                $this->layout->congelarDebajoThead($sheet);

                $filaSeccion = $this->layout->filaFinMeta() + 1;
                if (($this->resultado['bloques'] ?? []) !== []) {
                    $sheet->mergeCells('A'.$filaSeccion.':'.self::COL_ULTIMA.$filaSeccion);
                    $sheet->getRowDimension($filaSeccion)->setRowHeight(22);
                    $sheet->getStyle('A'.$filaSeccion.':'.self::COL_ULTIMA.$filaSeccion)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'name' => 'Arial',
                            'color' => ['rgb' => '17202A'],
                        ],
                    ]);
                }

                $sheet->getStyle('B'.$this->layout->filaPrimeraDatosExcel().':B'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true);
            },
        ];
    }

    public function title(): string
    {
        if ($this->soloTotales) {
            return 'TOTALES';
        }

        $bloques = $this->resultado['bloques'] ?? [];
        if (count($bloques) === 1) {
            return self::sanitizarNombreHoja((string) ($bloques[0]['nombre'] ?? 'Descuentos'));
        }

        return 'Descuentos';
    }

    public static function sanitizarNombreHoja(string $nombre): string
    {
        $nombre = preg_replace('/[\\\\\\/*\\[\\]:?]/', ' ', $nombre) ?? 'Hoja';
        $nombre = trim($nombre);

        return mb_substr($nombre !== '' ? $nombre : 'Hoja', 0, 31);
    }

    private function aplicarEstilosTotales(Worksheet $sheet): void
    {
        if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
            $sheet->getRowDimension(1)->setRowHeight(54);
        }

        $sheet->mergeCells('B3:E3');
        $sheet->getStyle('B3:E3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $sheet->mergeCells('B4:E4');
        $sheet->getStyle('B4:E4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $sheet->mergeCells('B5:E5');
        $sheet->getStyle('B5:E5')->applyFromArray([
            'font' => ['size' => 12, 'name' => 'Arial'],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $sheet->getStyle('B7:D7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '85C1E9'],
            ],
        ]);
        $sheet->freezePane('A8');
    }
}
