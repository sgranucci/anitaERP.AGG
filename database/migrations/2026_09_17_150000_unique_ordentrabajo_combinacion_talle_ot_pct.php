<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evita OCT duplicados (mismo OT + mismo PCT) que duplican pares al facturar.
 * Requiere: php artisan ventas:limpiar-oct-duplicados --ejecutar
 */
return new class extends Migration
{
    public function up(): void
    {
        $dup = DB::table('ordentrabajo_combinacion_talle')
            ->select('ordentrabajo_id', 'pedido_combinacion_talle_id')
            ->groupBy('ordentrabajo_id', 'pedido_combinacion_talle_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($dup) {
            throw new \RuntimeException(
                'Quedan duplicados en ordentrabajo_combinacion_talle. '
                .'Ejecute: php artisan ventas:limpiar-oct-duplicados --ejecutar'
            );
        }

        Schema::table('ordentrabajo_combinacion_talle', function (Blueprint $table) {
            $table->unique(
                ['ordentrabajo_id', 'pedido_combinacion_talle_id'],
                'uk_oct_ordentrabajo_pct'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ordentrabajo_combinacion_talle', function (Blueprint $table) {
            $table->dropUnique('uk_oct_ordentrabajo_pct');
        });
    }
};
