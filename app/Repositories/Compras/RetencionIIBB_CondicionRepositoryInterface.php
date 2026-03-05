<?php

namespace App\Repositories\Compras;

interface RetencionIIBB_CondicionRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function deletePorRetencionIIBB($retencionIIBB_id);
}

