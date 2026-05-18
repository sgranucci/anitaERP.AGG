<?php

namespace App\Repositories\Ventas;

interface DescuentoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;

    public function consultaDescuento(string $consulta): string;

    public function findPorCodigo(string $codigo): ?\App\Models\Ventas\DescuentoGastronomia;
}
