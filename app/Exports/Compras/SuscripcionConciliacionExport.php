<?php

namespace App\Exports\Compras;

use App\Models\Compras\Suscripcion_Conciliacion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Los cargos de un período con la orden que los explica, en XLS o CSV.
 *
 * Es el papel de trabajo de la conciliación: lo que Administración archiva y lo que se
 * manda a quien pregunta por qué se pagó tal cargo.
 */
class SuscripcionConciliacionExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    /** @var Collection<int, \App\Models\Compras\Suscripcion_Cargo> */
    private Collection $cargos;

    private ?Suscripcion_Conciliacion $conciliacion = null;

    /** @var array<string, mixed> */
    private array $resumen = [];

    public function __construct()
    {
        $this->cargos = collect();
    }

    /**
     * @param  Collection<int, \App\Models\Compras\Suscripcion_Cargo>  $cargos
     * @param  array<string, mixed>  $resumen
     */
    public function parametros(Suscripcion_Conciliacion $conciliacion, Collection $cargos, array $resumen): self
    {
        $this->conciliacion = $conciliacion;
        $this->cargos = $cargos;
        $this->resumen = $resumen;

        return $this;
    }

    public function view(): View
    {
        return view('exports.compras.suscripcion_conciliacion', [
            'conciliacion' => $this->conciliacion,
            'cargos' => $this->cargos,
            'resumen' => $this->resumen,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            2 => [
                'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Conciliacion '.(string) ($this->conciliacion->periodo ?? '');
    }
}
