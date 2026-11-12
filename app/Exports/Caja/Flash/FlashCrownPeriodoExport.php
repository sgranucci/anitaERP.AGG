<?php

namespace App\Exports\Caja\Flash;

use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use App\Support\Caja\Flash\FlashCrownPerfilVistaSupport;
use App\Support\Export\ExcelNumericCellsSupport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FlashCrownPeriodoExport implements FromView, WithEvents
{
    /**
     * @param  Collection<int, \App\Models\Caja\Flash\FlashCrown>  $filas
     */
    public function __construct(
        private readonly Collection $filas,
        private readonly Carbon $desde,
        private readonly Carbon $hasta,
        private readonly string $perfil = FlashCrownPerfilVistaSupport::COMPLETA,
    ) {}

    public function view(): View
    {
        $rows = [];
        foreach ($this->filas as $fila) {
            $arr = $fila->toArray();
            $arr['nombre_sala'] = $fila->sala->nombre ?? '';
            $arr['codigo_sala'] = $fila->sala->codigo ?? '';
            $arr['fecha_fmt'] = $fila->fecha->format('d/m/Y');
            $arr['metricas'] = FlashCrownCalculoSupport::metricasDerivadas($arr);
            $arr['combined_anterior'] = FlashCrownCalculoSupport::combinedAnteriorAnio(
                (int) $fila->sala_id,
                $fila->fecha->format('Y-m-d')
            );
            $rows[] = $arr;
        }

        return view('exports.caja.flash_crown_periodo', [
            'filas' => $rows,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'perfil' => FlashCrownPerfilVistaSupport::normalizar($this->perfil),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A3');
                $sheet->getStyle('A2:'.$sheet->getHighestColumn(2).'2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                    ],
                ]);
                // A fecha, B sala — texto; resto numérico.
                ExcelNumericCellsSupport::forzarRangoDatos($sheet, 3, 3, [], '#,##0.00');
            },
        ];
    }
}
