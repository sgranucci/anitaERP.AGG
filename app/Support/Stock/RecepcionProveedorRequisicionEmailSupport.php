<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

final class RecepcionProveedorRequisicionEmailSupport
{
    public static function emailSolicitanteOc(Recepcion_Proveedor $recepcion): ?string
    {
        $recepcion->loadMissing([
            'ordencompras.requisiciones.usuarios',
            'ordencompras.creousuarios',
        ]);

        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return null;
        }

        $emailReq = trim((string) (optional(optional($oc->requisiciones)->usuarios)->email ?? ''));
        if ($emailReq !== '') {
            return $emailReq;
        }

        return trim((string) (optional($oc->creousuarios)->email ?? '')) ?: null;
    }
}
