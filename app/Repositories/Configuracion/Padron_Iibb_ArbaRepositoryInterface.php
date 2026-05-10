<?php

namespace App\Repositories\Configuracion;

use Carbon\CarbonInterface;

interface Padron_Iibb_ArbaRepositoryInterface extends RepositoryInterface
{

    public function deletePorCuit($cuit);
    public function findPorCuit($cuit);

    /**
     * Elimina filas cuya vigencia cerró antes del corte (hastafecha menor que corte).
     */
    public function eliminarPorHastafechaAnteriorA(CarbonInterface $corte): int;

}

