<?php

namespace App\Exports\Caja;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class RendicionEstacionamientoCajaExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private Collection $rendiciones,
    ) {
    }

    public function view(): View
    {
        return view('caja.rendicionestacionamiento.listado', [
            'rendiciones' => $this->rendiciones,
        ]);
    }

    public function title(): string
    {
        // Excel: máximo 31 caracteres en el nombre de hoja.
        return 'Rend. estacionamiento caja';
    }
}
