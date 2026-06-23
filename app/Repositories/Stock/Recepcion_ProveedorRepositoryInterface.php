<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

interface Recepcion_ProveedorRepositoryInterface
{
    public function create(array $data): Recepcion_Proveedor;

    public function update(array $data, int $id): bool;

    public function find(int $id): Recepcion_Proveedor;

    public function leeRecepciones(array|string|null $filtros, bool $paginar = true);

    public function siguienteNumero(int $empresaId): int;

    /** Renumera borrador si el COM ya existe en Anita (otra empresa). Numerador único global. */
    public function renumerarBorradorSiColisionaGlobal(int $id): int;
}
