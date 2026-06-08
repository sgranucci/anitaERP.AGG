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
            $table->dropForeign('fk_articulo_proveedor_lpa');
            $table->dropColumn(['precio', 'listaprecio_proveedor_articulo_id']);
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->unsignedBigInteger('listaprecio_proveedor_id')->nullable()->after('coeficiente_conversion');
            $table->foreign('listaprecio_proveedor_id', 'fk_articulo_proveedor_listaprecio_proveedor')
                ->references('id')->on('listaprecio_proveedor')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_proveedor_listaprecio_proveedor');
            $table->dropColumn('listaprecio_proveedor_id');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->decimal('precio', 20, 6)->default(0);
            $table->unsignedBigInteger('listaprecio_proveedor_articulo_id')->nullable();
            $table->foreign('listaprecio_proveedor_articulo_id', 'fk_articulo_proveedor_lpa')
                ->references('id')->on('listaprecio_proveedor_articulo')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }
};
