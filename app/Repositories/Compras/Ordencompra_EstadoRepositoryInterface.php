<?php

namespace App\Repositories\Compras;

interface Ordencompra_EstadoRepositoryInterface
{
    public function create(array $data, int $ordencompra_id): void;

    public function creaEstado(int $ordencompra_id, string $fecha, string $estado, int $usuario_id, string $observacion);
}
