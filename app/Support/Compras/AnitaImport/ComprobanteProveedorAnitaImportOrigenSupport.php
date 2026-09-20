<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Compras\ComprobanteProveedorOrigenEntrada;

/**
 * Origen del comprobante de proveedor frente a import Anita → ERP.
 */
final class ComprobanteProveedorAnitaImportOrigenSupport
{
    /**
     * Nativa = cualquier alta que no sea import histórico Anita.
     * Ante origen null se trata como nativa (no se pisa).
     */
    public static function esNativo(?string $origenEntrada): bool
    {
        return $origenEntrada !== ComprobanteProveedorOrigenEntrada::ANITA_IMPORT;
    }

    /**
     * Empresas operativas AGG para alinear deuda (Biyemas / Kandiko / Rebisco).
     *
     * @return list<int>
     */
    public static function empresasOperativasAgg(): array
    {
        return [1, 2, 3];
    }
}
