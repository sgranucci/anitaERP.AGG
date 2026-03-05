<?php

namespace App\Repositories\Compras;

interface Concepto_Ivacompra_CondicionivaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function deletePorConcepto_Ivacompra($concepto_ivacompra_id);
}

