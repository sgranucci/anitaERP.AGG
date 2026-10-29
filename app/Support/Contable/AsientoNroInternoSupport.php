<?php

declare(strict_types=1);

namespace App\Support\Contable;

/**
 * subd_nro_interno de Anita es el com_nro_interno de la factura.
 * Una operación (un asiento) debe traer un solo interno distinto de cero.
 */
final class AsientoNroInternoSupport
{
    /**
     * @param  list<object>  $lineas
     */
    public static function unicoDesdeLineasSubdiario(array $lineas): ?int
    {
        $ids = [];
        foreach ($lineas as $linea) {
            $nro = (int) ($linea->subd_nro_interno ?? 0);
            if ($nro > 0) {
                $ids[$nro] = true;
            }
        }

        if (count($ids) !== 1) {
            return null;
        }

        return (int) array_key_first($ids);
    }
}
