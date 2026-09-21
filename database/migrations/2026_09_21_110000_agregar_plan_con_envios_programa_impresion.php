<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }
        if (Schema::hasColumn('comprobante_impresion_programa', 'plan_con_envios')) {
            return;
        }

        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            $table->boolean('plan_con_envios')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }
        if (! Schema::hasColumn('comprobante_impresion_programa', 'plan_con_envios')) {
            return;
        }

        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            $table->dropColumn('plan_con_envios');
        });
    }
};
