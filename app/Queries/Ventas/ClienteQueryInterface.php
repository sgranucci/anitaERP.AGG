<?php

namespace App\Queries\Ventas;

interface ClienteQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function traeClienteporCodigo($codigo);
    public function traeClienteporId($codigo);
    public function allQueryCargaPedido(array $campos);
}

