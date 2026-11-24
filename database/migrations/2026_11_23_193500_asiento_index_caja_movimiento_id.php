<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index IE listado: asiento no tenía índice en caja_movimiento_id.
 * Con ~630k asientos, cada lookup OPV hacía full scan (~300–800 ms).
 */
return new class extends Migration
{
    private const INDEX = 'asiento_caja_movimiento_id_idx';

    public function up(): void
    {
        if (! Schema::hasTable('asiento') || ! Schema::hasColumn('asiento', 'caja_movimiento_id')) {
            return;
        }
        if ($this->tieneIndiceCajaMovimiento()) {
            return;
        }

        Schema::table('asiento', function (Blueprint $table) {
            $table->index('caja_movimiento_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asiento') || ! $this->tieneIndiceCajaMovimiento()) {
            return;
        }

        Schema::table('asiento', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function tieneIndiceCajaMovimiento(): bool
    {
        foreach (Schema::getIndexes('asiento') as $indice) {
            $columnas = $indice['columns'] ?? [];
            if ($columnas === ['caja_movimiento_id']) {
                return true;
            }
            if (($indice['name'] ?? '') === self::INDEX) {
                return true;
            }
        }

        return false;
    }
};
