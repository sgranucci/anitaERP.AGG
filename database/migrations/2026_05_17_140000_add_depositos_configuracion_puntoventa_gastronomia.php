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
            $table->unsignedBigInteger('deposito_venta_id')->nullable()->after('listaprecio_id');
            $table->foreign('deposito_venta_id', 'fk_config_pv_gastronomia_deposito_venta')
                ->references('id')->on('depmae')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->unsignedBigInteger('deposito_insumos_id')->nullable()->after('deposito_venta_id');
            $table->foreign('deposito_insumos_id', 'fk_config_pv_gastronomia_deposito_insumos')
                ->references('id')->on('depmae')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });

        $fallback = (int) config('facturacion.DEPOSITO_VENTA_ID', 1);
        if ($fallback > 0) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->whereNull('deposito_venta_id')
                ->update([
                    'deposito_venta_id' => $fallback,
                    'deposito_insumos_id' => $fallback,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_config_pv_gastronomia_deposito_insumos');
            $table->dropForeign('fk_config_pv_gastronomia_deposito_venta');
            $table->dropColumn(['deposito_insumos_id', 'deposito_venta_id']);
        });
    }
};
