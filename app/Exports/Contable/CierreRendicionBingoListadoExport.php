<?php

namespace App\Exports\Contable;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CierreRendicionBingoListadoExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private Collection $rendiciones,
    ) {
    }

    public function view(): View
    {
        return view('contable.cierre_rendicion_bingo.listado', [
            'rendiciones' => $this->rendiciones,
        ]);
    }
}
