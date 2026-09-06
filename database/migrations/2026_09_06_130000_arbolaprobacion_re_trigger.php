<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            Schema::create('arbolaprobacion_re_trigger', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('arbolaprobacion_id');
                $table->string('nombre', 120)->nullable();
                $table->string('tipo', 20)->default('CONDICION');
                $table->string('evaluador', 50)->nullable();
                $table->unsignedBigInteger('centrocosto_id')->nullable()
                    ->comment('Null = todos los CC del árbol');
                $table->string('accion_rama', 20)->default('ALLOWLIST')
                    ->comment('A | B | ALLOWLIST');
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->char('activo', 1)->default('S');
                $table->timestamps();

                $table->foreign('arbolaprobacion_id', 'fk_arbol_re_trigger_arbol')
                    ->references('id')->on('arbolaprobacion')->cascadeOnDelete();
                $table->foreign('centrocosto_id', 'fk_arbol_re_trigger_cc')
                    ->references('id')->on('centrocosto')->nullOnDelete();
                $table->index(['arbolaprobacion_id', 'activo', 'prioridad'], 'arbol_re_trigger_prio_idx');
            });
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'arbolaprobacion_re_trigger_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('arbolaprobacion_re_trigger_id')->nullable()
                    ->after('circuito_re');
                $table->foreign('arbolaprobacion_re_trigger_id', 'fk_arbol_mov_re_trigger')
                    ->references('id')->on('arbolaprobacion_re_trigger')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'arbolaprobacion_re_trigger_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_arbol_mov_re_trigger');
                $table->dropColumn('arbolaprobacion_re_trigger_id');
            });
        }

        Schema::dropIfExists('arbolaprobacion_re_trigger');
    }
};
