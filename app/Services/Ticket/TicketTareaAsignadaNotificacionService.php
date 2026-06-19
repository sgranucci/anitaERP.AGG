<?php

namespace App\Services\Ticket;

use App\Mail\Ticket\TareaAsignadaNotificacion;
use App\Models\Ticket\Tecnico_Ticket;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\Turno_Ticket;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketTareaAsignadaNotificacionService
{
    /**
     * @param  array<int, array<string, mixed>>  $tareasNuevas
     */
    public function notificar(int $ticketId, array $tareasNuevas): void
    {
        if ($tareasNuevas === []) {
            return;
        }

        $ticket = Ticket::query()
            ->with('usuarios:id,nombre,email,usuario')
            ->find($ticketId);

        if (! $ticket) {
            return;
        }

        /** @var Usuario|null $creador */
        $creador = $ticket->usuarios;
        if (! $creador || empty($creador->email)) {
            Log::warning('Ticket tarea asignada: el creador del ticket no tiene email', [
                'ticket_id' => $ticketId,
                'usuario_id' => $ticket->usuario_id,
            ]);

            return;
        }

        /** @var Usuario|null $asignadoPor */
        $asignadoPor = Auth::user();
        if (! $asignadoPor instanceof Usuario) {
            return;
        }

        $tareasEnriquecidas = $this->enriquecerTareas($tareasNuevas);
        $urlTicket = route('edita_ticket', ['id' => $ticketId]);

        try {
            Mail::to($creador->email)->send(new TareaAsignadaNotificacion(
                $ticket,
                $tareasEnriquecidas,
                $asignadoPor,
                $urlTicket
            ));
        } catch (\Throwable $e) {
            Log::error('Ticket tarea asignada: falló el envío de mail', [
                'ticket_id' => $ticketId,
                'destino' => $creador->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tareasNuevas
     * @return array<int, array<string, mixed>>
     */
    private function enriquecerTareas(array $tareasNuevas): array
    {
        $tecnicoIds = collect($tareasNuevas)->pluck('tecnico_id')->filter()->unique()->values();
        $turnoIds = collect($tareasNuevas)->pluck('turno_id')->filter()->unique()->values();

        $tecnicos = $tecnicoIds->isEmpty()
            ? collect()
            : Tecnico_Ticket::query()->whereIn('id', $tecnicoIds)->pluck('nombre', 'id');

        $turnos = $turnoIds->isEmpty()
            ? collect()
            : Turno_Ticket::query()->whereIn('id', $turnoIds)->pluck('nombre', 'id');

        return array_map(function (array $tarea) use ($tecnicos, $turnos): array {
            $tarea['fechacarga_legible'] = $this->fechaLegible($tarea['fechacarga'] ?? null);
            $tarea['fechaprogramacion_legible'] = $this->fechaLegible($tarea['fechaprogramacion'] ?? null);
            $tarea['tecnico_nombre'] = $tecnicos[$tarea['tecnico_id'] ?? ''] ?? '';
            $tarea['turno_nombre'] = $turnos[$tarea['turno_id'] ?? ''] ?? '';

            return $tarea;
        }, $tareasNuevas);
    }

    private function fechaLegible(?string $fecha): string
    {
        if (empty($fecha) || $fecha < '2000-01-01') {
            return '';
        }

        return date('d/m/Y', strtotime($fecha));
    }
}
