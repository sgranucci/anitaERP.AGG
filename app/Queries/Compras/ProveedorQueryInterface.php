<?php

namespace App\Queries\Compras;

interface ProveedorQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function allQueryOrdenado(array $campos, $orden);
    public function traeProveedorporCodigo($codigo);
    public function traeProveedorporId($codigo);
}

