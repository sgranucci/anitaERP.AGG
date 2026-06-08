<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_recepcion_proveedor_ordencompra');
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->unsignedBigInteger('ordencompra_id')->nullable()->change();
            $table->foreign('ordencompra_id', 'fk_recepcion_proveedor_ordencompra')
                ->references('id')->on('ordencompra')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_recepcion_proveedor_ordencompra');
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->unsignedBigInteger('ordencompra_id')->nullable(false)->change();
            $table->foreign('ordencompra_id', 'fk_recepcion_proveedor_ordencompra')
                ->references('id')->on('ordencompra')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
