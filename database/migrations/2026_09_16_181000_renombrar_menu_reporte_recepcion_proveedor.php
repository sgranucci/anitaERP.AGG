<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Distingue el ítem de reporte bajo Stock → Reportes del proceso
 * «Recepción proveedores», para que en Menú–Rol no se confundan.
 */
return new class extends Migration
{
    private const URL = 'stock/reporte-recepcion-proveedor';

    private const NOMBRE_NUEVO = 'Reporte recepción proveedores';

    private const NOMBRE_ANTERIOR = 'Recepción de proveedores';

    public function up(): void
    {
        DB::table('menu')->where('url', self::URL)->update([
            'nombre' => self::NOMBRE_NUEVO,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu')->where('url', self::URL)->update([
            'nombre' => self::NOMBRE_ANTERIOR,
            'updated_at' => now(),
        ]);
    }
};
