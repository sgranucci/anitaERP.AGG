<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia_linea', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_gastronomia_linea', 'comentario_cocina')) {
                $table->string('comentario_cocina', 255)->nullable()->after('numero_linea');
            }
        });

        Schema::table('venta_emision', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_emision', 'comentario_cocina')) {
                $table->string('comentario_cocina', 255)->nullable()->after('detalle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia_linea', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia_linea', 'comentario_cocina')) {
                $table->dropColumn('comentario_cocina');
            }
        });

        Schema::table('venta_emision', function (Blueprint $table) {
            if (Schema::hasColumn('venta_emision', 'comentario_cocina')) {
                $table->dropColumn('comentario_cocina');
            }
        });
    }
};
