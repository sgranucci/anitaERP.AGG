<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'unidadmedida_id')) {
                $table->unsignedBigInteger('unidadmedida_id')->nullable()->after('cantidad_stock');
                $table->foreign('unidadmedida_id', 'fk_recepcion_proveedor_articulo_unidadmedida')
                    ->references('id')->on('unidadmedida')->onDelete('restrict')->onUpdate('restrict');
            }

            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'coeficienteconversion')) {
                $table->decimal('coeficienteconversion', 22, 6)->default(1)->after('unidadmedida_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'coeficienteconversion')) {
                $table->dropColumn('coeficienteconversion');
            }
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'unidadmedida_id')) {
                $table->dropForeign('fk_recepcion_proveedor_articulo_unidadmedida');
                $table->dropColumn('unidadmedida_id');
            }
        });
    }
};
