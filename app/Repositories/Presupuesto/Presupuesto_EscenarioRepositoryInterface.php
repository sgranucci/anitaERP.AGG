<?php

namespace App\Repositories\Presupuesto;

interface Presupuesto_EscenarioRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorPresupuesto($presupuesto_id);
    public function deletePorPresupuesto($presupuesto_id);
}

