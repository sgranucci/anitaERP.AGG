<?php

namespace App\Exports\Stock;

use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\ArticuloListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArticuloExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private ArticuloRepositoryInterface $articuloRepository;

    private function colUltima(): string
    {
        return ArticuloListadoFiltros::filtroEmpresaActivo() ? 'I' : 'H';
    }

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(ArticuloRepositoryInterface $articuloRepository)
    {
        $this->articuloRepository = $articuloRepository;
    }

    public function view(): View
    {
        $articulos = $this->articuloRepository->leeArticulo($this->filtros, false);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        // logo? + título + generado → thead
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $filaGenerado = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $filaGenerado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.stock.articuloindex', [
            'articulos' => $articulos,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $formats = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
        ];
        if (ArticuloListadoFiltros::filtroEmpresaActivo()) {
            $formats['I'] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
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
        $widths = [
            'A' => 14,
            'B' => 40,
            'C' => 14,
            'D' => 18,
            'E' => 18,
            'F' => 14,
            'G' => 14,
            'H' => 12,
        ];
        if (ArticuloListadoFiltros::filtroEmpresaActivo()) {
            $widths['I'] = 16;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

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

                $colUltima = $this->colUltima();
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
                ]);

                $filaGen = $filaTit + 1;
                $sheet->mergeCells('A'.$filaGen.':'.$colUltima.$filaGen);
                $sheet->getStyle('A'.$filaGen.':'.$colUltima.$filaGen)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Reporte de Artículos';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros)
    {
        $this->filtros = $filtros;

        return $this;
    }
}
