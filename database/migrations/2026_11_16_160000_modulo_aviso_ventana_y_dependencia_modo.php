<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventana horaria (hora hasta) + modo de dependencia batch más rico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_hora_hasta')) {
                $table->string('programacion_hora_hasta', 5)->nullable()->after('programacion_hora');
            }
            if (! Schema::hasColumn('modulo_aviso_tipo', 'programacion_depende_modo')) {
                $table->string('programacion_depende_modo', 32)->nullable()->after('programacion_depende_de');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
            if (Schema::hasColumn('modulo_aviso_tipo', 'programacion_depende_modo')) {
                $table->dropColumn('programacion_depende_modo');
            }
            if (Schema::hasColumn('modulo_aviso_tipo', 'programacion_hora_hasta')) {
                $table->dropColumn('programacion_hora_hasta');
            }
        });
    }
};
