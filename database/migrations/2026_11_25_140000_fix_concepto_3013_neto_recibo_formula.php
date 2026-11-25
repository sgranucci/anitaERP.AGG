<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anita listados (p.ej. 69 ACREDITACION) leen 3013 NETO RECIBO.
 * En el ERP quedó con fórmula "0" y va_recibo=0, así que el calculador lo omitía
 * y el neto informativo solo quedaba en 3009. Alias: im(3009).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('concepto_sueldos')
            ->where('codigo', 3013)
            ->where(function ($q) {
                $q->whereNull('formula')
                    ->orWhere('formula', '')
                    ->orWhere('formula', '0');
            })
            ->update([
                'formula' => 'im(3009)',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('concepto_sueldos')
            ->where('codigo', 3013)
            ->where('formula', 'im(3009)')
            ->update([
                'formula' => '0',
                'updated_at' => now(),
            ]);
    }
};
