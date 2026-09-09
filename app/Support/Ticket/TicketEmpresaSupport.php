<?php

namespace App\Support\Ticket;

use App\Models\Configuracion\Empresa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Empresa de origen del ticket.
 *
 * Los tickets anteriores a empresa_id quedan en null; la sala siempre tiene empresa
 * (Biyemas / Kandiko / Rebisco). No inventar la empresa de sesión del técnico.
 */
class TicketEmpresaSupport
{
    public static function empresaIdDesdeSala(?int $salaId): ?int
    {
        $salaId = (int) $salaId;
        if ($salaId <= 0) {
            return null;
        }

        $empresaId = (int) DB::table('sala')->where('id', $salaId)->value('empresa_id');

        return $empresaId > 0 ? $empresaId : null;
    }

    public static function empresaIdDesdeTicket($ticket): ?int
    {
        if (! $ticket) {
            return null;
        }

        $empresaId = (int) ($ticket->empresa_id ?? 0);
        if ($empresaId > 0) {
            return $empresaId;
        }

        $salaEmpresa = (int) (optional($ticket->salas)->empresa_id ?? 0);
        if ($salaEmpresa > 0) {
            return $salaEmpresa;
        }

        return self::empresaIdDesdeSala((int) ($ticket->sala_id ?? 0));
    }

    public static function resolver(?int $empresaId, ?int $salaId, ?int $empresaIdExistente = null): ?int
    {
        $empresaId = (int) $empresaId;
        if ($empresaId > 0) {
            return $empresaId;
        }

        $existente = (int) $empresaIdExistente;
        if ($existente > 0) {
            return $existente;
        }

        return self::empresaIdDesdeSala($salaId);
    }

    /**
     * Incluye la empresa del ticket aunque el operador no la tenga asignada,
     * para mostrar el nombre en solo lectura.
     */
    public static function asegurarEnColeccion($empresas, ?int $empresaId): Collection
    {
        $coleccion = collect($empresas);
        $empresaId = (int) $empresaId;
        if ($empresaId <= 0) {
            return $coleccion;
        }

        $esta = $coleccion->contains(static function ($empresa) use ($empresaId) {
            return (int) ($empresa->id ?? 0) === $empresaId;
        });
        if ($esta) {
            return $coleccion;
        }

        $extra = Empresa::query()->find($empresaId);

        return $extra ? $coleccion->concat([$extra]) : $coleccion;
    }
}
