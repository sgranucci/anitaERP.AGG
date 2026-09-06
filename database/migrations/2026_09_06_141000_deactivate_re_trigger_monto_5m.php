<?php

use App\Support\Configuracion\ReArbolTriggerCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El umbral 5M lo manejan los niveles de firmante (Rama B), no el trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        DB::table('arbolaprobacion_re_trigger')
            ->where('evaluador', ReArbolTriggerCatalog::EVAL_MONTO_MAYOR_IGUAL)
            ->where('param_monto', 5000000)
            ->update([
                'activo' => 'N',
                'observacion' => 'Apagado: el monto lo resuelven los firmantes N2 (hdattilo/mbmendez). Activar solo si se quiere forzar Rama B por umbral.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        DB::table('arbolaprobacion_re_trigger')
            ->where('evaluador', ReArbolTriggerCatalog::EVAL_MONTO_MAYOR_IGUAL)
            ->where('param_monto', 5000000)
            ->update([
                'activo' => 'S',
                'observacion' => 'Umbral alto refuerza autorización aunque la cuenta esté en allowlist.',
                'updated_at' => now(),
            ]);
    }
};
