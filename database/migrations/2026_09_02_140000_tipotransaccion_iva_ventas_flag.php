<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca por tipo de transacción: si el comprobante entra al listado IVA ventas.
 * Equivale a Anita t_comp.tcomp_subdiar = V.
 */
return new class extends Migration
{
    /** Tipos internos que no van al subdiario IVA ventas. */
    private const EXCLUIDOS = ['PRE', 'IZV'];

    public function up(): void
    {
        if (Schema::hasTable('tipotransaccion')
            && ! Schema::hasColumn('tipotransaccion', 'iva_ventas')) {
            Schema::table('tipotransaccion', function (Blueprint $table) {
                $table->boolean('iva_ventas')->default(false)->after('estado');
            });
        }

        if (! Schema::hasColumn('tipotransaccion', 'iva_ventas')) {
            return;
        }

        DB::table('tipotransaccion')->update(['iva_ventas' => 1, 'updated_at' => now()]);

        DB::table('tipotransaccion')
            ->whereIn('abreviatura', self::EXCLUIDOS)
            ->update(['iva_ventas' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tipotransaccion')
            && Schema::hasColumn('tipotransaccion', 'iva_ventas')) {
            Schema::table('tipotransaccion', function (Blueprint $table) {
                $table->dropColumn('iva_ventas');
            });
        }
    }
};
