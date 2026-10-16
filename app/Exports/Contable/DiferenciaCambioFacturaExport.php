<?php

namespace App\Exports\Contable;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DiferenciaCambioFacturaExport implements FromView, ShouldAutoSize
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private readonly Collection $filas,
        private readonly array $filtros,
    ) {}

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('exports.contable.diferencia_cambio_facturaindex', [
            'filas' => $this->filas,
            'filtros' => $this->filtros,
        ]);
    }
}
