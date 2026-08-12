<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor_Concepto;

/**
 * Línea Informix concmov (3 columnas: nro_interno, concepto, importe).
 */
final class ConcmovLineaAnitaMapper
{
    public static function camposInsert(): string
    {
        return 'concv_nro_interno, concv_concepto, concv_importe';
    }

    public static function valoresInsert(
        ComprobanteProveedorAnitaContext $ctx,
        Comprobante_Proveedor_Concepto $linea,
        int $orden = 1,
    ): string {
        $codigo = (int) ($linea->concepto_ivacompras?->codigo ?? 0);

        return "
            '".$ctx->nroInterno."',
            '".$codigo."',
            '".$ctx->decimal($linea->monto)."'
        ";
    }
}
