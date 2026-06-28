<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera subconsultas de depósito en artículos vendidos gastronomía
 * (filtro por fechajornada en articulo_movimiento).
 */
return new class extends Migration
{
    private const INDEX = 'idx_articulo_movimiento_fechajornada';

    public function up(): void
    {
        if (! Schema::hasTable('articulo_movimiento') || ! Schema::hasColumn('articulo_movimiento', 'fechajornada')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table): void {
            if (! $this->indexExists('articulo_movimiento', self::INDEX)) {
                $table->index('fechajornada', self::INDEX);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_movimiento')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table): void {
            if ($this->indexExists('articulo_movimiento', self::INDEX)) {
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
