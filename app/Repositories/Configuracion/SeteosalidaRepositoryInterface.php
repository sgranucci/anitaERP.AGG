<?php

namespace App\Repositories\Configuracion;

use Illuminate\Http\Request;

interface SeteosalidaRepositoryInterface extends RepositoryInterface
{

    public function buscaSeteo($usuario_id, $opcion = null);

    public function buscaSeteoEtiquetaOt($usuario_id, ?string $tipoEtiqueta = null);

    public function armaNombrePrograma($opcion = null);

    public function leeSeteo($usuario_id, $programa);

}

