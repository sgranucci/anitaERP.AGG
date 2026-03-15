<?php

namespace App\Repositories\Configuracion;

use Illuminate\Http\Request;

interface SeteoModeloetiquetaRepositoryInterface extends RepositoryInterface
{

    public function buscaSeteoModeloetiqueta($usuario_id, $opcion = null);
    public function leeSeteo($usuario_id, $programa);

}

