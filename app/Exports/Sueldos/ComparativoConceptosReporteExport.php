<?php

namespace App\Exports\Sueldos;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ComparativoConceptosReporteExport implements FromView, WithEvents
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private readonly array $resultado,
        private readonly string $titulo,
        private readonly string $subtitulo,
    ) {}

    public function view(): View
    {
        return view('exports.sueldos.comparativo_conceptos', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'logos' => EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect()),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A4');

                $lastCol = $sheet->getHighestColumn(3);
                $sheet->getStyle('A3:'.$lastCol.'3')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $formato = (string) ($this->resultado['formato'] ?? 'importe');
                $colCount = count($this->resultado['columnas'] ?? []);
                $muestraDif = ! empty($this->resultado['muestra_diferencia']);
                // A–H fijas; luego dinámicas; última = total
                $start = 9; // columna I
                $porCol = $formato === 'ambos' ? 2 : 1;
                $dyn = $colCount * $porCol + ($muestraDif ? $porCol : 0) + $porCol;
                $highestRow = (int) $sheet->getHighestRow();

                for ($i = 0; $i < $dyn; $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start + $i);
                    $sheet->getStyle($colLetter.'4:'.$colLetter.$highestRow)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                }
            },
        ];
    }
}
