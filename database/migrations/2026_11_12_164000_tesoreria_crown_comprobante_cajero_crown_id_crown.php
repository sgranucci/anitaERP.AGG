<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operativa por cajero+jornada: cajero_crown_id en comprobante, cierre y ajuste.
 * Backfill desde tesoreria_crown_asignacion; asignacion_id queda nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->agregarCajero('tesoreria_crown_comprobante');
        $this->agregarCajero('tesoreria_crown_cierre');
        $this->agregarCajero('tesoreria_crown_ajuste');

        if (Schema::hasTable('tesoreria_crown_asignacion')) {
            foreach (['tesoreria_crown_comprobante', 'tesoreria_crown_cierre', 'tesoreria_crown_ajuste'] as $tabla) {
                if (! Schema::hasColumn($tabla, 'cajero_crown_id')) {
                    continue;
                }
                DB::statement(
                    "UPDATE {$tabla} t
                     INNER JOIN tesoreria_crown_asignacion a ON a.id = t.asignacion_id
                     SET t.cajero_crown_id = a.cajero_crown_id
                     WHERE t.cajero_crown_id IS NULL AND t.asignacion_id IS NOT NULL"
                );
            }
        }

        // Propagar tarea al maestro desde la última asignación si el cajero no tiene.
        if (Schema::hasTable('cajero_crown') && Schema::hasColumn('cajero_crown', 'tipo_tarea')
            && Schema::hasTable('tesoreria_crown_asignacion')) {
            $filas = DB::table('tesoreria_crown_asignacion as a')
                ->select('a.cajero_crown_id', 'a.tipo_tarea', 'a.permite_rendir_sin_habilitacion')
                ->whereNotNull('a.cajero_crown_id')
                ->orderByDesc('a.id')
                ->get()
                ->unique('cajero_crown_id');
            foreach ($filas as $fila) {
                DB::table('cajero_crown')
                    ->where('id', $fila->cajero_crown_id)
                    ->whereNull('tipo_tarea')
                    ->update([
                        'tipo_tarea' => $fila->tipo_tarea,
                        'permite_rendir_sin_habilitacion' => (bool) ($fila->permite_rendir_sin_habilitacion ?? false),
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->nullableAsignacion('tesoreria_crown_comprobante');
        $this->nullableAsignacion('tesoreria_crown_cierre');
        $this->nullableAsignacion('tesoreria_crown_ajuste');
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        $this->quitarCajero('tesoreria_crown_ajuste');
        $this->quitarCajero('tesoreria_crown_cierre');
        $this->quitarCajero('tesoreria_crown_comprobante');
    }

    private function agregarCajero(string $tabla): void
    {
        if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'cajero_crown_id')) {
            return;
        }
        Schema::table($tabla, function (Blueprint $table) use ($tabla) {
            $table->unsignedBigInteger('cajero_crown_id')->nullable()->after('jornada_id');
            $table->foreign('cajero_crown_id', substr($tabla, 0, 20).'_cajero_fk')
                ->references('id')->on('cajero_crown')->nullOnDelete();
            $table->index(['jornada_id', 'cajero_crown_id'], substr($tabla, 0, 18).'_jor_caj_idx');
        });
    }

    private function quitarCajero(string $tabla): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'cajero_crown_id')) {
            return;
        }
        Schema::table($tabla, function (Blueprint $table) use ($tabla) {
            try {
                $table->dropForeign(substr($tabla, 0, 20).'_cajero_fk');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex(substr($tabla, 0, 18).'_jor_caj_idx');
            } catch (\Throwable) {
            }
            $table->dropColumn('cajero_crown_id');
        });
    }

    private function nullableAsignacion(string $tabla): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'asignacion_id')) {
            return;
        }
        Schema::table($tabla, function (Blueprint $table) {
            $table->unsignedBigInteger('asignacion_id')->nullable()->change();
        });
    }
};
