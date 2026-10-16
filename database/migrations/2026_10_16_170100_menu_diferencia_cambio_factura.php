<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/diferencia-cambio-factura';

    private const MENU_NOMBRE = 'Diferencia de cambio en facturas';

    private const MENU_PADRE_NOMBRE = 'Reportes Contables';

    private const MENU_MODULO_NOMBRE = 'Módulo Contable';

    private const PERMISO_SLUG = 'listar-diferencia-cambio-factura';

    private const PERMISO_NOMBRE = 'Listar diferencia de cambio en facturas de proveedor';

    public function up(): void
    {
        $padreId = $this->resolverMenuPorNombre(self::MENU_PADRE_NOMBRE);
        if ($padreId === 0) {
            $padreId = $this->resolverMenuPorNombre(self::MENU_MODULO_NOMBRE);
        }
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($padreId, $orden);
        $permisoId = $this->upsertPermiso($menuId);
        $moduloId = $this->resolverMenuPorNombre(self::MENU_MODULO_NOMBRE);
        $rolIds = $this->resolverRolIdsContaduria();

        foreach ($rolIds as $rolId) {
            $this->vincularMenuRol($menuId, $rolId);
            if ($padreId > 0) {
                $this->vincularMenuRol($padreId, $rolId);
            }
            if ($moduloId > 0) {
                $this->vincularMenuRol($moduloId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $payload = [
            'menu_id' => $padreId,
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'orden' => $orden,
            'icono' => 'fa-exchange-alt',
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
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

    private function resolverMenuPorNombre(string $nombre): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsContaduria(): array
    {
        $ids = [];
        foreach (['administrador'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        foreach (['Enc-contadur%', 'Op-contadur%', 'Sup-contadur%', 'Ger-contadur%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }
};
