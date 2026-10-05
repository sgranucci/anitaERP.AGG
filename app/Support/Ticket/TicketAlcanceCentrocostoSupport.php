<?php

namespace App\Support\Ticket;

use App\Models\Seguridad\Usuario;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alcance de Carga de Tickets por mismo centro de costo del emisor
 * (permiso admin-ticket-sector).
 *
 * enc-SEGURIDAD comparte el centro de costo SEGURIDAD entre salas.
 * En ese rol el listado queda además en los emisores del mismo establecimiento.
 */
class TicketAlcanceCentrocostoSupport
{
    public static function centrocostoId(?Usuario $usuario = null): int
    {
        $usuario = $usuario ?? Auth::user();
        if (! $usuario) {
            return 0;
        }

        return (int) ($usuario->centrocosto_id ?? 0);
    }

    /**
     * Filtra tickets cuyo emisor (join alias usuario) tiene el mismo CC.
     * Si el viewer no tiene CC, solo deja los propios.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $tickets
     */
    public static function aplicarFiltroEmisoresMismoCentrocosto($tickets, ?Usuario $viewer = null): void
    {
        $viewer = $viewer ?? Auth::user();
        $ccId = self::centrocostoId($viewer);

        if ($ccId > 0) {
            $tickets->where('usuario.centrocosto_id', $ccId);
            self::aplicarFiltroEstablecimientoEmisor($tickets, $viewer);

            return;
        }

        $viewerId = (int) ($viewer->id ?? 0);
        $tickets->where('ticket.usuario_id', $viewerId > 0 ? $viewerId : -1);
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder  $tickets
     */
    public static function aplicarFiltroEstablecimientoEmisor($tickets, ?Usuario $viewer = null): void
    {
        if (! self::restringePorEstablecimiento()) {
            return;
        }

        $viewer = $viewer ?? Auth::user();
        $empresaIds = self::empresaIdsViewer($viewer);
        if ($empresaIds === []) {
            return;
        }

        $viewerId = (int) ($viewer->id ?? 0);
        $tickets->where(function ($query) use ($empresaIds, $viewerId) {
            $query->whereExists(function ($sub) use ($empresaIds) {
                $sub->select(DB::raw(1))
                    ->from('usuario_empresa as ue_emisor')
                    ->whereColumn('ue_emisor.usuario_id', 'usuario.id')
                    ->whereIn('ue_emisor.empresa_id', $empresaIds);
            });
            if ($viewerId > 0) {
                $query->orWhere('ticket.usuario_id', $viewerId);
            }
        });
    }

    public static function emisorMismoCentrocosto(Ticket $ticket, ?Usuario $viewer = null): bool
    {
        $viewer = $viewer ?? Auth::user();
        if (! $viewer) {
            return false;
        }

        if ((int) $ticket->usuario_id === (int) $viewer->id) {
            return true;
        }

        $ccViewer = self::centrocostoId($viewer);
        if ($ccViewer <= 0) {
            return false;
        }

        $emisor = $ticket->relationLoaded('usuarios')
            ? $ticket->usuarios
            : Usuario::query()->select('id', 'centrocosto_id')->find($ticket->usuario_id);

        if (! $emisor) {
            return false;
        }

        if ((int) ($emisor->centrocosto_id ?? 0) !== $ccViewer) {
            return false;
        }

        if (! self::restringePorEstablecimiento()) {
            return true;
        }

        return self::emisorComparteEstablecimiento((int) $emisor->id, $viewer);
    }

    public static function restringePorEstablecimiento(): bool
    {
        return (string) session('rol_nombre') === 'enc-SEGURIDAD';
    }

    /**
     * @return list<int>
     */
    public static function empresaIdsViewer(?Usuario $viewer = null): array
    {
        $desdeSesion = collect(session('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($desdeSesion !== []) {
            return $desdeSesion;
        }

        $viewer = $viewer ?? Auth::user();
        $viewerId = (int) ($viewer->id ?? 0);
        if ($viewerId <= 0) {
            return [];
        }

        return DB::table('usuario_empresa')
            ->where('usuario_id', $viewerId)
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private static function emisorComparteEstablecimiento(int $emisorId, ?Usuario $viewer): bool
    {
        $empresaIds = self::empresaIdsViewer($viewer);
        if ($empresaIds === []) {
            return true;
        }
        if ($emisorId <= 0) {
            return false;
        }

        return DB::table('usuario_empresa')
            ->where('usuario_id', $emisorId)
            ->whereIn('empresa_id', $empresaIds)
            ->exists();
    }

    public static function puedeAccederTicketCarga(Ticket $ticket, ?Usuario $viewer = null): bool
    {
        $viewer = $viewer ?? Auth::user();
        if (! $viewer) {
            return false;
        }

        if (session()->get('rol_nombre') === 'administrador') {
            return true;
        }

        $permisos = traePermisosUsuario()['permisos'] ?? [];

        if (in_array('supervisor-ticket', $permisos, true)) {
            return true;
        }

        if (in_array('admin-ticket-sector', $permisos, true)) {
            return self::emisorMismoCentrocosto($ticket, $viewer);
        }

        // usuario-ticket sin perfiles de atención: solo propios
        if (in_array('usuario-ticket', $permisos, true)
            && ! in_array('encargado-ticket', $permisos, true)
            && ! in_array('tecnico-ticket', $permisos, true)
            && ! in_array('admin-ticket-sector', $permisos, true)) {
            return (int) $ticket->usuario_id === (int) $viewer->id;
        }

        return true;
    }
}