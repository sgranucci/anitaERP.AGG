<?php

use App\Support\Sueldos\LibretaSanitariaLeyendaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libreta sanitaria del legajo. Copia el texto que estaba en las leyendas
 * (DEBE LIBRETA SANITARIA / VCE. mm/aaaa). Las leyendas no se borran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('empleado_sueldos', 'debe_libreta_sanitaria')) {
                $table->boolean('debe_libreta_sanitaria')->default(false)->after('antiguedad_anterior');
            }
            if (! Schema::hasColumn('empleado_sueldos', 'fecha_vencimiento_libreta_sanitaria')) {
                $table->date('fecha_vencimiento_libreta_sanitaria')->nullable()->after('debe_libreta_sanitaria');
            }
        });

        if (! Schema::hasTable('empleado_leyenda_sueldos')) {
            return;
        }

        $porEmpleado = [];
        $leyendas = DB::table('empleado_leyenda_sueldos')
            ->where(function ($q) {
                $q->where('leyenda', 'like', '%LIBRET%')
                    ->orWhere('leyenda', 'like', '%libret%')
                    ->orWhere('leyenda', 'like', '%Libret%');
            })
            ->orderBy('empleado_id')
            ->orderBy('linea')
            ->get(['empleado_id', 'leyenda']);

        foreach ($leyendas as $leyenda) {
            $porEmpleado[(int) $leyenda->empleado_id][] = (string) $leyenda->leyenda;
        }

        foreach ($porEmpleado as $empleadoId => $textos) {
            $dato = LibretaSanitariaLeyendaSupport::interpretar($textos);
            if (! $dato['debe'] && $dato['vencimiento'] === null) {
                continue;
            }

            DB::table('empleado_sueldos')->where('id', $empleadoId)->update([
                'debe_libreta_sanitaria' => $dato['debe'],
                'fecha_vencimiento_libreta_sanitaria' => $dato['vencimiento'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('empleado_sueldos', 'fecha_vencimiento_libreta_sanitaria')) {
                $table->dropColumn('fecha_vencimiento_libreta_sanitaria');
            }
            if (Schema::hasColumn('empleado_sueldos', 'debe_libreta_sanitaria')) {
                $table->dropColumn('debe_libreta_sanitaria');
            }
        });
    }
};
