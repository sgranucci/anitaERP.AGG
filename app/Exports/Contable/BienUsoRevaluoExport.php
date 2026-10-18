<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BienUsoRevaluoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    /**
     * @param  array{filas: list<array<string, mixed>>, totales: array<string, float>, meta?: array<string, mixed>}  $resultado
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private readonly array $resultado,
        private readonly array $filtros,
    ) {
    }

    public function view(): View
    {
        $filas = collect($this->resultado['filas'] ?? []);
        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            $filas->map(fn ($f) => (object) ['nombreempresa' => $f['nombre_empresa'] ?? ''])
        );

        return view('exports.contable.bien_uso_revaluoindex', [
            'filas' => $this->resultado['filas'] ?? [],
            'totales' => $this->resultado['totales'] ?? [],
            'filtros' => $this->filtros,
            'logosCabecera' => $logos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A4');
                $sheet->getStyle('A3:L3')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'name' => 'Arial',
                        'size' => 11,
                    ],
                ]);
            },
        ];
    }
}
