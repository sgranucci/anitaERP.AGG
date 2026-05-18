<?php

namespace App\Repositories\Ventas;

interface UbicacionGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function listarParaSelect(?int $empresaId = null);

    public function tieneMesasAsociadas(int $id): bool;

    public function resolverId(?string $nombre, int $empresaId): ?int;
}
