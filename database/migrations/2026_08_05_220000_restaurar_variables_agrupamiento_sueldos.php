<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura agr_variable1..4 (Anita VAG1..VAG4). Se habían eliminado creyendo que no
 * se usaban; el premio fallo de caja (concepto 190) y otras fórmulas leen VAG1.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNAS = ['variable1', 'variable2', 'variable3', 'variable4'];

    public function up(): void
    {
        Schema::table('agrupamiento_sueldos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $col) {
                if (! Schema::hasColumn('agrupamiento_sueldos', $col)) {
                    $table->decimal($col, 15, 2)->default(0);
                }
            }
        });
    }

    public function down(): void
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
};
