<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket', 'fecha_resolucion')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->date('fecha_resolucion')->nullable();
            });
        }
        if (! Schema::hasColumn('ticket', 'hora_resolucion')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->time('hora_resolucion')->nullable();
            });
        }
        if (! Schema::hasColumn('ticket', 'tiempo_insumido_total')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->decimal('tiempo_insumido_total', 12, 2)->nullable()->default(0);
            });
        }

        try {
            Schema::table('ticket', function (Blueprint $table) {
                $table->index('fecha_resolucion', 'ticket_fecha_resolucion_index');
            });
        } catch (\Throwable $e) {
            // índice ya existente
        }

        $this->backfillEstadisticas();
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            try {
                $table->dropIndex('ticket_fecha_resolucion_index');
            } catch (\Throwable $e) {
                // índice ausente en algún entorno
            }

            $drop = [];
            foreach (['tiempo_insumido_total', 'hora_resolucion', 'fecha_resolucion'] as $col) {
                if (Schema::hasColumn('ticket', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    private function backfillEstadisticas(): void
    {
        DB::table('ticket')->orderBy('id')->chunkById(200, function ($tickets) {
            foreach ($tickets as $ticket) {
                $sum = (float) DB::table('ticket_tarea')
                    ->where('ticket_id', $ticket->id)
                    ->whereNull('deleted_at')
                    ->sum('tiempoinsumido');

                $update = ['tiempo_insumido_total' => round($sum, 2)];

                if ((string) ($ticket->estado_ticket ?? '') === 'Finalizado') {
                    $sello = $this->resolverSelloHistorico((int) $ticket->id);
                    if ($sello !== null) {
                        $update['fecha_resolucion'] = $sello['fecha'];
                        $update['hora_resolucion'] = $sello['hora'];
                    }
                }

                DB::table('ticket')->where('id', $ticket->id)->update($update);
            }
        });
    }

    /**
     * @return array{fecha: string, hora: string}|null
     */
    private function resolverSelloHistorico(int $ticketId): ?array
    {
        $estado = DB::table('ticket_estado')
            ->where('ticket_id', $ticketId)
            ->where('estado', 'Finalizado')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($estado && ! empty($estado->fecha)) {
            $dtFecha = Carbon::parse($estado->fecha);
            $fuenteHora = $estado->updated_at ?? $estado->created_at ?? null;
            $hora = '00:00';
            if (! empty($fuenteHora)) {
                $hora = Carbon::parse($fuenteHora)->format('H:i');
            }

            return [
                'fecha' => $dtFecha->toDateString(),
                'hora' => $hora,
            ];
        }

        $maxFecha = DB::table('ticket_tarea')
            ->where('ticket_id', $ticketId)
            ->whereNull('deleted_at')
            ->max('fechafinalizacion');

        if (! empty($maxFecha) && (string) $maxFecha >= '2000-01-01') {
            return [
                'fecha' => Carbon::parse($maxFecha)->toDateString(),
                'hora' => '00:00',
            ];
        }

        return null;
    }
};
