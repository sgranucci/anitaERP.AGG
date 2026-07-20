<?php

namespace App\Repositories\Sueldos;

interface Liquidacion_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeLiquidacion($filtros, $flPaginando = null);

    public function proximoNumero(int $empresaId): int;

    public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null);
}
