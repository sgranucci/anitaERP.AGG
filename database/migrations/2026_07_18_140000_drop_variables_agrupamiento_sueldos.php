<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las variables numéricas de agrupamiento no se usan (variable2/3/4 siempre 0; variable1
 * solo replicaba el monto base del fallo). Se eliminan. Idempotente: en entornos nuevos
 * la tabla ya se crea sin estas columnas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNAS = ['variable1', 'variable2', 'variable3', 'variable4'];

    public function up(): void
    {
        $existentes = array_values(array_filter(
            self::COLUMNAS,
            fn ($col) => Schema::hasColumn('agrupamiento_sueldos', $col)
        ));

        if ($existentes === []) {
            return;
        }

        Schema::table('agrupamiento_sueldos', function (Blueprint $table) use ($existentes) {
            $table->dropColumn($existentes);
        });
    }

    public function down(): void
    {
        Schema::table('agrupamiento_sueldos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $col) {
                if (! Schema::hasColumn('agrupamiento_sueldos', $col)) {
                    $table->decimal($col, 15, 2)->default(0);
                }
            }
        });
    }
};
