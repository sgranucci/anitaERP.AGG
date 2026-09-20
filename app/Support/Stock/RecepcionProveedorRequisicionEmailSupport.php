<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

final class RecepcionProveedorRequisicionEmailSupport
{
    public static function emailSolicitanteOc(Recepcion_Proveedor $recepcion): ?string
    {
        // En Ordencompra la relación de creousuario_id se llama «usuarios»: pedir «creousuarios»
        // hacía que este método lanzara RelationNotFoundException siempre, y como los handlers de
        // aviso lo llaman dentro de un try que loguea y sigue, el aviso de recepción ingresada y la
        // encuesta al proveedor nunca se enviaban.
        $recepcion->loadMissing([
            'ordencompras.requisiciones.usuarios',
            'ordencompras.usuarios',
        ]);

        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return null;
        }

        $emailReq = trim((string) (optional(optional($oc->requisiciones)->usuarios)->email ?? ''));
        if ($emailReq !== '') {
            return $emailReq;
        }

        return trim((string) (optional($oc->usuarios)->email ?? '')) ?: null;
    }
}
