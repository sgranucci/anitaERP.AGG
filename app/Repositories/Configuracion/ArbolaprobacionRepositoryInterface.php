<?php

namespace App\Repositories\Configuracion;

interface ArbolaprobacionRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * Árboles activos de un tipo para una empresa concreta (requisiciones, etc.).
     */
    public function findPorTipoArbolYEmpresa(string $tipoarbol, int $empresa_id);
}

