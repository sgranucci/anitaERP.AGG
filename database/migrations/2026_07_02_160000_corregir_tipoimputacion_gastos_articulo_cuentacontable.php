<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la 3.ª imputación contable por artículo/empresa cuando quedó como
 * IMPUESTOS INTERNOS en lugar de GASTOS (patrón VENTAS + COMPRAS + GASTOS).
 *
 * No modifica grupos de 4 filas que incluyen GASTOS e IMPUESTOS INTERNOS por separado.
 */
return new class extends Migration
{
    private const TABLA_BACKUP = '_fix_articulo_cuentacontable_tipo_gastos';

    public function up(): void
    {
        if (! Schema::hasTable('articulo_cuentacontable')) {
            return;
        }

        Schema::create(self::TABLA_BACKUP, function ($table) {
            $table->unsignedBigInteger('id')->primary();
        });

        DB::statement('
            INSERT INTO '.self::TABLA_BACKUP.' (id)
            SELECT acc.id
            FROM articulo_cuentacontable acc
            INNER JOIN (
                SELECT articulo_id, empresa_id
                FROM articulo_cuentacontable
                GROUP BY articulo_id, empresa_id
                HAVING COUNT(*) = 3
                    AND SUM(tipoimputacion = "VENTAS") = 1
                    AND SUM(tipoimputacion = "COMPRAS") = 1
                    AND SUM(tipoimputacion = "IMPUESTOS INTERNOS") = 1
                    AND SUM(tipoimputacion = "GASTOS") = 0
            ) g ON g.articulo_id = acc.articulo_id AND g.empresa_id = acc.empresa_id
            WHERE acc.tipoimputacion = "IMPUESTOS INTERNOS"
        ');

        DB::table('articulo_cuentacontable as acc')
            ->join(self::TABLA_BACKUP.' as b', 'b.id', '=', 'acc.id')
            ->update([
                'acc.tipoimputacion' => 'GASTOS',
                'acc.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_cuentacontable') || ! Schema::hasTable(self::TABLA_BACKUP)) {
            return;
        }

        DB::table('articulo_cuentacontable as acc')
            ->join(self::TABLA_BACKUP.' as b', 'b.id', '=', 'acc.id')
            ->update([
                'acc.tipoimputacion' => 'IMPUESTOS INTERNOS',
                'acc.updated_at' => now(),
            ]);

        Schema::dropIfExists(self::TABLA_BACKUP);
    }
};
