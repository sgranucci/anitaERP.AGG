<?php

namespace App\Exports\Ventas;

use App\Models\Ventas\LocalVenta;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\LocalVentaListadoFiltros;
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

class LocalVentaListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

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
            $query = LocalVenta::query()
                ->select('local_venta.*')
                ->leftJoin('empresa', 'empresa.id', '=', 'local_venta.empresa_id')
                ->leftJoin('puntoventa', 'puntoventa.id', '=', 'local_venta.puntoventa_id')
                ->leftJoin('depmae', 'depmae.id', '=', 'local_venta.deposito_id')
                ->leftJoin('listaprecio', 'listaprecio.id', '=', 'local_venta.listaprecio_id')
                ->with([
                    'puntoventa:id,codigo,nombre',
                    'puntoventas:id,codigo,nombre',
                    'deposito:id,codigo,nombre',
                    'listaprecio:id,codigo,nombre',
                    'empresa:id,nombre',
                ])
                ->orderBy('local_venta.codigo');

            if (LocalVentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
                LocalVentaListadoFiltros::aplicar($query, $filtros);
            }

            $datas = $query->get();
            foreach ($datas as $row) {
                $row->nombreempresa = $row->empresa->nombre ?? '';
            }

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.ventas.local_ventaindex', [
                'datas' => $datas,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.ventas.local_ventaindex', [
            'datas' => collect(),
            'reservarFilaLogoExcel' => false,
        ]);
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
            'A' => 8,
            'B' => 14,
            'C' => 28,
            'D' => 28,
            'E' => 28,
            'F' => 22,
            'G' => 10,
        ];
    }

    public function title(): string
    {
        return 'Locales venta';
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
                        $drawing = new Drawing();
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
