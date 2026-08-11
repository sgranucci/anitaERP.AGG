<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peso total de línea OC (cantidad × peso_unitario). Usado cuando
 * ORDENCOMPRA_MOSTRAR_PESO_ARTICULO=true (El Bierzo). Nullable en AGG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'peso_total')) {
                $table->decimal('peso_total', 18, 6)->nullable()->after('peso_unitario')
                    ->comment('Cantidad × peso_unitario (snapshot OC)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'peso_total')) {
                $table->dropColumn('peso_total');
            }
        });
    }
};
