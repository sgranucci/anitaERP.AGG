<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PerdidaPersonalReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents
{
    use Exportable;

    /** @param list<array<string, mixed>> $filas */
    public function __construct(
        private readonly array $filas,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly float $totalImporte,
    ) {}

    public function view(): View
    {
        return view('exports.caja.perdida_personal_reporte', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totalImporte' => $this->totalImporte,
            'logos' => EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect()),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 28, 'C' => 12, 'D' => 18, 'E' => 18,
            'F' => 18, 'G' => 12, 'H' => 28, 'I' => 14,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A4');
                $sheet->getStyle('A3:I3')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }
}
