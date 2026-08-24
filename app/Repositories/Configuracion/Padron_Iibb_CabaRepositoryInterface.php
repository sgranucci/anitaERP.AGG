<?php

namespace App\Repositories\Configuracion;

use Carbon\CarbonInterface;

interface Padron_Iibb_CabaRepositoryInterface extends RepositoryInterface
{

    public function deletePorCuit($cuit);
    public function findPorCuit($cuit);
    public function minDesdefechaPorCuit($cuit): ?string;

    /**
     * Elimina filas cuya vigencia cerró antes del corte (hastafecha menor que corte).
     */
    public function eliminarPorHastafechaAnteriorA(CarbonInterface $corte): int;

}

