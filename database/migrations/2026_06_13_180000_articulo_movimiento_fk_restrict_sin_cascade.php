<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * articulo_movimiento: FKs padre CASCADE → RESTRICT.
 *
 * Obliga a borrar cada movimiento vía Eloquent (observers en Venta, Venta_Emision,
 * MovimientoStock, Pedido_Combinacion, Ordentrabajo) para mantener articulo_saldo_deposito.
 */
return new class extends Migration
{
    /** @var list<array{constraint: string, column: string, table: string}> */
    private const FOREIGN_KEYS = [
        ['constraint' => 'fk_articulo_movimiento_venta', 'column' => 'venta_id', 'table' => 'venta'],
        ['constraint' => 'fk_articulo_movimiento_movimientostock', 'column' => 'movimientostock_id', 'table' => 'movimientostock'],
        ['constraint' => 'fk_articulo_movimiento_pedido_combinacion', 'column' => 'pedido_combinacion_id', 'table' => 'pedido_combinacion'],
        ['constraint' => 'fk_articulo_movimiento_ordentrabajo', 'column' => 'ordentrabajo_id', 'table' => 'ordentrabajo'],
        ['constraint' => 'fk_articulo_movimiento_venta_emision', 'column' => 'venta_emision_id', 'table' => 'venta_emision'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('articulo_movimiento')) {
            return;
        }

        foreach (self::FOREIGN_KEYS as $fk) {
            if (! Schema::hasColumn('articulo_movimiento', $fk['column'])) {
                continue;
            }

            $this->limpiarReferenciasHuerfanas($fk['column'], $fk['table']);

            if ($this->foreignKeyExists($fk['constraint'])) {
                Schema::table('articulo_movimiento', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk['constraint']);
                });
            }

            Schema::table('articulo_movimiento', function (Blueprint $table) use ($fk) {
                $this->addRestrictForeign($table, $fk['constraint'], $fk['column']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_movimiento')) {
            return;
        }

        foreach (self::FOREIGN_KEYS as $fk) {
            if (! $this->foreignKeyExists($fk['constraint'])) {
                continue;
            }

            Schema::table('articulo_movimiento', function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk['constraint']);
            });

            Schema::table('articulo_movimiento', function (Blueprint $table) use ($fk) {
                $this->addCascadeForeign($table, $fk['constraint'], $fk['column']);
            });
        }
    }

    private function limpiarReferenciasHuerfanas(string $column, string $parentTable): void
    {
        if (! Schema::hasTable($parentTable)) {
            return;
        }

        DB::statement(
            'UPDATE articulo_movimiento am
             LEFT JOIN '.$parentTable.' p ON p.id = am.'.$column.'
             SET am.'.$column.' = NULL
             WHERE am.'.$column.' IS NOT NULL AND p.id IS NULL'
        );
    }

    private function foreignKeyExists(string $constraint): bool
    {
        $row = Schema::getConnection()->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['articulo_movimiento', $constraint, 'FOREIGN KEY'],
        );

        return $row !== null;
    }

    private function addRestrictForeign(Blueprint $table, string $constraint, string $column): void
    {
        match ($column) {
            'venta_id' => $table->foreign('venta_id', $constraint)
                ->references('id')->on('venta')->onDelete('restrict')->onUpdate('cascade'),
            'movimientostock_id' => $table->foreign('movimientostock_id', $constraint)
                ->references('id')->on('movimientostock')->onDelete('restrict')->onUpdate('cascade'),
            'pedido_combinacion_id' => $table->foreign('pedido_combinacion_id', $constraint)
                ->references('id')->on('pedido_combinacion')->onDelete('restrict')->onUpdate('cascade'),
            'ordentrabajo_id' => $table->foreign('ordentrabajo_id', $constraint)
                ->references('id')->on('ordentrabajo')->onDelete('restrict')->onUpdate('cascade'),
            'venta_emision_id' => $table->foreign('venta_emision_id', $constraint)
                ->references('id')->on('venta_emision')->onDelete('restrict')->onUpdate('cascade'),
            default => null,
        };
    }

    private function addCascadeForeign(Blueprint $table, string $constraint, string $column): void
    {
        match ($column) {
            'venta_id' => $table->foreign('venta_id', $constraint)
                ->references('id')->on('venta')->onDelete('cascade'),
            'movimientostock_id' => $table->foreign('movimientostock_id', $constraint)
                ->references('id')->on('movimientostock')->onDelete('cascade'),
            'pedido_combinacion_id' => $table->foreign('pedido_combinacion_id', $constraint)
                ->references('id')->on('pedido_combinacion')->onDelete('cascade'),
            'ordentrabajo_id' => $table->foreign('ordentrabajo_id', $constraint)
                ->references('id')->on('ordentrabajo')->onDelete('cascade'),
            'venta_emision_id' => $table->foreign('venta_emision_id', $constraint)
                ->references('id')->on('venta_emision')->onDelete('cascade')->onUpdate('cascade'),
            default => null,
        };
    }
};
