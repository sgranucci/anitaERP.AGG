<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa hora_resolucion cuando quedó 00:00 por backfill desde fecha de tarea (sin hora).
 * Usa updated_at de la tarea finalizada ese día, o el del ticket si coincide la fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'hora_resolucion')) {
            return;
        }

        // estado_ticket no es columna de ticket: el estado vive en ticket_estado.
        $query = DB::table('ticket')
            ->whereNotNull('fecha_resolucion')
            ->where(function ($q) {
                $q->whereNull('hora_resolucion')
                    ->orWhere('hora_resolucion', '00:00:00')
                    ->orWhere('hora_resolucion', '00:00');
            })
            ->orderBy('id');

        if (Schema::hasTable('ticket_estado')) {
            $query->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('ticket_estado')
                    ->whereColumn('ticket_estado.ticket_id', 'ticket.id')
                    ->where('ticket_estado.estado', 'Finalizado')
                    ->whereNull('ticket_estado.deleted_at');
            });
        } elseif (Schema::hasColumn('ticket', 'estado_ticket')) {
            $query->where('estado_ticket', 'Finalizado');
        }

        $query->chunkById(200, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $fecha = Carbon::parse($ticket->fecha_resolucion)->toDateString();
                    $hora = $this->horaDesdeTarea((int) $ticket->id, $fecha);
                    if ($hora === null) {
                        $hora = $this->horaDesdeTicket($ticket, $fecha);
                    }
                    if ($hora === null) {
                        continue;
                    }

                    DB::table('ticket')->where('id', $ticket->id)->update([
                        'hora_resolucion' => $hora,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // No revierte: 00:00 no era una hora real.
    }

    private function horaDesdeTarea(int $ticketId, string $fecha): ?string
    {
        $tarea = DB::table('ticket_tarea')
            ->where('ticket_id', $ticketId)
            ->whereNull('deleted_at')
            ->whereDate('fechafinalizacion', $fecha)
            ->orderByDesc('updated_at')
            ->first(['updated_at']);

        if (! $tarea || empty($tarea->updated_at)) {
            return null;
        }

        $dt = Carbon::parse($tarea->updated_at);
        if ($dt->toDateString() !== $fecha) {
            return null;
        }

        $hora = $dt->format('H:i');

        return $hora === '00:00' ? null : $hora;
    }

    private function horaDesdeTicket(object $ticket, string $fecha): ?string
    {
        if (empty($ticket->updated_at)) {
            return null;
        }

        $dt = Carbon::parse($ticket->updated_at);
        if ($dt->toDateString() !== $fecha) {
            return null;
        }

        $hora = $dt->format('H:i');

        return $hora === '00:00' ? null : $hora;
    }
};
