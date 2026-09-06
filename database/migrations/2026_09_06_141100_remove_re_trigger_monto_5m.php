<?php

use App\Support\Configuracion\ReArbolTriggerCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita el trigger MONTO ≥ 5M: el umbral lo resuelven firmantes N2 en niveles Rama B.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        $ids = DB::table('arbolaprobacion_re_trigger')
            ->where('evaluador', ReArbolTriggerCatalog::EVAL_MONTO_MAYOR_IGUAL)
            ->where('param_monto', 5000000)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasColumn('arbolaprobacion_movimiento', 'arbolaprobacion_re_trigger_id')) {
            DB::table('arbolaprobacion_movimiento')
                ->whereIn('arbolaprobacion_re_trigger_id', $ids)
                ->update(['arbolaprobacion_re_trigger_id' => null]);
        }

        DB::table('arbolaprobacion_re_trigger')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // No recrea: el seed premium ya no debe reintroducir este trigger activo.
    }
};
