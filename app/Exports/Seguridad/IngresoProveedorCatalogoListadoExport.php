<?php

namespace App\Exports\Seguridad;

use App\Support\Seguridad\IngresoProveedorCatalogoListadoFiltros;
use App\Support\Seguridad\IngresoProveedorCatalogoSupport;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IngresoProveedorCatalogoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'D';

    private string $tipo = 'motivo';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(string $tipo, array $filtros): self
    {
        $this->tipo = $tipo;
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $def = IngresoProveedorCatalogoSupport::def($this->tipo);
        $datas = collect();
        if ($this->flDesdeIndex) {
            $query = IngresoProveedorCatalogoSupport::modelo($this->tipo)::query();
            if (IngresoProveedorCatalogoListadoFiltros::tieneCriteriosAplicados($this->filtros)) {
                IngresoProveedorCatalogoListadoFiltros::aplicar($query, $this->filtros);
            }
            $datas = $query->orderBy('nombre')->get();
        }

        return view('exports.seguridad.ingreso_proveedor_catalogoindex', [
            'datas' => $datas,
            'titulo' => $def['titulo'],
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }
        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

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
            'A' => 8,
            'B' => 14,
            'C' => 40,
            'D' => 10,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }
                $sheet = $event->sheet->getDelegate();
                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
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
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        $def = IngresoProveedorCatalogoSupport::TIPOS[$this->tipo] ?? null;

        return $def['titulo'] ?? 'Catálogo seguridad';
    }
}
