<?php

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reasigna pago.retencion_* (y rellena NULL) con códigos AGG 213100005/014/008/007.
 * El seed previo buscaba 214010013/021/015/014 inexistentes y dejaba cuentacontable_id NULL:
 * el asiento de OP omitía retenciones en silencio.
 */
return new class extends Migration
{
    private const CLAVES = [
        'pago.retencion_ganancias',
        'pago.retencion_iva',
        'pago.retencion_suss',
        'pago.retencion_iibb',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('empresa')) {
            return;
        }

        $empresaIds = DB::table('empresa')
            ->whereIn('id', [1, 2, 3])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($empresaIds === []) {
            return;
        }

        // Forzar re-resolución: deja en NULL las que siguen sin cuenta o con id inválido.
        // asegurarCatalogoEmpresa solo completa cuando cuentacontable_id es null.
        DB::table('contabilidad_cuenta_automatica')
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('clave', self::CLAVES)
            ->where(function ($q) {
                $q->whereNull('cuentacontable_id')->orWhere('cuentacontable_id', '<=', 0);
            })
            ->update(['cuentacontable_id' => null, 'updated_at' => now()]);

        app(ContabilidadCuentaAutomaticaSeedService::class)->asegurarCatalogoEmpresas($empresaIds);
    }

    public function down(): void
    {
        // No revierte a códigos 2140… (inexistentes). Dejar cuentas AGG.
    }
};
