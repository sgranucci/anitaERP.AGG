<?php

namespace App\Exports\Stock;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;

class ContaduriaInventarioTotalesExport implements FromView, WithEvents, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $depositos
     * @param  list<array<string, mixed>>  $rubros
     */
    public function __construct(
        private readonly array $depositos,
        private readonly array $rubros,
        private readonly string $mes,
        private readonly string $empresaNombre,
    ) {}

    public function view(): View
    {
        return view('exports.stock.contaduria_inventario_totales', [
            'depositos' => $this->depositos,
            'rubros' => $this->rubros,
            'mes' => $this->mes,
            'empresa' => $this->empresaNombre,
        ]);
    }

    public function title(): string
    {
        return 'TOTAL';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestColumn();
                $filaFin = (int) $sheet->getHighestRow();
                $rubros = count($this->rubros);
                $colUltima = 2 + ($rubros * 2);

                $sheet->mergeCells('A1:'.$ultima.'1');
                $sheet->mergeCells('A2:'.$ultima.'2');
                $sheet->mergeCells('A3:'.$ultima.'3');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setName('Arial')->setSize(16);
                $sheet->getStyle('A2:A3')->getFont()->setBold(true)->setName('Arial')->setSize(12);
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->getStyle('A5:'.$ultima.'5')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                if ($filaFin >= 6) {
                    $importe = new Style();
                    $importe->getNumberFormat()->setFormatCode('#,##0.00');
                    for ($i = 0; $i < $rubros; $i++) {
                        $col = Coordinate::stringFromColumnIndex(3 + ($i * 2));
                        $sheet->duplicateStyle($importe, $col.'6:'.$col.$filaFin);
                    }
                    $colTotal = Coordinate::stringFromColumnIndex($colUltima);
                    $sheet->duplicateStyle($importe, $colTotal.'6:'.$colTotal.$filaFin);
                    $sheet->getStyle('A'.$filaFin.':'.$ultima.$filaFin)->getFont()->setBold(true);
                }

                $sheet->getColumnDimension('A')->setWidth(22);
                for ($i = 2; $i <= $colUltima; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($i % 2 === 0 ? 18 : 20);
                }
                $sheet->freezePane('A6');
            },
        ];
    }
}
