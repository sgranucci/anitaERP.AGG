<?php

namespace App\Exports\Ventas;

use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaConsumo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\Vianda\ViandaConsumoListadoFiltros;
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

class ViandaConsumoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filasMeta = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

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
        $filas = $this->consultarFilas();
        $totales = $this->totales($filas);
        $resumenCentroCosto = $this->resumenPorCentroCosto();
        $subtitulo = ViandaConsumoListadoFiltros::subtitulo(
            $this->filtros,
            $this->nombreEmpresa(),
            $this->nombreCentrocosto(),
        );

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        // título + generado + subtítulo + totales + contador
        $this->filasMeta = 2;
        if (trim($subtitulo) !== '') {
            $this->filasMeta++;
        }
        $this->filasMeta++; // totales
        $this->filasMeta++; // contador

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.viandaconsumoindex', [
            'filas' => $filas,
            'resumen_centrocosto' => $resumenCentroCosto,
            'totales' => $totales,
            'filtros' => $this->filtros,
            'titulo' => 'Reporte de viandas',
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => NumberFormat::FORMAT_NUMBER_00,
            'J' => NumberFormat::FORMAT_NUMBER_00,
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 8,
            'D' => 16,
            'E' => 26,
            'F' => 24,
            'G' => 22,
            'H' => 8,
            'I' => 12,
            'J' => 12,
            'K' => 10,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos) {
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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $filaFin = $this->filaTituloExcel + $this->filasMeta - 1;
                for ($fila = $this->filaTituloExcel + 1; $fila <= $filaFin; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                    $sheet->getStyle('A'.$fila.':'.self::COL_ULTIMA.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Viandas';
    }

    /**
     * @return \Illuminate\Support\Collection<int, ViandaConsumo>
     */
    private function consultarFilas()
    {
        $query = ViandaConsumo::query()
            ->with(['centrocosto', 'empresa', 'terminal']);

        $query = ViandaConsumoListadoFiltros::aplicar($query, $this->filtros);

        return ViandaConsumoListadoFiltros::aplicarOrden($query, $this->filtros)->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ViandaConsumo>  $filas
     * @return array{consumos:int,items:int,costo:float,venta:float}
     */
    private function totales($filas): array
    {
        return [
            'consumos' => $filas->count(),
            'items' => (int) $filas->sum('cantidad_items'),
            'costo' => (float) $filas->sum('total_costo'),
            'venta' => (float) $filas->sum('total_venta'),
        ];
    }

    /**
     * @return list<array{centrocosto:string,consumos:int,items:int,costo:float,venta:float}>
     */
    private function resumenPorCentroCosto(): array
    {
        $query = ViandaConsumo::query();
        ViandaConsumoListadoFiltros::aplicar($query, $this->filtros);

        $filas = $query->selectRaw('centrocosto_id, count(*) as consumos, sum(cantidad_items) as items, sum(total_costo) as costo, sum(total_venta) as venta')
            ->groupBy('centrocosto_id')
            ->orderByDesc('items')
            ->get();

        $ids = $filas->pluck('centrocosto_id')->filter()->map(fn ($v) => (int) $v)->all();
        $nombres = $ids === []
            ? collect()
            : Centrocosto::query()->whereIn('id', $ids)->pluck('nombre', 'id');

        return $filas->map(fn ($f) => [
            'centrocosto' => $f->centrocosto_id ? (string) ($nombres[(int) $f->centrocosto_id] ?? ('C.C. '.$f->centrocosto_id)) : 'Sin centro de costo',
            'consumos' => (int) $f->consumos,
            'items' => (int) $f->items,
            'costo' => (float) $f->costo,
            'venta' => (float) $f->venta,
        ])->all();
    }

    private function nombreEmpresa(): ?string
    {
        $id = (int) ($this->filtros['empresa_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return app(EmpresaRepositoryInterface::class)->find($id)?->nombre;
    }

    private function nombreCentrocosto(): ?string
    {
        $id = (int) ($this->filtros['centrocosto_id'] ?? 0);

        return $id > 0 ? Centrocosto::query()->where('id', $id)->value('nombre') : null;
    }
}
