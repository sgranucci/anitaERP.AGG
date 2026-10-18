<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MozoGastronomia;

interface MozoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all(?int $salaId = null);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeMozoGastronomia($filtros, bool $flPaginando = true);

    public function existeRegistro(): bool;

    public function consultaMozo(string $consulta, int $empresaId, bool $filtrarEmpresasAsignadas = false, ?int $salaId = null): string;

    public function findPorCodigo(string $codigo, int $empresaId, bool $filtrarEmpresasAsignadas = false, ?int $salaId = null): ?MozoGastronomia;

    public function findPorId(int $id, int $empresaId, ?int $salaId = null): ?MozoGastronomia;

    public function proximoCodigoLocal(int $empresaId): string;

    public function codigoExisteEnEmpresa(int $empresaId, string $codigo, ?int $ignorarId = null): bool;
}
