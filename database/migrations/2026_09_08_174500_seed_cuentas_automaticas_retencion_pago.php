<?php

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta de claves pago.retencion_* en contabilidad_cuenta_automatica
 * (solo empresas operativas 1, 2 y 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('empresa')) {
            return;
        }

        $empresaIds = [1, 2, 3];
        app(ContabilidadCuentaAutomaticaSeedService::class)->asegurarCatalogoEmpresas($empresaIds);
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->whereIn('clave', [
                'pago.retencion_ganancias',
                'pago.retencion_iva',
                'pago.retencion_suss',
                'pago.retencion_iibb',
            ])
            ->delete();
    }
};
