<?php

namespace App\Exports\Caja;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RendicionGastronomiaCajaExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private Collection $rendiciones,
    ) {
    }

    public function view(): View
    {
        return view('caja.rendiciongastronomia.listado', [
            'rendiciones' => $this->rendiciones,
        ]);
    }
}
