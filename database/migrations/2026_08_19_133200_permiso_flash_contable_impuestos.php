<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El flash unificado también lo usa Impuestos; suma Enc/Op-impuestos al menú y permiso.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/flash-contable';

    private const MENU_NOMBRE = 'Flash contable';

    private const MENU_PADRE_NOMBRE = 'Reportes Contables';

    private const MENU_MODULO_NOMBRE = 'Módulo Contable';

    private const PERMISO_SLUG = 'listar-flash-contable';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($menuId <= 0 || $permisoId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'nombre' => self::MENU_NOMBRE,
            'updated_at' => now(),
        ]);

        $reportesId = $this->resolverMenuPorNombre(self::MENU_PADRE_NOMBRE);
        $moduloId = $this->resolverMenuPorNombre(self::MENU_MODULO_NOMBRE);
        $rolIds = $this->resolverRolIdsImpuestos();

        foreach ($rolIds as $rolId) {
            $this->vincularMenuRol($menuId, $rolId);
            if ($reportesId > 0) {
                $this->vincularMenuRol($reportesId, $rolId);
            }
            if ($moduloId > 0) {
                $this->vincularMenuRol($moduloId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolIds = $this->resolverRolIdsImpuestos();

        if ($menuId > 0 && $rolIds !== []) {
            DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
        }
        if ($permisoId > 0 && $rolIds !== []) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolIds)->delete();
        }

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Flash contabilidad',
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsImpuestos(): array
    {
        $ids = [];
        foreach (['Enc-impuest%', 'Op-impuest%', 'Sup-impuest%', 'Ger-impuest%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private function resolverMenuPorNombre(string $nombre): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
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
