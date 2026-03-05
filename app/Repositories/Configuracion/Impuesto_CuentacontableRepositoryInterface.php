<?php

namespace App\Repositories\Configuracion;

interface Impuesto_CuentacontableRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorImpuesto($impuesto_id, $empresa_id = null);
    public function deletePorImpuesto($impuesto_id);
}

