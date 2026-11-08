<?php

namespace App\Exports\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FlashCrownDiaExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    /**
     * @param  list<FlashCrown>  $filas
     */
    public function __construct(
        private readonly array $filas,
        private readonly string $fecha,
        private readonly bool $csv = false,
    ) {}

    public function view(): View
    {
        $enriquecidas = [];
        foreach ($this->filas as $fila) {
            $arr = $fila->toArray();
            $arr['nombre_sala'] = $fila->sala->nombre ?? '';
            $arr['codigo_sala'] = $fila->sala->codigo ?? '';
            $arr['metricas'] = FlashCrownCalculoSupport::metricasDerivadas($arr);
            $arr['combined_anterior'] = FlashCrownCalculoSupport::combinedAnteriorAnio(
                (int) $fila->sala_id,
                $this->fecha
            );
            $enriquecidas[] = $arr;
        }

        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            collect($this->filas)->map(function (FlashCrown $f) {
                return (object) [
                    'nombreempresa' => $f->empresa->nombre ?? (string) config('app.empresa'),
                ];
            })
        );

        return view('exports.caja.flash_crown_dia', [
            'filas' => $enriquecidas,
            'fecha' => $this->fecha,
            'logos' => $logos,
            'csv' => $this->csv,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A4');
                $headerRow = 3;
                $lastCol = $sheet->getHighestColumn($headerRow);
                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
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
