<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;

/**
 * Sede IIBB del cliente / entrega (zona multilateral Anita).
 * No usar en POS gastronomía ni estacionamiento.
 */
final class ClienteProvinciaIibbSupport
{
    public static function idDe(?Cliente $cliente): ?int
    {
        $id = (int) ($cliente->provincia_iibb_id ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function idDeEntrega(?Cliente_Entrega $entrega): ?int
    {
        if ($entrega === null) {
            return null;
        }

        $id = (int) ($entrega->provincia_iibb_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Facturación administrativa: entrega.sede → cliente.sede → domicilio (transitorio
     * hasta completar provincia_iibb_id). El domicilio no es la fuente preferida.
     */
    public static function idParaPercepcionAdmin(?Cliente $cliente, ?Cliente_Entrega $entrega = null): ?int
    {
        $sedeEntrega = self::idDeEntrega($entrega);
        if ($sedeEntrega !== null) {
            return $sedeEntrega;
        }

        $sedeCliente = self::idDe($cliente);
        if ($sedeCliente !== null) {
            return $sedeCliente;
        }

        $domicilioEntrega = (int) ($entrega->provincia_id ?? 0);
        if ($domicilioEntrega > 0) {
            return $domicilioEntrega;
        }

        $domicilio = (int) ($cliente->provincia_id ?? 0);

        return $domicilio > 0 ? $domicilio : null;
    }

    /**
     * Código Anita clim_zonamult / ven_zonamult.
     * POS: domicilio (comportamiento actual). Admin: sede IIBB, si no domicilio.
     */
    public static function codigoZonamultParaAnita(
        ?Cliente $cliente,
        bool $esPos,
        ?Cliente_Entrega $entrega = null
    ): int {
        if ($esPos) {
            return ClienteAnitaZonamultSupport::codigoDesdeProvinciaId(
                (int) ($cliente->provincia_id ?? 0) ?: null
            );
        }

        $sede = self::idParaPercepcionAdmin($cliente, $entrega);

        return ClienteAnitaZonamultSupport::codigoDesdeProvinciaIibbId($sede);
    }
}
