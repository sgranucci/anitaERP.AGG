<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: jornada por sala (+ empresa) y vínculo mozo/sala en rendición de gastronomía (cierre X).
 * Columnas nullable: AGG sigue operando jornada solo por empresa (sala_id null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jornada_gastronomia') && ! Schema::hasColumn('jornada_gastronomia', 'sala_id')) {
            Schema::table('jornada_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->foreign('sala_id', 'fk_jornada_gastro_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
                $table->index(['empresa_id', 'sala_id', 'estado'], 'idx_jornada_gastro_empresa_sala_estado');
            });
        }

        if (Schema::hasTable('rendicion_gastronomia_caja')) {
            Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
                if (! Schema::hasColumn('rendicion_gastronomia_caja', 'sala_id')) {
                    $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                    $table->foreign('sala_id', 'fk_rend_gastro_sala')
                        ->references('id')->on('sala')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('rendicion_gastronomia_caja', 'mozo_gastronomia_id')) {
                    $table->unsignedBigInteger('mozo_gastronomia_id')->nullable()->after('caja_id');
                    $table->foreign('mozo_gastronomia_id', 'fk_rend_gastro_mozo')
                        ->references('id')->on('mozo_gastronomia')
                        ->nullOnDelete();
                    $table->index(['mozo_gastronomia_id', 'fecharendicion'], 'idx_rend_gastro_mozo_fecha');
                }
                if (! Schema::hasColumn('rendicion_gastronomia_caja', 'total_x')) {
                    $table->decimal('total_x', 14, 2)->nullable()->after('totalcobrado');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rendicion_gastronomia_caja')) {
            Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
                if (Schema::hasColumn('rendicion_gastronomia_caja', 'total_x')) {
                    $table->dropColumn('total_x');
                }
                if (Schema::hasColumn('rendicion_gastronomia_caja', 'mozo_gastronomia_id')) {
                    $table->dropForeign('fk_rend_gastro_mozo');
                    $table->dropIndex('idx_rend_gastro_mozo_fecha');
                    $table->dropColumn('mozo_gastronomia_id');
                }
                if (Schema::hasColumn('rendicion_gastronomia_caja', 'sala_id')) {
                    $table->dropForeign('fk_rend_gastro_sala');
                    $table->dropColumn('sala_id');
                }
            });
        }

        if (Schema::hasTable('jornada_gastronomia') && Schema::hasColumn('jornada_gastronomia', 'sala_id')) {
            Schema::table('jornada_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_jornada_gastro_sala');
                $table->dropIndex('idx_jornada_gastro_empresa_sala_estado');
                $table->dropColumn('sala_id');
            });
        }
    }
};
