<?php

namespace App\Exports\Stock;

use App\Models\Stock\Articulo;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\ArticuloFerliListadoFiltros;
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

class ArticuloFerliListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'F';

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $filtros = is_array($this->filtros) ? $this->filtros : [];
            $datas = self::leeArticulos($filtros);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            // logo? + título + generado → thead
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->filaTituloExcel + 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.stock.producto_ferliindex', [
                'datas' => $datas,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.stock.producto_ferliindex', [
            'datas' => collect(),
            'reservarFilaLogoExcel' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, Articulo>
     */
    public static function leeArticulos(array $filtros)
    {
        $query = Articulo::query()
            ->select(
                'articulo.id as id',
                'articulo.sku as stkm_articulo',
                'articulo.descripcion as stkm_desc',
                'unidadmedida.nombre as stkm_unidad_medida',
                'categoria.nombre as stkm_agrupacion',
                'mventa.nombre as stkm_marca',
                'linea.nombre as stkm_linea',
                'articulo.usoarticulo_id',
                'articulo.nofactura'
            )
            ->leftJoin('categoria', 'articulo.categoria_id', '=', 'categoria.id')
            ->leftJoin('unidadmedida', 'articulo.unidadmedida_id', '=', 'unidadmedida.id')
            ->leftJoin('mventa', 'articulo.mventa_id', '=', 'mventa.id')
            ->leftJoin('linea', 'articulo.linea_id', '=', 'linea.id')
            ->orderBy('articulo.sku');

        ArticuloFerliListadoFiltros::aplicar($query, $filtros);

        return $query->get();
    }

    public function columnFormats(): array
    {
        if ($this->flDesdeIndex) {
            $cols = [];
            foreach (range('A', self::COL_ULTIMA) as $c) {
                $cols[$c] = NumberFormat::FORMAT_TEXT;
            }

            return $cols;
        }

        return [];
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->flDesdeIndex) {
            return [
                $this->filaCabecerasExcel => [
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ],
            ];
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 36,
            'C' => 18,
            'D' => 16,
            'E' => 16,
            'F' => 14,
        ];
    }

    public function title(): string
    {
        return 'Artículos';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

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
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()
                    ->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $filaGen = $this->filaTituloExcel + 1;
                if ($filaGen < $this->filaCabecerasExcel) {
                    $sheet->mergeCells('A'.$filaGen.':'.$colUltima.$filaGen);
                    $sheet->getStyle('A'.$filaGen)->getFont()
                        ->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
