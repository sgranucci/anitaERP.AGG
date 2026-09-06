<?php

namespace App\Exports\Compras;

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
 * Listado de suscripciones en XLS o CSV, con la misma grilla que se ve en pantalla.
 */
class SuscripcionListadoExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    /** @var Collection<int, \App\Models\Compras\Ordencompra> */
    private Collection $filas;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, float|int> */
    private array $kpis = [];

    public function __construct()
    {
        $this->filas = collect();
    }

    /**
     * @param  Collection<int, \App\Models\Compras\Ordencompra>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, float|int>  $kpis
     */
    public function parametros(Collection $filas, array $filtros, array $kpis): self
    {
        $this->filas = $filas;
        $this->filtros = $filtros;
        $this->kpis = $kpis;

        return $this;
    }

    public function view(): View
    {
        return view('exports.compras.suscripcion_listado', [
            'filas' => $this->filas,
            'filtros' => $this->filtros,
            'kpis' => $this->kpis,
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
        return 'Suscripciones';
    }
}
