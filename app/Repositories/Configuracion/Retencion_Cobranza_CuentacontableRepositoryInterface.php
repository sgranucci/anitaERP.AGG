<?php

namespace App\Repositories\Configuracion;

interface Retencion_Cobranza_CuentacontableRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorRetencion_Cobranza($retencion_cobranza_id, $empresa_id);
    public function deletePorRetencion_Cobranza($retencion_cobranza_id);
}

