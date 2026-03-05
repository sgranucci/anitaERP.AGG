<?php

namespace App\Repositories\Ordenventa;

interface Concepto_Cuentacontable_OrdenventaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorConceptoOrdenventa($concepto_ordenventa_id, $empresa_id = null);
    public function deletePorConceptoOrdenventa($concepto_ordenventa_id);
}

