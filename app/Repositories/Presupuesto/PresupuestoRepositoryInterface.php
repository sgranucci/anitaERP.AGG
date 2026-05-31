<?php

namespace App\Repositories\Presupuesto;

interface PresupuestoRepositoryInterface extends RepositoryInterface
{

    public function all();

    public function leePresupuesto($filtros, $flPaginando = null);

}

