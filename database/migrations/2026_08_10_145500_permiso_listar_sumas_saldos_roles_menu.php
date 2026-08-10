<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea permiso listar-sumas-saldos con los roles que ya ven el menú
 * contable/sumas-saldos (Op-contaduria y afines tenían menú sin permiso).
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'listar-sumas-saldos';

    private const MENU_URL = 'contable/sumas-saldos';

    /** @var list<string> */
    private const ROLES_EXTRA = [
        'Op-contaduria',
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
        'opcont-capitalhumano',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        foreach ($this->resolverRolIds() as $rolId) {
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
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $rolIds = $this->resolverRolIdsExtra();
        if ($rolIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Roles que ya tenían el menú sin permiso (+ lista explícita por si el menú cambia).
     *
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = $this->resolverRolIdsExtra();

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id') as $rolId) {
                $ids[] = (int) $rolId;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @return list<int> */
    private function resolverRolIdsExtra(): array
    {
        $ids = [];
        foreach (self::ROLES_EXTRA as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
