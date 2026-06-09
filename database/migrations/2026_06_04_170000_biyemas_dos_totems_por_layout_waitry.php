<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biyemas: 2 tótems físicos por layout Waitry (Mostrador Tomasso + Kioscos Pizzería).
 */
return new class extends Migration
{
    private const EMPRESA_BIYEMAS = 1;

    private const LAYOUT_TOMASSO = 32211;

    private const LAYOUT_KIOSCO_1 = 32392;

    private const LAYOUT_KIOSCO_2 = 32393;

    private const TABLE_TOMASSO = 101066;

    private const TABLE_K1 = 103443;

    private const TABLE_K2 = 103444;

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_ids_adicionales')) {
            Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
                $table->string('waitry_layout_ids_adicionales', 255)->nullable()->after('waitry_layout_id');
            });
        }

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 1)
            ->update([
                'waitry_layout_id' => self::LAYOUT_TOMASSO,
                'waitry_layout_ids_adicionales' => null,
                'waitry_table_id' => self::TABLE_TOMASSO,
                'waitry_table_ids_adicionales' => null,
                'detalle' => 'Tótem Tomasso (Waitry layout Mostrador 32211)',
                'updated_at' => now(),
            ]);

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 2)
            ->update([
                'waitry_layout_id' => self::LAYOUT_KIOSCO_1,
                'waitry_layout_ids_adicionales' => (string) self::LAYOUT_KIOSCO_2,
                'waitry_table_id' => self::TABLE_K1,
                'waitry_table_ids_adicionales' => (string) self::TABLE_K2,
                'detalle' => 'Tótem Pizzería/Salón (Kiosco 1 layout 32392 + Kiosco 2 layout 32393)',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        if (Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_ids_adicionales')) {
            Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
                $table->dropColumn('waitry_layout_ids_adicionales');
            });
        }
    }
};
