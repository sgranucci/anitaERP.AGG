<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\IvaVentasReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\IvaVentasListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IvaVentasListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
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

    public function __construct(
        private readonly IvaVentasReporteService $reporteService,
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
        $filas = $resultado['filas_display'] ?? $resultado['filas'] ?? [];
        $coleccionLogos = collect($filas)->map(fn (array $f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 4 : 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $subtitulo = 'Período: '.IvaVentasListadoFiltros::formatearPeriodoTexto($this->filtros)
            .' · Orden: '.IvaVentasListadoFiltros::formatearOrdenTexto($this->filtros)
            .' · '.IvaVentasListadoFiltros::formatearSubdiarioTexto($this->filtros);

        return view('exports.ventas.iva_ventasindex', [
            'resultado' => $resultado,
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => 'IVA VENTAS',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'clasificar_por_host' => ! empty($this->filtros['clasificar_por_host']),
            'para_pdf' => true,
            'puede_ver_venta' => false,
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [];
        foreach (range('A', $this->colUltima) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
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
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 28, 'C' => 14, 'D' => 10, 'E' => 8, 'F' => 16,
            'G' => 12, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 12, 'L' => 12,
            'M' => 12, 'N' => 12, 'O' => 12, 'P' => 12,
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'IVA ventas';
    }
}
