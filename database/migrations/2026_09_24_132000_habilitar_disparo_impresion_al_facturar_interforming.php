<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interforming: al facturar PED/PEX → FAE, abrir sesión de impresión en automático.
 * El flag enviar_automatico_al_facturar ya estaba en 1; faltaba permite_disparo_al_grabar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        DB::table('comprobante_impresion_programa')
            ->where('codigo', 'DEFAULT')
            ->update([
                'permite_disparo_al_grabar' => true,
                'enviar_automatico_al_facturar' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }

        DB::table('comprobante_impresion_programa')
            ->where('codigo', 'DEFAULT')
            ->update([
                'permite_disparo_al_grabar' => false,
                'updated_at' => now(),
            ]);
    }
};
