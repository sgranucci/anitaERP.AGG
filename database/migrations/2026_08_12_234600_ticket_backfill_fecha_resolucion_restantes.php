<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill de fecha/hora de resolución para tickets Finalizados sin sello.
 * El estado no está en ticket.estado_ticket (no existe esa columna): se lee ticket_estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'fecha_resolucion')) {
            return;
        }

        $query = DB::table('ticket')
            ->whereNull('fecha_resolucion')
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
        } else {
            return;
        }

        $query->chunkById(200, function ($tickets) {
            foreach ($tickets as $ticket) {
                $fuente = $ticket->updated_at ?? $ticket->created_at ?? null;
                if (empty($fuente)) {
                    continue;
                }
                $dt = Carbon::parse($fuente);
                DB::table('ticket')->where('id', $ticket->id)->update([
                    'fecha_resolucion' => $dt->toDateString(),
                    'hora_resolucion' => $dt->format('H:i'),
                ]);
            }
        });
    }

    public function down(): void
    {
        // No revierte el backfill: los sellos históricos no se pueden distinguir de los cargados a mano.
    }
};
