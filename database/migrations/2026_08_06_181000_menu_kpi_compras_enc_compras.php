<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ítem de menú lateral «KPIs de Compras» (antes solo había botón en el listado).
 * Asigna menu_rol + permiso a Enc-compras y administrador.
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'compras/kpi',
        'nombre' => 'KPIs de Compras',
        'icono' => 'fa-chart-line',
        'orden' => 5,
    ];

    private const PERMISO_SLUG = 'listar-kpi-compras';

    /** @var list<string> */
    private const ROLES = [
        'Enc-compras',
        'administrador',
    ];

    public function up(): void
    {
        $comprasMenuId = $this->resolverMenuComprasId();
        if ($comprasMenuId <= 0) {
            return;
        }

        $menuId = $this->upsertMenu($comprasMenuId);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Tablero KPIs de Compras',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if ($permisoId > 0
                && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()
            ) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        // También el padre Módulo de Compras, por si el rol no lo tenía anclado
        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $comprasMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $comprasMenuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            $ocMenuId = (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('id') ?? 0);
            if ($ocMenuId > 0) {
                DB::table('permiso')->where('slug', self::PERMISO_SLUG)->update([
                    'menu_id' => $ocMenuId,
                    'updated_at' => now(),
                ]);
            }
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache();
    }

    private function resolverMenuComprasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Compras%')
                    ->orWhere('nombre', 'Módulo de Compras');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('menu_id') ?? 0);
    }

    private function upsertMenu(int $comprasMenuId): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        $orden = self::MENU['orden'];
        // Colocar justo después de Órdenes de Compra si existe
        $ordenOc = (int) (DB::table('menu')
            ->where('menu_id', $comprasMenuId)
            ->where('url', 'compras/ordencompra')
            ->value('orden') ?? 0);
        if ($ordenOc > 0) {
            $orden = $ordenOc; // mismo bloque visual; el nombre lo distingue
            // Mejor: ordenOc + 0.5 no existe; empujar los siguientes
            $orden = $ordenOc + 1;
            DB::table('menu')
                ->where('menu_id', $comprasMenuId)
                ->where('orden', '>=', $orden)
                ->where('url', '!=', self::MENU['url'])
                ->increment('orden');
        }

        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $comprasMenuId,
                'nombre' => self::MENU['nombre'],
                'url' => self::MENU['url'],
                'orden' => $orden,
                'icono' => self::MENU['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $comprasMenuId,
            'nombre' => self::MENU['nombre'],
            'orden' => $orden,
            'icono' => self::MENU['icono'],
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function forgetPermisoRolCache(): void
    {
        foreach ($this->resolverRolIds() as $rolId) {
            try {
                cache()->tags('Permiso')->forget("Permiso.rolid.$rolId");
            } catch (\Throwable) {
            }
        }
    }
};
