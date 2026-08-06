<?php

namespace App\Support\Ticket;

use App\Models\Seguridad\Usuario;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance de Carga de Tickets por mismo centro de costo del emisor
 * (permiso admin-ticket-sector).
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

            return;
        }

        $viewerId = (int) ($viewer->id ?? 0);
        $tickets->where('ticket.usuario_id', $viewerId > 0 ? $viewerId : -1);
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

        return (int) ($emisor->centrocosto_id ?? 0) === $ccViewer;
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