<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice (empresa_id, fecha) en asiento para los reportes de período (Totales por centro de costo,
 * conciliación sin CC y similares). Sin él, el filtro por empresa + rango de fechas recorre toda la tabla.
 */
return new class extends Migration
{
    private const INDEX = 'asiento_empresa_fecha_idx';

    public function up(): void
    {
        if (! Schema::hasTable('asiento') || $this->tieneIndice()) {
            return;
        }

        Schema::table('asiento', function (Blueprint $table) {
            $table->index(['empresa_id', 'fecha'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asiento') || ! $this->tieneIndice()) {
            return;
        }

        Schema::table('asiento', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function tieneIndice(): bool
    {
        foreach (Schema::getIndexes('asiento') as $indice) {
            if (($indice['name'] ?? '') === self::INDEX) {
                return true;
            }
            if (($indice['columns'] ?? []) === ['empresa_id', 'fecha']) {
                return true;
            }
        }

        return false;
    }
};
