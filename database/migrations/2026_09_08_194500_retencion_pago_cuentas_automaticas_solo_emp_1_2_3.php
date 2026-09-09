<?php

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones OP: cuentas automáticas solo en empresas operativas 1, 2 y 3.
 */
return new class extends Migration
{
    private const CLAVES = [
        'pago.retencion_ganancias',
        'pago.retencion_iva',
        'pago.retencion_suss',
        'pago.retencion_iibb',
    ];

    private const EMPRESAS = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->whereIn('clave', self::CLAVES)
            ->whereNotIn('empresa_id', self::EMPRESAS)
            ->delete();

        if (! Schema::hasTable('empresa')) {
            return;
        }

        // Evita FK en lab Postgres / entornos sin empresas 1/2/3 todavía.
        $empresaIds = DB::table('empresa')
            ->whereIn('id', self::EMPRESAS)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($empresaIds === []) {
            return;
        }

        app(ContabilidadCuentaAutomaticaSeedService::class)->asegurarCatalogoEmpresas($empresaIds);
    }

    public function down(): void
    {
        // No recrea filas en empresas 4..N: el seed original ya no aplica.
    }
};
