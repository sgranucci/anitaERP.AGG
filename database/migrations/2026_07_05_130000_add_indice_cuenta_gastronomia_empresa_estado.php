<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera auto-descarte de cuentas vacías (empresa + estado) y acorta ventana de lock.
 */
return new class extends Migration
{
    private const INDEX = 'idx_cuenta_gastro_empresa_estado';

    public function up(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table): void {
            if (! $this->indexExists('cuenta_gastronomia', self::INDEX)) {
                $table->index(['empresa_id', 'estado'], self::INDEX);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table): void {
            if ($this->indexExists('cuenta_gastronomia', self::INDEX)) {
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
