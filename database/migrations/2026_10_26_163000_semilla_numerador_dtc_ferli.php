<?php

use App\Models\Configuracion\Empresa;
use App\Support\Caja\IngresoEgresoDtcNumeracionSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: numerador ERP del DTC (caja.dtc).
 * Semilla = max(comprobante_proveedor.numerocomprobante) del tipo DTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('sistema_numerador') || ! Schema::hasTable('comprobante_proveedor')) {
            return;
        }

        $piso = IngresoEgresoDtcNumeracionSupport::pisoDesdeComprobantes();
        foreach (Empresa::query()->orderBy('id')->pluck('id') as $empresaId) {
            IngresoEgresoDtcNumeracionSupport::asegurarFila((int) $empresaId, $piso);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('sistema_numerador')) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('sistema_numerador')
            ->where('codigo', IngresoEgresoDtcNumeracionSupport::CODIGO)
            ->delete();
    }
};
