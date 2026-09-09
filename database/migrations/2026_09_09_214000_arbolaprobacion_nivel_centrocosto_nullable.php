<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite centrocosto_id NULL en nivel de árbol = «todos los centros de costo».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        try {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->dropForeign('fk_arbolaprobacion_nivel_centrocosto');
            });
        } catch (\Throwable) {
            // Nombre de FK puede variar entre entornos.
        }

        Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
            $table->unsignedBigInteger('centrocosto_id')->nullable()->change();
        });

        Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
            $table->foreign('centrocosto_id', 'fk_arbolaprobacion_nivel_centrocosto')
                ->references('id')->on('centrocosto')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        // No reponer NOT NULL si hay filas con null (evitar tumbar datos).
        if (Schema::hasColumn('arbolaprobacion_nivel', 'centrocosto_id')) {
            // Intento solo dropear/recrear FK; no forzar NOT NULL.
        }
    }
};
