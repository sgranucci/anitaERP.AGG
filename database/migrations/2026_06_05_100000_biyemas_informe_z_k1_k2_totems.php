<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biyemas Informe Z: 2 tótems Posnet (K1 + K2). Tomasso solo cierre operativo (sin Informe Z automático).
 */
return new class extends Migration
{
    private const EMPRESA_BIYEMAS = 1;

    private const LAYOUT_TOMASSO = 32211;

    private const LAYOUT_K1 = 32392;

    private const LAYOUT_K2 = 32393;

    private const TABLE_TOMASSO = 101066;

    private const TABLE_K1 = 103443;

    private const TABLE_K2 = 103444;

    public function up(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        if (! Schema::hasColumn('totem_waitry_gastronomia', 'informe_z_habilitado')) {
            Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
                $table->boolean('informe_z_habilitado')->default(true)->after('detalle');
            });
        }

        $now = now();
        $ubicK1 = $this->resolverUbicacionId('Kiosco 1', $now);
        $ubicK2 = $this->resolverUbicacionId('Kiosco 2', $now);

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 1)
            ->update([
                'waitry_layout_id' => self::LAYOUT_TOMASSO,
                'waitry_layout_ids_adicionales' => null,
                'waitry_table_id' => self::TABLE_TOMASSO,
                'waitry_table_ids_adicionales' => null,
                'informe_z_habilitado' => false,
                'detalle' => 'Tomasso — cierre operativo Waitry (Mostrador 32211). Informe Z Posnet: carga manual.',
                'updated_at' => $now,
            ]);

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 2)
            ->update([
                'ubicacion_id' => $ubicK1,
                'waitry_layout_id' => self::LAYOUT_K1,
                'waitry_layout_ids_adicionales' => null,
                'waitry_table_id' => self::TABLE_K1,
                'waitry_table_ids_adicionales' => null,
                'informe_z_habilitado' => true,
                'detalle' => 'Posnet Kiosco 1 (K1) — Informe Z',
                'updated_at' => $now,
            ]);

        $existeK2 = DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('waitry_layout_id', self::LAYOUT_K2)
            ->exists();

        if (! $existeK2) {
            DB::table('totem_waitry_gastronomia')->insert([
                'empresa_id' => self::EMPRESA_BIYEMAS,
                'ubicacion_id' => $ubicK2,
                'waitry_layout_id' => self::LAYOUT_K2,
                'waitry_layout_ids_adicionales' => null,
                'waitry_table_id' => self::TABLE_K2,
                'waitry_table_ids_adicionales' => null,
                'informe_z_habilitado' => true,
                'detalle' => 'Posnet Kiosco 2 (K2) — Informe Z',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('waitry_layout_id', self::LAYOUT_K2)
            ->where('id', '!=', 2)
            ->delete();

        if (Schema::hasColumn('totem_waitry_gastronomia', 'informe_z_habilitado')) {
            Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
                $table->dropColumn('informe_z_habilitado');
            });
        }
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
