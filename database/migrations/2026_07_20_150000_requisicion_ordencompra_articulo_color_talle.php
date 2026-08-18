<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PR3: color/talle en líneas de requisición y orden de compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_articulo', 'color_id')) {
                $table->unsignedBigInteger('color_id')->nullable()->after('articulo_id');
            }
            if (! Schema::hasColumn('requisicion_articulo', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->nullable()->after('color_id');
            }
        });
        $this->addForeignIfMissing('requisicion_articulo', 'fk_reqart_color', 'color_id', 'color');
        $this->addForeignIfMissing('requisicion_articulo', 'fk_reqart_talle', 'talle_id', 'talle');

        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'color_id')) {
                $table->unsignedBigInteger('color_id')->nullable()->after('articulo_id');
            }
            if (! Schema::hasColumn('ordencompra_articulo', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->nullable()->after('color_id');
            }
        });
        $this->addForeignIfMissing('ordencompra_articulo', 'fk_ocart_color', 'color_id', 'color');
        $this->addForeignIfMissing('ordencompra_articulo', 'fk_ocart_talle', 'talle_id', 'talle');
    }

    public function down(): void
    {
        foreach ([
            ['ordencompra_articulo', 'fk_ocart_talle', 'fk_ocart_color'],
            ['requisicion_articulo', 'fk_reqart_talle', 'fk_reqart_color'],
        ] as [$table, $fkTalle, $fkColor]) {
            Schema::table($table, function (Blueprint $blueprint) use ($fkTalle, $fkColor) {
                try {
                    $blueprint->dropForeign($fkTalle);
                } catch (\Throwable $e) {
                }
                try {
                    $blueprint->dropForeign($fkColor);
                } catch (\Throwable $e) {
                }
            });
            Schema::table($table, function (Blueprint $blueprint) {
                if (Schema::hasColumn($blueprint->getTable(), 'talle_id')) {
                    $blueprint->dropColumn('talle_id');
                }
                if (Schema::hasColumn($blueprint->getTable(), 'color_id')) {
                    $blueprint->dropColumn('color_id');
                }
            });
        }
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
