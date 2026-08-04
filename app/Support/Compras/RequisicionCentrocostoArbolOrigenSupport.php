<?php

namespace App\Support\Compras;

/**
 * Capital Humano: pueden cargar en la requisición el CC del circuito de árbol
 * (opcional; por default el CC de origen del solicitante), sin depender de los destinos de renglón.
 */
final class RequisicionCentrocostoArbolOrigenSupport
{
    public const PERMISO = 'cargar-centrocosto-arbol-requisicion';

    public static function usuarioPuedeCargar(): bool
    {
        return can(self::PERMISO, false);
    }
}
