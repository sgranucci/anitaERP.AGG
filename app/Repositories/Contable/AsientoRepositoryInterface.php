<?php

namespace App\Repositories\Contable;

interface AsientoRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
    public function leeAsientoPorClave($id, $clave);
}

