<?php

namespace App\Repositories\Uif;

interface Puntaje_UifRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorPuntaje($valorpuntaje);
}

