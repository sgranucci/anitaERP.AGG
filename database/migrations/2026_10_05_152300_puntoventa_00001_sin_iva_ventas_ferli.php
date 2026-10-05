<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: la sucursal 00001 (LINEA FERLI) no entra al subdiario IVA ventas.
 * En 2026 solo aporta NCA de ajuste; cobranzas y remitos ya quedan afuera por tipo.
 */
return new class extends Migration
{
    private const CODIGO = '00001';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('puntoventa') || ! Schema::hasColumn('puntoventa', 'iva_ventas')) {
            return;
        }

        DB::table('puntoventa')
            ->where('codigo', self::CODIGO)
            ->update([
                'iva_ventas' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('puntoventa') || ! Schema::hasColumn('puntoventa', 'iva_ventas')) {
            return;
        }

        DB::table('puntoventa')
            ->where('codigo', self::CODIGO)
            ->update([
                'iva_ventas' => true,
                'updated_at' => now(),
            ]);
    }
};
