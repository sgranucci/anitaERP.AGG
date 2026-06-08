<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_movimiento')) {
            return;
        }

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('arbolaprobacion_movimiento', 'requisicion_sala_id')) {
                $table->unsignedBigInteger('requisicion_sala_id')->nullable()->after('requisicion_id');
                $table->foreign('requisicion_sala_id', 'fk_arbol_mov_requisicion_sala')
                    ->references('id')->on('requisicion_sala')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_movimiento')) {
            return;
        }

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('arbolaprobacion_movimiento', 'requisicion_sala_id')) {
                $table->dropForeign('fk_arbol_mov_requisicion_sala');
                $table->dropColumn('requisicion_sala_id');
            }
        });
    }
};
