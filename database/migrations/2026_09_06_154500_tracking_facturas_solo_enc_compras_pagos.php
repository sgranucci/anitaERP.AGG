<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tracking de facturas: solo Enc-compras y Enc-pagos (menú + permisos).
 */
return new class extends Migration
{
    private const URL = 'compras/tracking-facturas';

    /** @var list<string> */
    private const ROLES = ['Enc-compras', 'Enc-pagos'];

    /** @var list<string> */
    private const PERMISOS = [
        'listar-tracking-facturas',
        'ver-pdf-tracking-facturas',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($rolIds === []) {
            return;
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->whereNotIn('rol_id', $rolIds)->delete();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISOS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->whereNotIn('rol_id', $rolIds)->delete();
            foreach ($permisoIds as $permisoId) {
                foreach ($rolIds as $rolId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => (int) $permisoId,
                            'rol_id' => $rolId,
                        ]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No restaura roles amplios (administrador / Op-*). Queda el estado post-up.
    }
};
