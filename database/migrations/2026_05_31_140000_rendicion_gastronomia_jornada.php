<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->string('tipo', 20)->default('turno')->after('id');
            $table->unsignedBigInteger('jornada_gastronomia_id')->nullable()->after('turno_operativo_gastronomia_id');
            $table->unsignedBigInteger('cierre_totem_jornada_gastronomia_id')->nullable()->after('jornada_gastronomia_id');
            $table->unsignedInteger('waitry_order_id_hasta')->nullable()->after('cierre_totem_jornada_gastronomia_id');
            $table->json('numeracion_comprobantes_json')->nullable()->after('waitry_order_id_hasta');

            $table->foreign('jornada_gastronomia_id', 'fk_rendicion_gastronomia_jornada')
                ->references('id')->on('jornada_gastronomia')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cierre_totem_jornada_gastronomia_id', 'fk_rendicion_gastronomia_cierre_totem')
                ->references('id')->on('cierre_totem_jornada_gastronomia')
                ->onDelete('set null')->onUpdate('restrict');

            $table->unique('jornada_gastronomia_id', 'uq_rendicion_gastronomia_jornada');
            $table->index(['empresa_id', 'tipo'], 'idx_rendicion_gastronomia_empresa_tipo');
        });

        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->unsignedBigInteger('turno_operativo_gastronomia_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rendicion_gastronomia_caja', 'jornada_gastronomia_id')) {
            DB::table('rendicion_gastronomia_caja')
                ->where('tipo', 'jornada')
                ->delete();
        }

        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->dropForeign('fk_rendicion_gastronomia_jornada');
            $table->dropForeign('fk_rendicion_gastronomia_cierre_totem');
            $table->dropUnique('uq_rendicion_gastronomia_jornada');
            $table->dropIndex('idx_rendicion_gastronomia_empresa_tipo');
            $table->dropColumn([
                'tipo',
                'jornada_gastronomia_id',
                'cierre_totem_jornada_gastronomia_id',
                'waitry_order_id_hasta',
                'numeracion_comprobantes_json',
            ]);
        });
    }
};
