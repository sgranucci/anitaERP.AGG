<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deleted_at en asiento / caja / kardex: columna sin trait y 0 filas blandas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'asiento',
        'asiento_movimiento',
        'caja_movimiento',
        'caja_movimiento_cuentacaja',
        'caja_movimiento_estado',
        'caja_movimiento_archivo',
        'articulo_movimiento',
        'articulo_movimiento_talle',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};
