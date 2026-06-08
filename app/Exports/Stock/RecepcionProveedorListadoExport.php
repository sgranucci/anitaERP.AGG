<?php

namespace App\Exports\Stock;

use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecepcionProveedorListadoExport implements FromView, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
    ) {
    }

    /** @param array<string, mixed>|string|null $filtros */
    public function parametros($filtros): self
    {
        $this->filtros = is_array($filtros) ? $filtros : ['filtro_valor' => (string) $filtros];
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $datas = $this->repository->leeRecepciones($this->filtros, false);

        return view('exports.stock.recepcion_proveedorindex', [
            'datas' => $datas,
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function title(): string
    {
        return 'Recepciones proveedor';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 12, 'D' => 10, 'E' => 10,
            'F' => 28, 'G' => 22, 'H' => 12, 'I' => 10, 'J' => 30,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            2 => ['font' => ['bold' => true, 'color' => ['rgb' => '17202A']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '85C1E9']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
                $sheet->freezePane('A3');
            },
        ];
    }
}
