<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (! Schema::hasColumn('usuario', 'tipotransaccion_venta_id')) {
                $table->unsignedBigInteger('tipotransaccion_venta_id')->nullable()->after('vendedor_id');
                $table->foreign('tipotransaccion_venta_id', 'fk_usuario_tipotransaccion_venta')
                    ->references('id')->on('tipotransaccion')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('usuario', 'puntoventa_id')) {
                $table->unsignedBigInteger('puntoventa_id')->nullable()->after('tipotransaccion_venta_id');
                $table->foreign('puntoventa_id', 'fk_usuario_puntoventa')
                    ->references('id')->on('puntoventa')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('usuario', 'puntoventaremito_id')) {
                $table->unsignedBigInteger('puntoventaremito_id')->nullable()->after('puntoventa_id');
                $table->foreign('puntoventaremito_id', 'fk_usuario_puntoventaremito')
                    ->references('id')->on('puntoventa')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (Schema::hasColumn('usuario', 'puntoventaremito_id')) {
                $table->dropForeign('fk_usuario_puntoventaremito');
                $table->dropColumn('puntoventaremito_id');
            }
            if (Schema::hasColumn('usuario', 'puntoventa_id')) {
                $table->dropForeign('fk_usuario_puntoventa');
                $table->dropColumn('puntoventa_id');
            }
            if (Schema::hasColumn('usuario', 'tipotransaccion_venta_id')) {
                $table->dropForeign('fk_usuario_tipotransaccion_venta');
                $table->dropColumn('tipotransaccion_venta_id');
            }
        });
    }
};
