<?php

namespace App\Exports\Stock;

use App\Queries\Stock\PrecioQueryInterface;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;

class PrecioExport implements FromView
{
    use Exportable;

    private PrecioQueryInterface $precioQuery;

    /** @var array<string, mixed> */
    private array $filtros;

    public function __construct(PrecioQueryInterface $precioQuery)
    {
        $this->precioQuery = $precioQuery;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;

        return $this;
    }

    public function view(): View
    {
        $precios = $this->precioQuery->leePrecios($this->filtros, false);
        $fechaReferencia = (string) ($this->filtros['fecha_vigencia'] ?? date('Y-m-d'));

        return view('exports.stock.precioindex', [
            'precios' => $precios,
            'fechaReferencia' => $fechaReferencia,
        ]);
    }
}
