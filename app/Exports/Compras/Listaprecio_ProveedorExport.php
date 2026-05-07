<?php

namespace App\Exports\Compras;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;

class Listaprecio_ProveedorExport implements FromView
{
    use Exportable;

    private $listaprecioProveedorQuery;

    private $busqueda;

    public function __construct($listaprecioProveedorQuery)
    {
        $this->listaprecioProveedorQuery = $listaprecioProveedorQuery;
    }

    public function view(): View
    {
        $listas = $this->listaprecioProveedorQuery->leeListas($this->busqueda, false);

        return view('exports.compras.listaprecio_proveedorindex', ['listas' => $listas]);
    }

    public function parametros($busqueda)
    {
        $this->busqueda = $busqueda;

        return $this;
    }
}
