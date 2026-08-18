<?php

namespace App\Repositories\Configuracion;

interface ArbolaprobacionRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * Listado del ABM (empresas asignadas + filtro externo de empresa + tipos por permiso).
     *
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Arbolaprobacion>
     */
    public function leeArbolaprobacion(array $filtros);

    /**
     * Árboles activos de un tipo para una empresa concreta (requisiciones, etc.).
     */
    public function findPorTipoArbolYEmpresa(string $tipoarbol, int $empresa_id);
}

