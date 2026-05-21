<?php

namespace App\Repositories\Stock;

interface Tipotransaccion_StockRepositoryInterface extends RepositoryInterface
{
    public function all($operacion = null, $estado = null);

    public function findIdPorAbreviatura(string $abreviatura): int;

    public function resolveIdFromLegacy(int $id): int;
}
