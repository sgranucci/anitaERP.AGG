<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepcion_proveedor_articulo')) {
            return;
        }
        if (Schema::hasColumn('recepcion_proveedor_articulo', 'peso_bulto')) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->decimal('peso_bulto', 18, 6)->nullable()->after('cantidad_rechazada');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepcion_proveedor_articulo', 'peso_bulto')) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->dropColumn('peso_bulto');
        });
    }
};
