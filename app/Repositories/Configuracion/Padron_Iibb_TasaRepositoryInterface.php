<?php

namespace App\Repositories\Configuracion;

use Carbon\CarbonInterface;

interface Padron_Iibb_TasaRepositoryInterface extends RepositoryInterface
{

    public function deletePorProvinciaId($jurisdiccion);

    /**
     * Elimina filas cuya vigencia cerró antes del corte (hastafecha menor que corte; periodo desdefecha–hastafecha finalizado).
     */
    public function eliminarPorHastafechaAnteriorA(CarbonInterface $corte): int;

}

