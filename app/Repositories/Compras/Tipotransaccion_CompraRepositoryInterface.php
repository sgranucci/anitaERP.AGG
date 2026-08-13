<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tipotransaccion_Compra;
use Illuminate\Support\Collection;

interface Tipotransaccion_CompraRepositoryInterface extends RepositoryInterface
{
    public function all($operacion, $estado = null);

    /**
     * @return Collection<int, Tipotransaccion_Compra>
     */
    public function listarParaConsulta(?string $consulta = null, ?int $centrocostoId = null): Collection;

    public function findPorAbreviaturaFiltrado(string $abreviatura, ?int $centrocostoId = null): ?Tipotransaccion_Compra;
}
