<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caja física (tabla caja) asociada a cada terminal PC.
 * En AGG se usa para recepción de rendiciones sin asignación diaria de cajero.
 */
return new class extends Migration
{
    /** @var array<string, string> tabla => nombre FK */
    private const TABLAS = [
        'configuracion_puntoventa_estacionamiento' => 'fk_cfg_pv_est_caja',
        'configuracion_puntoventa_gastronomia' => 'fk_cfg_pv_gas_caja',
        'configuracion_puntoventa_bingo' => 'fk_cfg_pv_bin_caja',
    ];

    public function up(): void
    {
        $cajaDefaultId = (int) DB::table('caja')->orderBy('id')->value('id');
        if ($cajaDefaultId <= 0) {
            $cajaDefaultId = 1;
            // Query Builder: whereKey() se interpreta como where('key', …); usar id explícito.
            if (! DB::table('caja')->where('id', 1)->exists()) {
                DB::table('caja')->insert([
                    'id' => 1,
                    'nombre' => 'Caja 1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (self::TABLAS as $tabla => $fkNombre) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            if (Schema::hasColumn($tabla, 'caja_id')) {
                DB::table($tabla)->whereNull('caja_id')->update(['caja_id' => $cajaDefaultId]);
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($fkNombre) {
                $table->unsignedBigInteger('caja_id')->nullable()->after('empresa_id');
                $table->foreign('caja_id', $fkNombre)
                    ->references('id')->on('caja')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
            });

            DB::table($tabla)->whereNull('caja_id')->update(['caja_id' => $cajaDefaultId]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla => $fkNombre) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'caja_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($fkNombre) {
                $table->dropForeign($fkNombre);
                $table->dropColumn('caja_id');
            });
        }
    }
};
