<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pagos del legajo: solo rbarrera y mmatta.
 */
return new class extends Migration
{
    private const SECTOR = 'PAGOS';

    private const USUARIOS = ['rbarrera', 'mmatta'];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasColumn('usuario', 'sector_legajocompra_id')) {
            return;
        }

        $sectorId = (int) (DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [self::SECTOR])
            ->value('id') ?? 0);
        if ($sectorId <= 0) {
            return;
        }

        $idsPagos = DB::table('usuario')
            ->whereIn('usuario', self::USUARIOS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($idsPagos !== []) {
            DB::table('usuario')->whereIn('id', $idsPagos)->update([
                'sector_legajocompra_id' => $sectorId,
                'updated_at' => now(),
            ]);
        }

        $q = DB::table('usuario')->where('sector_legajocompra_id', $sectorId);
        if ($idsPagos !== []) {
            $q->whereNotIn('id', $idsPagos);
        }
        $q->update([
            'sector_legajocompra_id' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Asignación operativa: no se revierte.
    }
};
