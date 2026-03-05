<?php

namespace App\Repositories\Uif;

interface Frecuencia_UifRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorFrecuencia($frecuencia);

}

