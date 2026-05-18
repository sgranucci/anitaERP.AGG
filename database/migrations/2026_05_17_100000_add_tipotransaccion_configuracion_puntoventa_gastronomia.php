<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_id')->nullable()->after('listaprecio_id');
            $table->foreign('tipotransaccion_id', 'fk_config_pv_gastro_tipotransaccion')
                ->references('id')->on('tipotransaccion')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });

        $envId = (int) env('GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID', 0);
        if ($envId > 0) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->whereNull('tipotransaccion_id')
                ->update(['tipotransaccion_id' => $envId]);
        }
    }

    public function down(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_config_pv_gastro_tipotransaccion');
            $table->dropColumn('tipotransaccion_id');
        });
    }
};
