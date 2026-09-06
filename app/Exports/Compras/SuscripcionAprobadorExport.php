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
 * Grilla de aprobadores de suscripciones (gerente por centro de costo) en XLS/CSV.
 */
class SuscripcionAprobadorExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    /** @var Collection<int, array<string, mixed>> */
    private Collection $filas;

    /** @var array<string, mixed> */
    private array $filtros = [];

    public function __construct()
    {
        $this->filas = collect();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(Collection $filas, array $filtros = []): self
    {
        $this->filas = $filas;
        $this->filtros = $filtros;

        return $this;
    }

    public function view(): View
    {
        return view('exports.compras.suscripcion_aprobador', [
            'filas' => $this->filas,
            'filtros' => $this->filtros,
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
        return 'Aprobadores';
    }
}
