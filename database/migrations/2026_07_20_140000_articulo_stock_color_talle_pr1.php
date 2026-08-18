<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PR1 stock dimensional color/talle:
 * - Flag en articulo
 * - color_id/talle_id en articulo_movimiento (nullable, FK real)
 * - Clave de saldo (articulo, deposito, color_id, talle_id) con 0 = sin variante
 *   (MySQL unique no colisiona con NULL; por eso el saldo usa 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo', 'maneja_stock_color_talle')) {
                $table->boolean('maneja_stock_color_talle')->default(false)->after('numeroparte');
            }
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_movimiento', 'color_id')) {
                $table->unsignedBigInteger('color_id')->nullable()->after('articulo_id');
            }
            if (! Schema::hasColumn('articulo_movimiento', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->nullable()->after('color_id');
            }
        });

        // FKs solo si no existen (idempotente ante re-runs parciales).
        $this->addForeignIfMissing('articulo_movimiento', 'fk_artmov_color', 'color_id', 'color');
        $this->addForeignIfMissing('articulo_movimiento', 'fk_artmov_talle', 'talle_id', 'talle');

        Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_saldo_deposito', 'color_id')) {
                $table->unsignedBigInteger('color_id')->default(0)->after('deposito_id');
            }
            if (! Schema::hasColumn('articulo_saldo_deposito', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->default(0)->after('color_id');
            }
        });

        // Filas existentes → variante 0/0.
        DB::table('articulo_saldo_deposito')->whereNull('color_id')->update(['color_id' => 0]);
        DB::table('articulo_saldo_deposito')->whereNull('talle_id')->update(['talle_id' => 0]);

        try {
            Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
                $table->dropUnique('uk_artsalddep_articulo_deposito');
            });
        } catch (\Throwable $e) {
            // Ya no existe.
        }

        try {
            Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
                $table->unique(
                    ['articulo_id', 'deposito_id', 'color_id', 'talle_id'],
                    'uk_artsalddep_art_dep_col_tal'
                );
            });
        } catch (\Throwable $e) {
            // Ya existe.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
                $table->dropUnique('uk_artsalddep_art_dep_col_tal');
            });
        } catch (\Throwable $e) {
        }

        // Antes de volver al unique viejo, consolidar variantes a una fila.
        // (down de emergencia: suma por articulo+deposito).
        $rows = DB::table('articulo_saldo_deposito')
            ->selectRaw('articulo_id, deposito_id, SUM(cantidad) AS total, MAX(fecha_ult_movimiento) AS ultima')
            ->groupBy('articulo_id', 'deposito_id')
            ->get();
        DB::table('articulo_saldo_deposito')->delete();
        $now = now();
        foreach ($rows as $row) {
            DB::table('articulo_saldo_deposito')->insert([
                'articulo_id' => (int) $row->articulo_id,
                'deposito_id' => (int) $row->deposito_id,
                'color_id' => 0,
                'talle_id' => 0,
                'cantidad' => (float) $row->total,
                'fecha_ult_movimiento' => $row->ultima,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
            $table->unique(['articulo_id', 'deposito_id'], 'uk_artsalddep_articulo_deposito');
            if (Schema::hasColumn('articulo_saldo_deposito', 'talle_id')) {
                $table->dropColumn('talle_id');
            }
            if (Schema::hasColumn('articulo_saldo_deposito', 'color_id')) {
                $table->dropColumn('color_id');
            }
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_artmov_talle');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign('fk_artmov_color');
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('articulo_movimiento', 'talle_id')) {
                $table->dropColumn('talle_id');
            }
            if (Schema::hasColumn('articulo_movimiento', 'color_id')) {
                $table->dropColumn('color_id');
            }
        });

        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'maneja_stock_color_talle')) {
                $table->dropColumn('maneja_stock_color_talle');
            }
        });
    }

    private function addForeignIfMissing(string $table, string $name, string $column, string $refTable): void
    {
        if (MigrationDialectSupport::tieneForeignKey($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $column, $refTable) {
            $blueprint->foreign($column, $name)
                ->references('id')->on($refTable)
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
