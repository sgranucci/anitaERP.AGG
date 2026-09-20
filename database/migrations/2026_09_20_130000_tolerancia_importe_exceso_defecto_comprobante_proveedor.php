<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tolerancia de importe factura vs provisión COM con límite superior e inferior por separado.
 *
 * Antes había un único porcentaje simétrico: una factura parcial (menor que la provisión) se
 * marcaba igual que una sobrefacturada y, en modo estricto, devolvía el legajo a COMPRAS con
 * correo. Se separan los sentidos siguiendo el criterio de OMR6/T169G en SAP: límite superior e
 * inferior independientes, cada uno en porcentaje y/o valor absoluto, y NULL = «no verificar».
 *
 * Backfill: ambos sentidos toman el porcentaje simétrico vigente, así el comportamiento no cambia
 * hasta que alguien edite la configuración.
 */
return new class extends Migration
{
    private const TABLA = 'configuracion_comprobante_proveedor_tolerancia';

    /** @var list<string> */
    private const COLUMNAS = [
        'tolerancia_exceso_pct',
        'tolerancia_defecto_pct',
        'tolerancia_exceso_abs',
        'tolerancia_defecto_abs',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLA, 'tolerancia_exceso_pct')) {
                $table->decimal('tolerancia_exceso_pct', 8, 4)->nullable()->after('tolerancia_importe_pct')
                    ->comment('Límite superior %: factura mayor que la provisión COM. NULL = no verificar');
            }
            if (! Schema::hasColumn(self::TABLA, 'tolerancia_defecto_pct')) {
                $table->decimal('tolerancia_defecto_pct', 8, 4)->nullable()->after('tolerancia_exceso_pct')
                    ->comment('Límite inferior %: factura menor que la provisión COM. NULL = no verificar');
            }
            if (! Schema::hasColumn(self::TABLA, 'tolerancia_exceso_abs')) {
                $table->decimal('tolerancia_exceso_abs', 18, 2)->nullable()->after('tolerancia_defecto_pct')
                    ->comment('Límite superior en importe absoluto. NULL = no verificar');
            }
            if (! Schema::hasColumn(self::TABLA, 'tolerancia_defecto_abs')) {
                $table->decimal('tolerancia_defecto_abs', 18, 2)->nullable()->after('tolerancia_exceso_abs')
                    ->comment('Límite inferior en importe absoluto. NULL = no verificar');
            }
        });

        // Preserva el comportamiento simétrico vigente en las filas ya configuradas.
        DB::table(self::TABLA)
            ->whereNull('tolerancia_exceso_pct')
            ->whereNull('tolerancia_defecto_pct')
            ->update([
                'tolerancia_exceso_pct' => DB::raw('tolerancia_importe_pct'),
                'tolerancia_defecto_pct' => DB::raw('tolerancia_importe_pct'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        $existentes = array_values(array_filter(
            self::COLUMNAS,
            fn (string $col) => Schema::hasColumn(self::TABLA, $col)
        ));
        if ($existentes === []) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) use ($existentes) {
            $table->dropColumn($existentes);
        });
    }
};
