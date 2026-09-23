<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FerliRinNumeracionSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: numerador ERP para RIN de pedidos/facturación (ventas.rin.comprobante).
 * Semilla = max(venta.numerocomprobante) del tipo RIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('sistema_numerador') || ! Schema::hasTable('venta')) {
            return;
        }

        $piso = FerliRinNumeracionSupport::pisoDesdeVentasRin();
        FerliRinNumeracionSupport::asegurarFila(1, $piso);
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
            ->where('codigo', FerliRinNumeracionSupport::CODIGO)
            ->delete();
    }
};
