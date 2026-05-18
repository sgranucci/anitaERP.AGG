<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('listaprecio_id')->default(1)->after('salida_factura_id');
            $table->foreign('listaprecio_id', 'fk_config_pv_gastro_listaprecio')
                ->references('id')->on('listaprecio')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_config_pv_gastro_listaprecio');
            $table->dropColumn('listaprecio_id');
        });
    }
};
