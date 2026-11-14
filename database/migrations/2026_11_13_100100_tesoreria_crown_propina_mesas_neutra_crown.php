<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: subtipo PROP/Mesas (MES) queda neutro como Híbrida:
 * no mueve billetaje. La propina de mesa se carga solo con monto
 * en el estado final (Anexo II + asiento informativo bóveda PROP/Mesas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')
            || ! Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'mueve_billetaje')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $tipoIds = DB::table('tesoreria_crown_boveda_tipo')
            ->where('abreviatura', 'PROP')
            ->pluck('id');
        if ($tipoIds->isEmpty()) {
            return;
        }

        DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('tipo_id', $tipoIds)
            ->where('codigo', 'MES')
            ->update([
                'mueve_billetaje' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')
            || ! Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'mueve_billetaje')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $tipoIds = DB::table('tesoreria_crown_boveda_tipo')
            ->where('abreviatura', 'PROP')
            ->pluck('id');
        if ($tipoIds->isEmpty()) {
            return;
        }

        DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('tipo_id', $tipoIds)
            ->where('codigo', 'MES')
            ->update([
                'mueve_billetaje' => true,
                'updated_at' => now(),
            ]);
    }
};
