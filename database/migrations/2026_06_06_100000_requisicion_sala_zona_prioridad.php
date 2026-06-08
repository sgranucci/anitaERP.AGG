<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisicion_sala')) {
            return;
        }

        Schema::table('requisicion_sala', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_sala', 'zona_sala_id')) {
                $table->unsignedBigInteger('zona_sala_id')->nullable()->after('centrocosto_id');
                $table->foreign('zona_sala_id', 'fk_requisicion_sala_zona_sala')
                    ->references('id')->on('zona_sala')->onDelete('restrict')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('requisicion_sala', 'prioridad_sala_id')) {
                $table->unsignedBigInteger('prioridad_sala_id')->nullable()->after('zona_sala_id');
                $table->foreign('prioridad_sala_id', 'fk_requisicion_sala_prioridad_sala')
                    ->references('id')->on('prioridad_sala')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('requisicion_sala')) {
            return;
        }

        Schema::table('requisicion_sala', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion_sala', 'prioridad_sala_id')) {
                $table->dropForeign('fk_requisicion_sala_prioridad_sala');
                $table->dropColumn('prioridad_sala_id');
            }
            if (Schema::hasColumn('requisicion_sala', 'zona_sala_id')) {
                $table->dropForeign('fk_requisicion_sala_zona_sala');
                $table->dropColumn('zona_sala_id');
            }
        });
    }
};
