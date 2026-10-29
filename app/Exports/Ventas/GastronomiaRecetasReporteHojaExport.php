<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Export\ExcelFormatoNumero;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GastronomiaRecetasReporteHojaExport implements FromArray, WithColumnFormatting, WithEvents, WithTitle
{
    use Exportable;

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     * @param  array<string, int>  $columnasNumericas  letra => decimales
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
        private readonly array $columnasNumericas = [],
        private readonly bool $resaltarTotales = false,
    ) {}

    public function title(): string
    {
        return $this->titulo;
    }

    public function array(): array
    {
        return array_merge([$this->encabezados], $this->filas);
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::preferenciaGlobal();
        $out = [];
        foreach ($this->columnasNumericas as $columna => $decimales) {
            $out[$columna] = ExcelFormatoNumero::codigoColumna($formato, $decimales);
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestColumn();
                $sheet->getStyle('A1:'.$ultima.'1')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:'.$ultima.'1');

                if (! $this->resaltarTotales) {
                    return;
                }

                $fila = 2;
                foreach ($this->filas as $datos) {
                    $texto = (string) ($datos[0] ?? '');
                    if (str_starts_with($texto, 'Total ')) {
                        $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->getFont()->setBold(true);
                    }
                    $fila++;
                }
            },
        ];
    }
}
