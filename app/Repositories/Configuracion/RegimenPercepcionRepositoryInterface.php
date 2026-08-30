<?php

declare(strict_types=1);

namespace App\Repositories\Configuracion;

interface RegimenPercepcionRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeRegimenPercepcion($filtros, bool $paginar = false);

    /**
     * @param  list<int|string|null>  $empresaIds
     * @param  list<int|string|null>  $cuentaIds
     * @param  list<int|string|null>  $creousuarioIds
     */
    public function sincronizarCuentas(int $regimenId, array $empresaIds, array $cuentaIds, array $creousuarioIds): void;
}
