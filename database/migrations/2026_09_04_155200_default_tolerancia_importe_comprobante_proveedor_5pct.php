<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default de tolerancia importe factura vs COM (sin CC específico): 5%.
 * Antes el seed dejaba 0% y devolvía legajos por diferencias de centavos.
 */
return new class extends Migration
{
    private const PCT_DEFAULT = 5.0;

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('configuracion_comprobante_proveedor_tolerancia')) {
            return;
        }

        DB::table('configuracion_comprobante_proveedor_tolerancia')
            ->whereNull('centrocosto_id')
            ->update([
                'tolerancia_importe_pct' => self::PCT_DEFAULT,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('configuracion_comprobante_proveedor_tolerancia')) {
            return;
        }

        DB::table('configuracion_comprobante_proveedor_tolerancia')
            ->whereNull('centrocosto_id')
            ->where('tolerancia_importe_pct', self::PCT_DEFAULT)
            ->update([
                'tolerancia_importe_pct' => 0,
                'updated_at' => now(),
            ]);
    }
};
