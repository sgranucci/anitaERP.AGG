<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El módulo de tickets trata estado_ticket como columna denormalizada en ticket
 * (listados, bandeja, alta/edición). El historial sigue en ticket_estado.
 * Sin esta columna el index / carga de tickets responde 500 (Unknown column).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket') || Schema::hasColumn('ticket', 'estado_ticket')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            $table->string('estado_ticket', 40)->nullable()->after('observacion');
            $table->index('estado_ticket', 'ticket_estado_ticket_index');
        });

        $this->backfillDesdeHistoria();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'estado_ticket')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            try {
                $table->dropIndex('ticket_estado_ticket_index');
            } catch (\Throwable $e) {
                // índice ausente en algún entorno
            }
            $table->dropColumn('estado_ticket');
        });
    }

    private function backfillDesdeHistoria(): void
    {
        if (! Schema::hasTable('ticket_estado')) {
            return;
        }

        $ultimos = DB::table('ticket_estado as te')
            ->select('te.ticket_id', 'te.estado')
            ->whereRaw('te.id = (
                select max(te2.id) from ticket_estado te2 where te2.ticket_id = te.ticket_id
            )')
            ->get();

        foreach ($ultimos as $fila) {
            $estado = trim((string) ($fila->estado ?? ''));
            if ($estado === '') {
                continue;
            }
            DB::table('ticket')
                ->where('id', (int) $fila->ticket_id)
                ->where(function ($q) {
                    $q->whereNull('estado_ticket')->orWhere('estado_ticket', '');
                })
                ->update(['estado_ticket' => $estado]);
        }
    }
};
