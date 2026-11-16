<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layout del diseñador gráfico del árbol (posiciones de nodos).
 * No afecta la lógica de aprobación: solo presentación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }
        if (! Schema::hasColumn('arbolaprobacion', 'diseno_layout')) {
            Schema::table('arbolaprobacion', function (Blueprint $table) {
                $table->json('diseno_layout')->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion') && Schema::hasColumn('arbolaprobacion', 'diseno_layout')) {
            Schema::table('arbolaprobacion', function (Blueprint $table) {
                $table->dropColumn('diseno_layout');
            });
        }
    }
};
