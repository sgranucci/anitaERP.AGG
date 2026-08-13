<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ticket')
            ->where('estado_ticket', 'Finalizado')
            ->whereNull('fecha_resolucion')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) {
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
