<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evita que vuelvan a grabarse dos filas del mismo talle en un ítem de pedido.
 * Requiere haber corrido antes: php artisan ventas:limpiar-talles-pedido-duplicados --ejecutar
 */
return new class extends Migration
{
    public function up(): void
    {
        $dup = DB::table('pedido_combinacion_talle')
            ->select('pedido_combinacion_id', 'talle_id')
            ->groupBy('pedido_combinacion_id', 'talle_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($dup) {
            throw new \RuntimeException(
                'Quedan duplicados en pedido_combinacion_talle. '
                .'Ejecute: php artisan ventas:limpiar-talles-pedido-duplicados --ejecutar'
            );
        }

        Schema::table('pedido_combinacion_talle', function (Blueprint $table) {
            $table->unique(
                ['pedido_combinacion_id', 'talle_id'],
                'uk_pedido_combinacion_talle_item_talle'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pedido_combinacion_talle', function (Blueprint $table) {
            $table->dropUnique('uk_pedido_combinacion_talle_item_talle');
        });
    }
};
