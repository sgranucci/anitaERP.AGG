<?php

namespace App\Exports\Caja;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RendicionBingoCajaExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private Collection $rendiciones,
    ) {
    }

    public function view(): View
    {
        return view('caja.rendicionbingo.listado', [
            'rendiciones' => $this->rendiciones,
        ]);
    }
}
