<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El motivo de visita puede exigir la orden de compra al cargar el ingreso.
 * Servicio a realizar queda obligatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_motivo')) {
            return;
        }

        if (! Schema::hasColumn('ingreso_proveedor_motivo', 'exige_oc')) {
            Schema::table('ingreso_proveedor_motivo', function (Blueprint $table) {
                $table->boolean('exige_oc')->default(false)->after('activo');
            });
        }

        DB::table('ingreso_proveedor_motivo')
            ->where('codigo', 'SERVICIO')
            ->update([
                'exige_oc' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_motivo')
            || ! Schema::hasColumn('ingreso_proveedor_motivo', 'exige_oc')) {
            return;
        }

        Schema::table('ingreso_proveedor_motivo', function (Blueprint $table) {
            $table->dropColumn('exige_oc');
        });
    }
};
