<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_gastronomia_emision')) {
            return;
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_gastronomia_emision', 'waitry_comandas_json')) {
                $table->json('waitry_comandas_json')->nullable()->after('waitry_order_id');
            }
            if (! Schema::hasColumn('venta_gastronomia_emision', 'cierre_jornada_proceso_lote')) {
                $table->unsignedSmallInteger('cierre_jornada_proceso_lote')->nullable()->after('waitry_comandas_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_gastronomia_emision')) {
            return;
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            if (Schema::hasColumn('venta_gastronomia_emision', 'cierre_jornada_proceso_lote')) {
                $table->dropColumn('cierre_jornada_proceso_lote');
            }
            if (Schema::hasColumn('venta_gastronomia_emision', 'waitry_comandas_json')) {
                $table->dropColumn('waitry_comandas_json');
            }
        });
    }
};
