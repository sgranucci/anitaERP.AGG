<?php

namespace App\Support\Compras;

use Illuminate\Support\Facades\DB;

/**
 * Capital Humano: pueden cargar en la requisición el CC del circuito de árbol
 * (opcional; por default el CC de origen del solicitante), sin depender de los destinos de renglón.
 *
 * Fuera de esa excepción, el circuito debe seguir el CC de destino de los renglones.
 */
final class RequisicionCentrocostoArbolOrigenSupport
{
    public const PERMISO = 'cargar-centrocosto-arbol-requisicion';

    public static function usuarioPuedeCargar(): bool
    {
        return can(self::PERMISO, false);
    }

    public static function usuarioIdPuedeCargar(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        return DB::table('usuario_rol as ur')
            ->join('permiso_rol as pr', 'pr.rol_id', '=', 'ur.rol_id')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->where('ur.usuario_id', $usuarioId)
            ->where('p.slug', self::PERMISO)
            ->exists();
    }

    /**
     * ¿Puede el circuito quedar distinto del único destino de renglón?
     * Sí si el operador actual o el creador de la requi tienen el permiso CH.
     */
    public static function permiteCircuitoDistintoDeDestino(?int $creousuarioId = null): bool
    {
        if (self::usuarioPuedeCargar()) {
            return true;
        }

        return $creousuarioId !== null && self::usuarioIdPuedeCargar((int) $creousuarioId);
    }
}
