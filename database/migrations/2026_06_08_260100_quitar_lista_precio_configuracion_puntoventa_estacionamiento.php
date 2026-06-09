<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configuracion_puntoventa_estacionamiento', 'lista_precio_estacionamiento_id')) {
            return;
        }

        Schema::table('configuracion_puntoventa_estacionamiento', function (Blueprint $table) {
            $table->dropForeign('fk_cfg_pv_estacionamiento_lista_precio');
            $table->dropColumn('lista_precio_estacionamiento_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuracion_puntoventa_estacionamiento', 'lista_precio_estacionamiento_id')) {
            return;
        }

        Schema::table('configuracion_puntoventa_estacionamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('lista_precio_estacionamiento_id')->nullable()->after('puntoventa_caea_id');
            $table->foreign('lista_precio_estacionamiento_id', 'fk_cfg_pv_estacionamiento_lista_precio')
                ->references('id')->on('lista_precio_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }
};
