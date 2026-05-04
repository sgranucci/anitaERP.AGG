<?php

namespace App\Exports\Compras;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;

class RequisicionExport implements FromView
{
    use Exportable;

    private $requisicionQuery;
    private $busqueda;

    public function __construct($requisicionquery)
    {
        $this->requisicionQuery = $requisicionquery;
    }

    public function view(): View
    {
        $requisicion = $this->requisicionQuery->leeRequisicion($this->busqueda, false);

        return view('exports.compras.requisicionindex', ['requisicion' => $requisicion]);
    }

    public function parametros($busqueda)
    {
        $this->busqueda = $busqueda;

        return $this;
    }
}
