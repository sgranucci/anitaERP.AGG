<?php

namespace App\Exports\Ventas;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Excel guía suburbano Ferli: mismo layout que lista_control Anita / controlrem.
 *
 * @phpstan-type Payload array{
 *   titulo: string,
 *   encabezados: list<string>,
 *   filas: list<list<string|int|float>>,
 *   total: list<string|int|float>
 * }
 */
class CotGuiaSuburbanoExport implements FromArray, ShouldAutoSize, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'U';

    /** @param Payload $payload */
    public function __construct(private array $payload) {}

    public function title(): string
    {
        return 'Control remitos';
    }

    /** @return list<list<string|int|float>> */
    public function array(): array
    {
        $rows = [
            [$this->payload['titulo']],
            $this->payload['encabezados'],
        ];

        foreach ($this->payload['filas'] as $fila) {
            $rows[] = $fila;
        }

        if (($this->payload['filas'] ?? []) !== []) {
            $rows[] = $this->payload['total'];
        }

        return $rows;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 10,
            'C' => 6,
            'D' => 18,
            'E' => 32,
            'F' => 30,
            'G' => 28,
            'H' => 8,
            'I' => 16,
            'J' => 16,
            'K' => 14,
            'L' => 8,
            'M' => 8,
            'N' => 8,
            'O' => 8,
            'P' => 10,
            'Q' => 12,
            'R' => 18,
            'S' => 28,
            'T' => 14,
            'U' => 24,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                $sheet->getStyle('A1')->getFont()
                    ->setName('Arial')->setSize(12)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle('A2:'.self::COL_ULTIMA.'2')->getFont()
                    ->setName('Arial')->setSize(10)->setBold(true);

                $ultimaFila = 2 + count($this->payload['filas']);
                if (($this->payload['filas'] ?? []) !== []) {
                    $ultimaFila++; // total
                    $sheet->getStyle('A'.$ultimaFila.':Q'.$ultimaFila)->getFont()
                        ->setName('Arial')->setBold(true);
                }

                $sheet->freezePane('A3');
            },
        ];
    }
}
