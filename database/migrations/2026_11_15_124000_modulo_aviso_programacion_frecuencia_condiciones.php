<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Frecuencia tipo MyLogic (cada N semanas, hasta) + condiciones JSON + disparador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_cada_n')) {
                $table->unsignedTinyInteger('programacion_cada_n')->default(1)->after('programacion_dias');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_hasta')) {
                $table->date('programacion_hasta')->nullable()->after('programacion_cada_n');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_ancla')) {
                $table->date('programacion_ancla')->nullable()->after('programacion_hasta');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'condiciones')) {
                $table->json('condiciones')->nullable()->after('notificar_in_app');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'disparador')) {
                $table->string('disparador', 20)->default('evento')->after('condiciones');
            }
        });

        $mapa = config('modulo_aviso.programados', []);
        if (is_array($mapa) && $mapa !== []) {
            foreach (array_keys($mapa) as $clave) {
                [$modulo, $codigo] = array_pad(explode('.', (string) $clave, 2), 2, null);
                if ($modulo === null || $codigo === null) {
                    continue;
                }
                DB::table('modulo_aviso_tipo')
                    ->where('modulo', $modulo)
                    ->where('codigo', $codigo)
                    ->update([
                        'disparador' => 'cron',
                        'updated_at' => now(),
                    ]);
            }
        }

        // Migrar parametro_dias de cheques a condiciones JSON (sin borrar la columna).
        $cheques = DB::table('modulo_aviso_tipo')
            ->where('modulo', 'caja')
            ->where('codigo', 'cheques_aging')
            ->first(['id', 'parametro_dias', 'condiciones']);
        if ($cheques) {
            $cond = [];
            if (! empty($cheques->condiciones)) {
                $decoded = json_decode((string) $cheques->condiciones, true);
                if (is_array($decoded)) {
                    $cond = $decoded;
                }
            }
            if (! isset($cond['dias_proximos']) && $cheques->parametro_dias !== null) {
                $cond['dias_proximos'] = (int) $cheques->parametro_dias;
                DB::table('modulo_aviso_tipo')->where('id', $cheques->id)->update([
                    'condiciones' => json_encode($cond, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            foreach (['disparador', 'condiciones', 'programacion_ancla', 'programacion_hasta', 'programacion_cada_n'] as $col) {
                if (Schema::hasColumn('modulo_aviso_tipo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
