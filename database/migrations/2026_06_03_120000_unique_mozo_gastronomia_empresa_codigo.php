<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El código de mozo puede repetirse entre empresas (p. ej. Kandiko/Rebisco vs Biyemas).
     * Dentro de la misma empresa sigue siendo único.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mozo_gastronomia')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->unique(['empresa_id', 'codigo'], 'uq_mozo_gastronomia_empresa_codigo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mozo_gastronomia')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            $table->dropUnique('uq_mozo_gastronomia_empresa_codigo');
            $table->unique('codigo');
        });
    }
};
