<?php

namespace App\Services\Ticket;

use App\Mail\Ticket\ComentarioAdministracionTareaNotificacion;
use App\Mail\Ticket\ComentarioUsuarioTareaNotificacion;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Tarea;
use App\Models\Ticket\Ticket_Tarea_Comentario_Usuario;
use App\Models\Seguridad\Usuario;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketTareaComentarioUsuarioService
{
    private Tecnico_TicketRepositoryInterface $tecnicoTicketRepository;

    public function __construct(Tecnico_TicketRepositoryInterface $tecnicoTicketRepository)
    {
        $this->tecnicoTicketRepository = $tecnicoTicketRepository;
    }

    public function guardar(int $ticketId, int $ticketTareaId, string $comentario): Ticket_Tarea_Comentario_Usuario
    {
        $comentario = trim($comentario);
        if ($comentario === '') {
            throw new \InvalidArgumentException('El comentario no puede estar vacío.');
        }

        $ticket = Ticket::query()->with('usuarios')->findOrFail($ticketId);
        $this->validarAccesoTicket($ticket);

        $ticketTarea = Ticket_Tarea::query()
            ->with(['tareas', 'tecnicos.usuarios', 'tickets'])
            ->where('ticket_id', $ticketId)
            ->findOrFail($ticketTareaId);

        /** @var Usuario $usuario */
        $usuario = Auth::user();

        $registro = Ticket_Tarea_Comentario_Usuario::query()->create([
            'ticket_tarea_id' => $ticketTarea->id,
            'usuario_id' => $usuario->id,
            'comentario' => $comentario,
        ]);

        $registro->load('usuarios');

        if ((int) $ticket->usuario_id === (int) $usuario->id) {
            $this->notificarTecnico($ticket, $ticketTarea, $registro, $usuario);
        } else {
            $this->notificarCreadorTicket($ticket, $ticketTarea, $registro, $usuario);
        }

        return $registro;
    }

    private function validarAccesoTicket(Ticket $ticket): void
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();
        if (! $usuario) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        $permisos = traePermisosUsuario()['permisos'] ?? [];

        if (in_array('supervisor-ticket', $permisos, true)) {
            return;
        }

        if (in_array('editar-ticket', $permisos, true) || in_array('actualizar-ticket', $permisos, true)) {
            return;
        }

        if ((int) $ticket->usuario_id === (int) $usuario->id) {
            return;
        }

        if (in_array('encargado-ticket', $permisos, true)) {
            $tecnicos = $this->tecnicoTicketRepository->leePorUsuarioId($usuario->id);
            if (count($tecnicos) > 0
                && (int) $tecnicos[0]->areadestino_id === (int) $ticket->areadestino_id) {
                return;
            }
        }

        throw new \RuntimeException('No tiene permiso para comentar en este ticket.');
    }

    private function notificarTecnico(
        Ticket $ticket,
        Ticket_Tarea $ticketTarea,
        Ticket_Tarea_Comentario_Usuario $comentario,
        Usuario $autor
    ): void {
        $tecnico = $ticketTarea->tecnicos;
        if (! $tecnico) {
            Log::warning('Ticket comentario usuario: tarea sin técnico asignado', [
                'ticket_id' => $ticket->id,
                'ticket_tarea_id' => $ticketTarea->id,
            ]);

            return;
        }

        $tecnicoUsuario = $tecnico->usuarios;
        if (! $tecnicoUsuario || empty($tecnicoUsuario->email)) {
            Log::warning('Ticket comentario usuario: técnico sin email', [
                'ticket_id' => $ticket->id,
                'ticket_tarea_id' => $ticketTarea->id,
                'tecnico_id' => $tecnico->id,
            ]);

            return;
        }

        $urlTicket = route('edita_administracion_ticket', ['id' => $ticket->id]);

        try {
            Mail::to($tecnicoUsuario->email)->send(new ComentarioUsuarioTareaNotificacion(
                $ticket,
                $ticketTarea,
                $comentario,
                $autor,
                $urlTicket
            ));
        } catch (\Throwable $e) {
            Log::error('Ticket comentario usuario: falló el envío de mail', [
                'ticket_id' => $ticket->id,
                'ticket_tarea_id' => $ticketTarea->id,
                'destino' => $tecnicoUsuario->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificarCreadorTicket(
        Ticket $ticket,
        Ticket_Tarea $ticketTarea,
        Ticket_Tarea_Comentario_Usuario $comentario,
        Usuario $autor
    ): void {
        $creador = $ticket->usuarios;
        if (! $creador || empty($creador->email)) {
            Log::warning('Ticket comentario administración: creador sin email', [
                'ticket_id' => $ticket->id,
                'ticket_tarea_id' => $ticketTarea->id,
                'usuario_id' => $ticket->usuario_id,
            ]);

            return;
        }

        if ((int) $creador->id === (int) $autor->id) {
            return;
        }

        $urlTicket = route('edita_ticket', ['id' => $ticket->id]);

        try {
            Mail::to($creador->email)->send(new ComentarioAdministracionTareaNotificacion(
                $ticket,
                $ticketTarea,
                $comentario,
                $autor,
                $urlTicket
            ));
        } catch (\Throwable $e) {
            Log::error('Ticket comentario administración: falló el envío de mail', [
                'ticket_id' => $ticket->id,
                'ticket_tarea_id' => $ticketTarea->id,
                'destino' => $creador->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
