<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PR4: color/talle en líneas de recepción proveedor (desde OC → stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'color_id')) {
                $table->unsignedBigInteger('color_id')->nullable()->after('articulo_id');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->nullable()->after('color_id');
            }
        });
        $this->addForeignIfMissing('recepcion_proveedor_articulo', 'fk_recprovart_color', 'color_id', 'color');
        $this->addForeignIfMissing('recepcion_proveedor_articulo', 'fk_recprovart_talle', 'talle_id', 'talle');
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $blueprint) {
            try {
                $blueprint->dropForeign('fk_recprovart_talle');
            } catch (\Throwable $e) {
            }
            try {
                $blueprint->dropForeign('fk_recprovart_color');
            } catch (\Throwable $e) {
            }
        });
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $blueprint) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'talle_id')) {
                $blueprint->dropColumn('talle_id');
            }
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'color_id')) {
                $blueprint->dropColumn('color_id');
            }
        });
    }

    private function addForeignIfMissing(string $table, string $name, string $column, string $refTable): void
    {
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS n FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'FOREIGN KEY']
        );
        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $column, $refTable) {
            $blueprint->foreign($column, $name)
                ->references('id')->on($refTable)
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
