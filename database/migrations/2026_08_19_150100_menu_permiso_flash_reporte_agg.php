<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/flash/reporte';

    private const MENU_NOMBRE = 'Flash Report AGG';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar Flash Report AGG', 'slug' => 'listar-flash-reporte-agg'],
        ['nombre' => 'Exportar Flash Report AGG', 'slug' => 'exportar-flash-reporte-agg'],
        ['nombre' => 'Administrar envíos Flash Report AGG', 'slug' => 'administrar-flash-reporte-agg'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        $padreFlashId = $this->resolverMenuPadreFlashId();
        if ($padreFlashId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreFlashId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $padreFlashId, $orden, 'fa-file-excel-o');

        $permisoIds = [];
        foreach (self::PERMISOS as $permiso) {
            $permisoIds[] = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        $cajaId = $this->resolverMenuCajaId();
        foreach ($rolIds as $rolId) {
            foreach (array_filter([$cajaId, $padreFlashId, $menuId]) as $mid) {
                $this->vincularMenuRol($mid, $rolId);
            }
            foreach ($permisoIds as $permisoId) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (array_column(self::PERMISOS, 'slug') as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
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

    private function resolverMenuPadreFlashId(): int
    {
        $hijo = DB::table('menu')->where('url', 'caja/flash')->first();
        if ($hijo && (int) ($hijo->menu_id ?? 0) > 0) {
            return (int) $hijo->menu_id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'Flash')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function resolverMenuCajaId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * Roles fijos de tesorería/flash + los que ya tiene mbarrios (y homónimos).
     *
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (['Enc-contadur%', 'Ger-contadur%', 'Sup-contadur%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        $mbarriosId = (int) (DB::table('usuario')->where('usuario', 'mbarrios')->value('id') ?? 0);
        if ($mbarriosId > 0) {
            foreach (DB::table('usuario_rol')->where('usuario_id', $mbarriosId)->pluck('rol_id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
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
