<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera MAX(numerocomprobante) en numeración CAEA (VentaNumeracionEmpresaSupport).
 *
 * Filtro típico: puntoventa_id + join tipotransaccion + MAX(numerocomprobante).
 */
return new class extends Migration
{
    private const INDEX = 'idx_venta_pv_tt_numerocomprobante';

    public function up(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table): void {
            if (! $this->indexExists('venta', self::INDEX)) {
                $table->index(
                    ['puntoventa_id', 'tipotransaccion_id', 'numerocomprobante'],
                    self::INDEX,
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table): void {
            if ($this->indexExists('venta', self::INDEX)) {
                $table->dropIndex(self::INDEX);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }
};
