<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biyemas: un solo tótem físico en Pizzería/Salón (K1+K2); elimina registro duplicado Kiosco 2.
 */
return new class extends Migration
{
    private const EMPRESA_BIYEMAS = 1;

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

        $salonId = DB::table('ubicaciones_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('nombre', 'Salon')
            ->value('id');

        if ($salonId === null) {
            $salonId = DB::table('ubicaciones_gastronomia')
                ->where('empresa_id', self::EMPRESA_BIYEMAS)
                ->where('nombre', 'Salon')
                ->value('id');
        }

        // Eliminar tótem extra (Kiosco 2 como equipo separado).
        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('waitry_layout_id', 32393)
            ->where('waitry_table_id', self::TABLE_K2)
            ->delete();

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 5)
            ->delete();

        $update = [
            'waitry_layout_id' => null,
            'waitry_table_id' => self::TABLE_K1,
            'waitry_table_ids_adicionales' => (string) self::TABLE_K2,
            'detalle' => 'Tótem Pizzería/Salón (Kiosco 1 K1 + Kiosco 2 K2 en Waitry)',
            'updated_at' => now(),
        ];

        if ($salonId !== null) {
            $update['ubicacion_id'] = (int) $salonId;
        }

        DB::table('totem_waitry_gastronomia')
            ->where('empresa_id', self::EMPRESA_BIYEMAS)
            ->where('id', 2)
            ->update($update);

        // Ubicaciones auxiliares creadas solo para el split (sin tótem asociado).
        if (Schema::hasTable('ubicaciones_gastronomia')) {
            $kioscoUbicIds = DB::table('ubicaciones_gastronomia')
                ->where('empresa_id', self::EMPRESA_BIYEMAS)
                ->whereIn('nombre', ['Kiosco 1', 'Kiosco 2'])
                ->pluck('id');

            foreach ($kioscoUbicIds as $ubicId) {
                $enUso = DB::table('totem_waitry_gastronomia')
                    ->where('ubicacion_id', $ubicId)
                    ->exists();
                if (! $enUso) {
                    DB::table('ubicaciones_gastronomia')->where('id', $ubicId)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // No revertir: el estado previo (3 tótems) no refleja la realidad física.
    }
};
