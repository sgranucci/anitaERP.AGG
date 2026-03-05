<?php

namespace App\Repositories\Caja;

interface Caja_MovimientoRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
    public function leeGastoAnterior($ordenservicio_id);
    public function leeOrdenServicioCajaMovimiento();
    
}

