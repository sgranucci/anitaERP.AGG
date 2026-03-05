<?php

namespace App\Repositories\Configuracion;

use Illuminate\Http\Request;

interface SeteosalidaRepositoryInterface extends RepositoryInterface
{

    public function buscaSeteo($usuario_id, $opcion = null);
    public function armaNombrePrograma($opcion = null);
    public function leeSeteo($usuario_id, $programa);

}

