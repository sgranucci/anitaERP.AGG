<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de mozo Anita (vend_aplicacion): M=mozo/colaborador, E=encargado, A=adicionista.
 * Columna en todos los entornos; el sync Crown la llena desde vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mozo_gastronomia')) {
            return;
        }
        if (Schema::hasColumn('mozo_gastronomia', 'aplicacion')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            $table->string('aplicacion', 1)->default('M')->after('clave');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mozo_gastronomia') || ! Schema::hasColumn('mozo_gastronomia', 'aplicacion')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            $table->dropColumn('aplicacion');
        });
    }
};
