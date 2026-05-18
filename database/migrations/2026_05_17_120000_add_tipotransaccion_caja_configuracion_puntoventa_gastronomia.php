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
            $table->unsignedBigInteger('tipotransaccion_caja_id')->nullable()->after('tipotransaccion_id');
            $table->foreign('tipotransaccion_caja_id', 'fk_config_pv_gastro_tipotransaccion_caja')
                ->references('id')->on('tipotransaccion_caja')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });

        $envId = (int) env('GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID', 0);
        if ($envId > 0) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->whereNull('tipotransaccion_caja_id')
                ->update(['tipotransaccion_caja_id' => $envId]);
        }
    }

    public function down(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_config_pv_gastro_tipotransaccion_caja');
            $table->dropColumn('tipotransaccion_caja_id');
        });
    }
};
