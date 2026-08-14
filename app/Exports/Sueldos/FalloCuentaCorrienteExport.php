<?php

namespace App\Exports\Sueldos;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FalloCuentaCorrienteExport implements FromView, WithColumnWidths, WithEvents
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private readonly array $filas,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly array $resultado,
    ) {}

    public function view(): View
    {
        return view('exports.sueldos.fallo_reporte', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'resultado' => $this->resultado,
            'logos' => EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect()),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 28, 'C' => 12, 'D' => 18, 'E' => 18,
            'F' => 16, 'G' => 12, 'H' => 22, 'I' => 12, 'J' => 12, 'K' => 28,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A4');
                $sheet->getStyle('A3:K3')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }
}
