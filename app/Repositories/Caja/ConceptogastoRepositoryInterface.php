<?php

namespace App\Repositories\Caja;

interface ConceptogastoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);

}

