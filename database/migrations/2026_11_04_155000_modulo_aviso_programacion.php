<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Día y hora del aviso se editan en Configuración → Avisos por módulo.
 * Un cron general (avisos:despachar-programados) ejecuta los que correspondan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_habilitada')) {
                $table->boolean('programacion_habilitada')->default(false)->after('incluir_link_consulta');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_hora')) {
                $table->string('programacion_hora', 5)->nullable()->after('programacion_habilitada');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_dias')) {
                $table->string('programacion_dias', 20)->nullable()->after('programacion_hora');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_ultima_en')) {
                $table->date('programacion_ultima_en')->nullable()->after('programacion_dias');
            }
        });

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sueldos')
            ->where('codigo', 'libreta_sanitaria_pendiente')
            ->update([
                'descripcion' => 'Empleados vigentes marcados como "Debe libreta sanitaria". '
                    .'El día y la hora se programan en este aviso. Las bajas no entran.',
                'mail_texto' => "Empleados que deben la libreta sanitaria al {fecha}.\n\n"
                    ."Cantidad: {cantidad}.\n\n"
                    ."{empleados}\n\n"
                    ."La frecuencia y el horario se definen en la programación de este aviso.\n"
                    ."Mientras el legajo siga marcado en la ficha, solapa Laborales, entra en el listado.\n"
                    ."Listado de empleados: {link_consulta}\n",
                'programacion_habilitada' => true,
                'programacion_hora' => '08:20',
                'programacion_dias' => '1,2,3,4,5',
                // Hoy ya pasó esa hora: no disparar al activar el cron. El próximo día hábil sale a las 08:20.
                'programacion_ultima_en' => now()->toDateString(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            foreach (['programacion_ultima_en', 'programacion_dias', 'programacion_hora', 'programacion_habilitada'] as $col) {
                if (Schema::hasColumn('modulo_aviso_tipo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
