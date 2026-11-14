<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: tipificar tipos de bóveda para el reporte propinas por casino,
 * y baja del subtipo PROP/Mesas (la propina de mesa queda en el movimiento de mesa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->agregarFlagTipo();
        $this->marcarPropina();
        $this->bajaSubtipoMesas();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda_tipo')
            && Schema::hasColumn('tesoreria_crown_boveda_tipo', 'para_reporte_propina')) {
            Schema::table('tesoreria_crown_boveda_tipo', function (Blueprint $table) {
                $table->dropColumn('para_reporte_propina');
            });
        }
    }

    private function agregarFlagTipo(): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }
        if (Schema::hasColumn('tesoreria_crown_boveda_tipo', 'para_reporte_propina')) {
            return;
        }
        Schema::table('tesoreria_crown_boveda_tipo', function (Blueprint $table) {
            $table->boolean('para_reporte_propina')->default(false)->after('activo');
        });
    }

    private function marcarPropina(): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasColumn('tesoreria_crown_boveda_tipo', 'para_reporte_propina')) {
            return;
        }

        DB::table('tesoreria_crown_boveda_tipo')
            ->where('abreviatura', 'PROP')
            ->update([
                'para_reporte_propina' => true,
                'updated_at' => now(),
            ]);
    }

    private function bajaSubtipoMesas(): void
    {
        if (! Schema::hasTable('tesoreria_crown_boveda_subtipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $subtipoIds = DB::table('tesoreria_crown_boveda_subtipo as st')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'st.tipo_id')
            ->where('t.abreviatura', 'PROP')
            ->where('st.codigo', 'MES')
            ->pluck('st.id');

        if ($subtipoIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda')) {
            DB::table('tesoreria_crown_boveda')
                ->whereIn('subtipo_id', $subtipoIds)
                ->update([
                    'subtipo_id' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('id', $subtipoIds)
            ->delete();
    }
};
