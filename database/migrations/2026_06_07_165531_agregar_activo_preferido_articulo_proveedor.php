<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articulo_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('articulo_proveedor', 'listaprecio_proveedor_id')) {
                $table->dropForeign('fk_articulo_proveedor_listaprecio_proveedor');
                $table->dropColumn('listaprecio_proveedor_id');
            }
            if (Schema::hasColumn('articulo_proveedor', 'moneda_id')) {
                $table->dropForeign('fk_articulo_proveedor_moneda');
                $table->dropColumn('moneda_id');
            }
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_proveedor', 'activo')) {
                $table->boolean('activo')->default(true)->after('coeficiente_conversion');
            }
            if (! Schema::hasColumn('articulo_proveedor', 'preferido')) {
                $table->boolean('preferido')->default(false)->after('activo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articulo_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('articulo_proveedor', 'activo')) {
                $table->dropColumn('activo');
            }
            if (Schema::hasColumn('articulo_proveedor', 'preferido')) {
                $table->dropColumn('preferido');
            }
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_proveedor', 'moneda_id')) {
                $table->unsignedBigInteger('moneda_id')->nullable()->after('codigo_articulo_proveedor');
                $table->foreign('moneda_id', 'fk_articulo_proveedor_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('articulo_proveedor', 'listaprecio_proveedor_id')) {
                $table->unsignedBigInteger('listaprecio_proveedor_id')->nullable()->after('coeficiente_conversion');
                $table->foreign('listaprecio_proveedor_id', 'fk_articulo_proveedor_listaprecio_proveedor')
                    ->references('id')->on('listaprecio_proveedor')->onDelete('set null')->onUpdate('restrict');
            }
        });
    }
};
