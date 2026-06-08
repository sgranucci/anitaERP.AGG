<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MozoGastronomia;

interface MozoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;

    public function consultaMozo(string $consulta, int $empresaId, bool $filtrarEmpresasAsignadas = false): string;

    public function findPorCodigo(string $codigo, int $empresaId, bool $filtrarEmpresasAsignadas = false): ?MozoGastronomia;
}
