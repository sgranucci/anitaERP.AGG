<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para "última entrada de compra por artículo" (ArticuloPrecioUltimaCompraSupport::IDX_ULTIMA_ENTRADA).
 * En producción se creó a mano el 2026-09-03 con ALGORITHM=INPLACE, LOCK=NONE en ventana de bajo uso;
 * acá queda idempotente para el resto de los entornos.
 */
return new class extends Migration
{
    private const TABLA = 'articulo_movimiento';

    private const INDICE = 'idx_am_ultima_entrada';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA) || $this->existeIndice()) {
            return;
        }

        // ~1M filas en producción: online (INPLACE/LOCK=NONE) para no cortar DML; en otros motores, ADD INDEX común.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (`articulo_id`, `fecha`, `id`, `cantidad`, `precio`), ALGORITHM=INPLACE, LOCK=NONE',
                self::TABLA,
                self::INDICE,
            ));

            return;
        }

        Schema::table(self::TABLA, function ($table) {
            $table->index(['articulo_id', 'fecha', 'id', 'cantidad', 'precio'], self::INDICE);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA) || ! $this->existeIndice()) {
            return;
        }

        Schema::table(self::TABLA, function ($table) {
            $table->dropIndex(self::INDICE);
        });
    }

    private function existeIndice(): bool
    {
        foreach (Schema::getIndexes(self::TABLA) as $idx) {
            if (strcasecmp((string) ($idx['name'] ?? ''), self::INDICE) === 0) {
                return true;
            }
        }

        return false;
    }
};
