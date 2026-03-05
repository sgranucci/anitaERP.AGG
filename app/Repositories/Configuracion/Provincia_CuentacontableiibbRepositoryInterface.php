<?php

namespace App\Repositories\Configuracion;

interface Provincia_CuentacontableiibbRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorProvincia($provincia_id, $empresa_id);
    public function deletePorProvincia($provincia_id);
}

