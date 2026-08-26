<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usuarios Enc-compras / Op-Compras: sector de legajo COMPRAS.
 */
return new class extends Migration
{
    private const SECTOR = 'COMPRAS';

    private const ROLES = ['Enc-compras', 'Op-Compras'];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('usuario')
            || ! DB::getSchemaBuilder()->hasColumn('usuario', 'sector_legajocompra_id')
        ) {
            return;
        }

        $sectorId = (int) (DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [self::SECTOR])
            ->value('id') ?? 0);
        if ($sectorId <= 0) {
            return;
        }

        $usuarioIds = DB::table('usuario')
            ->join('usuario_rol', 'usuario_rol.usuario_id', '=', 'usuario.id')
            ->join('rol', 'rol.id', '=', 'usuario_rol.rol_id')
            ->whereIn('rol.nombre', self::ROLES)
            ->where(function ($q) use ($sectorId) {
                $q->whereNull('usuario.sector_legajocompra_id')
                    ->orWhere('usuario.sector_legajocompra_id', '!=', $sectorId);
            })
            ->pluck('usuario.id')
            ->unique()
            ->all();

        if ($usuarioIds === []) {
            return;
        }

        DB::table('usuario')
            ->whereIn('id', $usuarioIds)
            ->update([
                'sector_legajocompra_id' => $sectorId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Asignación operativa: no se revierte.
    }
};
