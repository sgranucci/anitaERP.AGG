<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cajeros tesorería: ver comprobante de cierre tótem en rendiciones gastronomía caja.
 */
return new class extends Migration
{
    /** Perfiles cajeros / tesorería operativa. */
    private const ROLES_CAJERO_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
    ];

    private const SLUG_COMPROBANTE_TOTEM = 'ver-comprobante-cierre-totem-gastronomia-caja';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_COMPROBANTE_TOTEM)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_CAJERO_TESORERIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_COMPROBANTE_TOTEM)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_CAJERO_TESORERIA)
            ->pluck('id')
            ->all();

        if ($rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
