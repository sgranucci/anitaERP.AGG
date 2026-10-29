<?php

namespace App\Repositories\Produccion;

interface MovimientoOrdentrabajoRepositoryInterface extends RepositoryInterface
{

    public function estadoEnum();
    public function all();

    public function leeListado(array $filtros, bool $paginar);

    public function cortesListado(array $filtros): array;

}

