<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera búsqueda de artículos de compra por SKU alt./insumo
 * (fórmulas, mov. stock depósito fórmulas, recepción proveedor).
 */
return new class extends Migration
{
    private const INDEX_SKU_ALT = 'idx_articulo_skualternativo';

    private const INDEX_EMPRESA_SKU_ALT = 'idx_articulo_empresa_skualternativo';

    public function up(): void
    {
        if (! Schema::hasTable('articulo') || ! Schema::hasColumn('articulo', 'skualternativo')) {
            return;
        }

        Schema::table('articulo', function (Blueprint $table): void {
            if (! $this->indexExists('articulo', self::INDEX_SKU_ALT)) {
                $table->index('skualternativo', self::INDEX_SKU_ALT);
            }
            if (Schema::hasColumn('articulo', 'empresa_id')
                && ! $this->indexExists('articulo', self::INDEX_EMPRESA_SKU_ALT)) {
                $table->index(['empresa_id', 'skualternativo'], self::INDEX_EMPRESA_SKU_ALT);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo')) {
            return;
        }

        Schema::table('articulo', function (Blueprint $table): void {
            if ($this->indexExists('articulo', self::INDEX_EMPRESA_SKU_ALT)) {
                $table->dropIndex(self::INDEX_EMPRESA_SKU_ALT);
            }
            if ($this->indexExists('articulo', self::INDEX_SKU_ALT)) {
                $table->dropIndex(self::INDEX_SKU_ALT);
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
