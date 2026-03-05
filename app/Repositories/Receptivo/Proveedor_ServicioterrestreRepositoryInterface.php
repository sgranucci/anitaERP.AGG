<?php

namespace App\Repositories\Receptivo;

interface Proveedor_ServicioterrestreRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leeCosto($servicioterrestre_id, $proveedor_id);
    public function leeProveedor($servicioterrestre_id);

}

