<?php

namespace App\Repositories\Receptivo;

interface Guia_IdiomaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function deletePorGuia($retencionganancia_id);
}

