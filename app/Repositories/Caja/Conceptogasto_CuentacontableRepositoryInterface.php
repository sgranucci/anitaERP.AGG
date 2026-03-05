<?php

namespace App\Repositories\Caja;

interface Conceptogasto_CuentacontableRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorConceptogasto($conceptogasto_id);
    public function leePorConceptogastoCuenta($conceptogasto_id, $cuentacontable_id);
    public function deletePorConceptogasto($conceptogasto_id);
}

