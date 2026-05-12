<?php

namespace App\Support\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Seguridad\Usuario;

/**
 * Usuario que emitió la requisición (alta) y último usuario asociado al paso APROBADA en su historia.
 */
final class OrdencompraPdfContextoRequisicion
{
    /**
     * @return array{0: ?Usuario, 1: ?Usuario}
     */
    public static function emitioYUltimoAprobador(?Requisicion $req): array
    {
        if (! $req) {
            return [null, null];
        }
        $req->loadMissing(['usuarios', 'requisicion_estados.usuarios']);
        $nombreAprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $aprobador = null;
        foreach ($req->requisicion_estados->sortBy('fecha') as $h) {
            if (($h->estado ?? '') === $nombreAprobada && $h->usuarios) {
                $aprobador = $h->usuarios;
            }
        }

        return [$req->usuarios, $aprobador];
    }
}
