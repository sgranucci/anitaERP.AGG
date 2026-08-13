<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El permiso listar-sumas-saldos se creó sin menu_id. El ABM de menú/rol
 * no lo muestra y Op-contaduria ve el ítem pero can() puede denegar por caché.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/sumas-saldos';

    private const PERMISO_SLUG = 'listar-sumas-saldos';

    private const PERMISO_NOMBRE = 'Listar balance de sumas y saldos';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->resolverRolIds($menuId);

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $payload = [
            'nombre' => self::PERMISO_NOMBRE,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => self::PERMISO_SLUG,
            'created_at' => now(),
        ]));
    }

    /**
     * Roles que ya ven el menú + Op/Enc contaduría por si el menú no está asignado.
     *
     * @return list<int>
     */
    private function resolverRolIds(int $menuId): array
    {
        $ids = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all();

        foreach (['administrador'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (DB::table('rol')->where('nombre', 'like', 'Op-contadur%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', 'Enc-contadur%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
            } catch (\Throwable) {
            }
        }
    }
};
