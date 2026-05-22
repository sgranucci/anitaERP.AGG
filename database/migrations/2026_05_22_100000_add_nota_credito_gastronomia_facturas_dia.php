<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_FACTURAS_DIA_URL = 'ventas/gastronomia/facturas-dia';

    public function up(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_nota_credito_id')->nullable()->after('tipotransaccion_id');
            $table->foreign('tipotransaccion_nota_credito_id', 'fk_config_pv_gastro_tt_nc')
                ->references('id')->on('tipotransaccion')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });

        $envNcId = (int) env('GASTRONOMIA_TIPO_TRANSACCION_NOTA_CREDITO_ID', 0);
        if ($envNcId > 0) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->whereNull('tipotransaccion_nota_credito_id')
                ->update(['tipotransaccion_nota_credito_id' => $envNcId]);
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_factura_origen_id')->nullable()->after('configuracion_puntoventa_gastronomia_id');
            $table->foreign('venta_factura_origen_id', 'fk_vge_venta_factura_origen')
                ->references('id')->on('venta')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unique('venta_factura_origen_id', 'uq_vge_venta_factura_origen');
        });

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_FACTURAS_DIA_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $slug = 'generar-nota-credito-gastronomia-facturas-dia';
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Generar nota de crédito (facturas del día gastronomía)',
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-facturas-gastronomia-dia')->value('id') ?? 0);
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slug = 'generar-nota-credito-gastronomia-facturas-dia';
        $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            $table->dropForeign('fk_vge_venta_factura_origen');
            $table->dropUnique('uq_vge_venta_factura_origen');
            $table->dropColumn('venta_factura_origen_id');
        });

        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_config_pv_gastro_tt_nc');
            $table->dropColumn('tipotransaccion_nota_credito_id');
        });
    }
};
