<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arbolaprobacion_oc_trigger')) {
            return;
        }

        Schema::create('arbolaprobacion_oc_trigger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('arbolaprobacion_id');
            $table->foreign('arbolaprobacion_id', 'fk_arbol_oc_trigger_arbol')
                ->references('id')->on('arbolaprobacion')->cascadeOnDelete();
            $table->string('nombre', 120)->nullable();
            $table->string('tipo', 20);
            $table->string('evento', 30)->nullable();
            $table->string('evaluador', 50)->nullable();
            $table->unsignedBigInteger('sector_origen_id')->nullable();
            $table->unsignedBigInteger('sector_destino_id')->nullable();
            $table->unsignedBigInteger('centrocosto_circuito_id')->nullable();
            $table->string('documento_estado_al_aprobar', 50)->nullable();
            $table->string('accion_final', 30)->default('NINGUNA');
            $table->unsignedBigInteger('accion_final_sector_id')->nullable();
            $table->string('accion_final_estado', 50)->nullable();
            $table->unsignedSmallInteger('prioridad')->default(100);
            $table->char('anula_auto_aprobacion', 1)->default('N');
            $table->char('reevaluar_en_actualizacion', 1)->default('N');
            $table->char('activo', 1)->default('S');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sector_origen_id', 'fk_arbol_oc_trigger_sector_origen')
                ->references('id')->on('sector_legajocompra')->nullOnDelete();
            $table->foreign('sector_destino_id', 'fk_arbol_oc_trigger_sector_destino')
                ->references('id')->on('sector_legajocompra')->nullOnDelete();
            $table->foreign('centrocosto_circuito_id', 'fk_arbol_oc_trigger_centrocosto')
                ->references('id')->on('centrocosto')->nullOnDelete();
            $table->foreign('accion_final_sector_id', 'fk_arbol_oc_trigger_accion_sector')
                ->references('id')->on('sector_legajocompra')->nullOnDelete();
        });

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'arbolaprobacion_oc_trigger_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('arbolaprobacion_oc_trigger_id')->nullable()->after('circuito_oc');
                $table->foreign('arbolaprobacion_oc_trigger_id', 'fk_arbol_mov_oc_trigger')
                    ->references('id')->on('arbolaprobacion_oc_trigger')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'arbolaprobacion_oc_trigger_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_arbol_mov_oc_trigger');
                $table->dropColumn('arbolaprobacion_oc_trigger_id');
            });
        }

        Schema::dropIfExists('arbolaprobacion_oc_trigger');
    }
};
