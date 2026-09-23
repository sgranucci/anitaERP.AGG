<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: menú + permisos Importar pedidos Anita (PED/PEX).
 * Roles: administrador, Facturacion, Ventas (si existen).
 *
 * Gate obligatorio: no-op en AGG / EL BIERZO / Ferli / etc.
 * El Bierzo tiene su propia migración `*_elbierzo` con la misma URL/slugs.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/importar-pedido-anita';

    private const MENU_NOMBRE = 'Importar pedidos Anita';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Facturacion', 'Ventas'];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar importar pedidos Anita', 'slug' => 'listar-importar-pedido-anita'],
        ['nombre' => 'Ejecutar importar pedidos Anita', 'slug' => 'ejecutar-importar-pedido-anita'],
    ];

    public function up(): void
    {
        // Solo EMPRESA=INTERFORMING (regla migraciones-entorno-empresa-menu-roles).
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $parentId = $this->resolverMenuVentasId();
        if ($parentId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($parentId, $orden);

        $rolIds = [];
        foreach (self::ROLES as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId > 0) {
                $rolIds[] = $rolId;
            }
        }

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($parentId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // Solo EMPRESA=INTERFORMING — no borrar menú/permisos en otros clientes.
        if (! EntornoEmpresaSupport::esInterforming()) {
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

    private function resolverMenuVentasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Ventas%')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $pedidoMenu = DB::table('menu')->where('url', 'ventas/pedido')->first();
        if ($pedidoMenu && (int) ($pedidoMenu->menu_id ?? 0) > 0) {
            return (int) $pedidoMenu->menu_id;
        }

        return 0;
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

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $now = now();

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => $now,
        ]);

        return $permisoId;
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            return;
        }
        DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
    }
};
