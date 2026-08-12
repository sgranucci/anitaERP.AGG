<?php

namespace App\Observers\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Stock\TransferenciaMercaderiaTitoPrecioAvisoSupport;

/**
 * Al asociar asiento contable a una TRCONT con artículos TITO, avisa el precio promedio usado.
 */
class Transferencia_MercaderiaObserver
{
    public function updated(Transferencia_Mercaderia $transferencia): void
    {
        if (! $transferencia->wasChanged('asiento_id')) {
            return;
        }

        $asientoId = (int) ($transferencia->asiento_id ?? 0);
        $anterior = (int) ($transferencia->getOriginal('asiento_id') ?? 0);
        if ($asientoId <= 0 || $anterior > 0) {
            return;
        }

        TransferenciaMercaderiaTitoPrecioAvisoSupport::notificarSiCorresponde($transferencia);
    }
}
