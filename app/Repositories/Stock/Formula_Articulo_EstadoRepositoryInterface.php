<?php

namespace App\Repositories\Stock;

interface Formula_Articulo_EstadoRepositoryInterface
{
    public function create(array $data, int $formula_articulo_id);

    public function creaEstado(int $formula_articulo_id, string $fecha, string $estado, int $usuario_id, ?string $observacion);

    public function leeHistoria(int $formula_articulo_id);
}
