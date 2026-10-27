<?php

namespace App\Repositories\Configuracion;

interface CotizacionRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();

    /**
     * Alta o actualización del día en shared. En Interforming no escribe Anita.
     *
     * @param  array<int, array{venta: float, compra: float}>  $porCodigo  código Anita 2..9
     */
    public function grabarDiaApiEnAnita(string $fechaSql, array $porCodigo): void;

}

