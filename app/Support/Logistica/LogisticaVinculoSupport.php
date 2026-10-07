<?php

namespace App\Support\Logistica;

use App\Models\Compras\Requisicion;
use App\Models\Logistica\SolicitudLogistica;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use RuntimeException;

final class LogisticaVinculoSupport
{
    public static function vincular(SolicitudLogistica $solicitud, string $numero): void
    {
        $numero = trim($numero);
        if ($numero === '') {
            throw new RuntimeException('Indicá el número del comprobante.');
        }
        $modo = (string) $solicitud->modo_cumplimiento;
        if ($modo === 'deposito') {
            $mov = MovimientoStock::query()->where('codigo', $numero)->orderByDesc('id')->first();
            if ($mov === null && ctype_digit($numero)) {
                $mov = MovimientoStock::query()->whereKey((int) $numero)->first();
            }
            if ($mov === null) {
                throw new RuntimeException('No existe el movimiento de stock '.$numero.'.');
            }
            $solicitud->movimientostock_id = (int) $mov->id;
            $solicitud->save();

            return;
        }
        if ($modo === 'transferencia') {
            $tm = Transferencia_Mercaderia::query()->where('codigo', $numero)->orderByDesc('id')->first();
            if ($tm === null && ctype_digit($numero)) {
                $tm = Transferencia_Mercaderia::query()->whereKey((int) $numero)->first();
            }
            if ($tm === null) {
                throw new RuntimeException('No existe la transferencia '.$numero.'.');
            }
            $solicitud->transferencia_mercaderia_id = (int) $tm->id;
            $solicitud->save();

            return;
        }
        if ($modo === 'compra') {
            if (! ctype_digit($numero)) {
                throw new RuntimeException('Indicá el número de requisición.');
            }
            $req = Requisicion::query()->where('numerorequisicion', (int) $numero)->orderByDesc('id')->first();
            if ($req === null) {
                throw new RuntimeException('No existe la requisición '.$numero.'.');
            }
            $solicitud->requisicion_id = (int) $req->id;
            $solicitud->save();

            return;
        }

        throw new RuntimeException('Primero pasá la solicitud a preparación e indicá el modo.');
    }
}
