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

    private string $fechaReferencia;

    private ?int $listaprecioId;

    /** @var array<string, mixed> */
    private array $filtros;

    private string $busqueda;

    public function __construct(PrecioQueryInterface $precioQuery)
    {
        $this->precioQuery = $precioQuery;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(
        string $fechaReferencia,
        ?int $listaprecioId,
        array $filtros,
        string $busqueda = ''
    ): self {
        $this->fechaReferencia = $fechaReferencia;
        $this->listaprecioId = $listaprecioId;
        $this->filtros = $filtros;
        $this->busqueda = $busqueda;

        return $this;
    }

    public function view(): View
    {
        $precios = $this->precioQuery->leePrecios(
            $this->fechaReferencia,
            $this->listaprecioId,
            $this->filtros,
            $this->busqueda !== '' ? $this->busqueda : null,
            false
        );

        return view('exports.stock.precioindex', [
            'precios' => $precios,
            'fechaReferencia' => $this->fechaReferencia,
        ]);
    }
}
