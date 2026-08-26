<?php

namespace App\Support\Compras\PrecargaProveedor;

/**
 * Código de centro de costo destino para listaConcepto (tipo FIU/FNB/FGA…).
 *
 * Anita cabecera: penmp_ccosto = origen, penmp_ccosto_dest a menudo copia el origen
 * al grabar ERP→Anita. El destino real está en las líneas (penvp_ccosto).
 */
final class PrecargaProveedorCentrocostoDestinoSupport
{
    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     */
    public static function codigoDesdeOcAnita(object $cabecera, iterable $itemsOrdenCompra): string
    {
        foreach ($itemsOrdenCompra as $item) {
            $linea = self::normalizarCodigo($item->penvp_ccosto_dest ?? null)
                ?: self::normalizarCodigo($item->penvp_ccosto ?? null);
            if ($linea !== '') {
                return $linea;
            }
        }

        $destCabecera = self::normalizarCodigo($cabecera->penmp_ccosto_dest ?? null);
        if ($destCabecera !== '') {
            return $destCabecera;
        }

        return self::normalizarCodigo($cabecera->penmp_ccosto ?? null);
    }

    /**
     * Deja penmp_ccosto_dest de la cabecera leída con el destino de las líneas.
     */
    public static function aplicarDestinoEnCabecera(object $cabecera, iterable $itemsOrdenCompra): object
    {
        $codigo = self::codigoDesdeOcAnita($cabecera, $itemsOrdenCompra);
        if ($codigo !== '') {
            $cabecera->penmp_ccosto_dest = $codigo;
        }

        return $cabecera;
    }

    private static function normalizarCodigo(mixed $valor): string
    {
        $codigo = trim((string) $valor);
        if ($codigo === '' || $codigo === '0') {
            return '';
        }

        return $codigo;
    }
}
