<?php

namespace App\Repositories\Compras;

interface Retencionganancia_EscalaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function deletePorRetencionGanancia($retencionganancia_id);
}

