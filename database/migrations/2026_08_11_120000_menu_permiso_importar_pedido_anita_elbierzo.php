<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos: Importar pedidos Anita.
 * Solo EL BIERZO. Bajo menú Administrador; rol administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/importar-pedido-anita';

    private const MENU_NOMBRE = 'Importar pedidos Anita';

    private const ROL_ADMINISTRADOR = 'administrador';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar importar pedidos Anita', 'slug' => 'listar-importar-pedido-anita'],
        ['nombre' => 'Ejecutar importar pedidos Anita', 'slug' => 'ejecutar-importar-pedido-anita'],
    ];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $parentId = $this->resolverMenuAdministradorId();
        if ($parentId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($parentId, $orden);
        $this->asignarRolAdministrador($menuId, $parentId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    private function resolverMenuAdministradorId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Administrador')
            ->where('menu_id', 0)
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('id', 8)->value('id') ?? 0);
    }

    private function upsertMenu(int $parentId, int $orden): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $now = now();

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $parentId,
                'icono' => 'fa-download',
                'updated_at' => $now,
            ]);

            return $menuId;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'menu_id' => $parentId,
            'orden' => $orden,
            'icono' => 'fa-download',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function asignarRolAdministrador(int $menuId, int $parentMenuId): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMINISTRADOR)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        if ($parentMenuId > 0
            && ! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rolId]);
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            $now = now();

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $permiso['slug'],
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $permiso['nombre'],
                    'updated_at' => $now,
                ]);
            }

            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
