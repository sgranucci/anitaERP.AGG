<?php

namespace App\Repositories\Configuracion;

interface SalidaRepositoryInterface extends RepositoryInterface
{

    public function all();

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Salida>
     */
    public function paraProgramaSeteo(?string $programa, ?int $incluirSalidaId = null);
}

