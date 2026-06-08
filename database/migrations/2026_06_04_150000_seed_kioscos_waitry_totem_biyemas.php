<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biyemas (empresa 1): separa Kiosco 1 y Kiosco 2 en tótems Waitry por layout (getOrdersPOS).
 */
return new class extends Migration
{
    private const EMPRESA_BIYEMAS = 1;

    private const LAYOUT_KIOSCO_1 = 32392;

    private const LAYOUT_KIOSCO_2 = 32393;

    private const TABLE_K1 = 103443;

    private const TABLE_K2 = 103444;

    public function up(): void
    {
        if (! Schema::hasTable('ubicaciones_gastronomia') || ! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_id')) {
            return;
        }

        $now = now();

        $ubicacionK1 = $this->resolverUbicacionId('Kiosco 1', $now);
        $ubicacionK2 = $this->resolverUbicacionId('Kiosco 2', $now);

        $totemK1 = DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 2)
            ->first();

        if ($totemK1 !== null) {
            DB::table('totem_waitry_gastronomia')
                ->where('id', 2)
                ->update([
                    'ubicacion_id' => $ubicacionK1,
                    'waitry_layout_id' => self::LAYOUT_KIOSCO_1,
                    'waitry_table_id' => self::TABLE_K1,
                    'waitry_table_ids_adicionales' => null,
                    'detalle' => 'Tótem Kiosco 1 (Waitry layout Kiosco 1, mesa K1)',
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('totem_waitry_gastronomia')->insert([
                'empresa_id' => self::EMPRESA_BIYEMAS,
                'ubicacion_id' => $ubicacionK1,
                'waitry_layout_id' => self::LAYOUT_KIOSCO_1,
                'waitry_table_id' => self::TABLE_K1,
                'waitry_table_ids_adicionales' => null,
                'detalle' => 'Tótem Kiosco 1 (Waitry layout Kiosco 1, mesa K1)',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existeK2 = DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('waitry_layout_id', self::LAYOUT_KIOSCO_2)
            ->exists();

        if (! $existeK2) {
            DB::table('totem_waitry_gastronomia')->insert([
                'empresa_id' => self::EMPRESA_BIYEMAS,
                'ubicacion_id' => $ubicacionK2,
                'waitry_layout_id' => self::LAYOUT_KIOSCO_2,
                'waitry_table_id' => self::TABLE_K2,
                'waitry_table_ids_adicionales' => null,
                'detalle' => 'Tótem Kiosco 2 (Waitry layout Kiosco 2, mesa K2)',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia') || ! Schema::hasTable('ubicaciones_gastronomia')) {
            return;
        }

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('waitry_layout_id', self::LAYOUT_KIOSCO_2)
            ->delete();

        $salonId = DB::table('ubicaciones_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('nombre', 'Salon')
            ->value('id');

        if ($salonId !== null) {
            DB::table('totem_waitry_gastronomia')
                ->where('id', 2)
                ->update([
                    'ubicacion_id' => $salonId,
                    'waitry_layout_id' => null,
                    'waitry_table_id' => self::TABLE_K1,
                    'waitry_table_ids_adicionales' => (string) self::TABLE_K2,
                    'detalle' => 'Totem 2 ubicado en Pizzeria',
                ]);
        }

        DB::table('ubicaciones_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->whereIn('nombre', ['Kiosco 1', 'Kiosco 2'])
            ->delete();
    }

    private function resolverUbicacionId(string $nombre, $now): int
    {
        $existente = DB::table('ubicaciones_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('nombre', $nombre)
            ->value('id');

        if ($existente !== null) {
            return (int) $existente;
        }

        return (int) DB::table('ubicaciones_gastronomia')->insertGetId([
            'empresa_id' => self::EMPRESA_BIYEMAS,
            'nombre' => $nombre,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
