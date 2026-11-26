<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permiso del «Reporte de ahorros en compras», junto al historial de precios (Compras → Reportes).
 * Menú genérico del ERP (no es de un cliente): solo roles de Compras que ya existan.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/ahorro-reporte';

    private const MENU_NOMBRE = 'Reporte de ahorros';

    /** URL de un ítem hermano que ya vive en el grupo de reportes de Compras. */
    private const MENU_HERMANO_URL = 'compras/historial-precios-articulo';

    /** @var array<string, string> slug => nombre */
    private const PERMISOS = [
        'listar-reporte-ahorro-compras' => 'Listar reporte de ahorros en compras',
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-compras'];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::MENU_HERMANO_URL)->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($padreId, $orden);
        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = $this->upsertPermiso($slug, $nombre, $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($menuId, $rolId);
            $this->propagarMenuPadres($menuId, $rolId);
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id')->all();
        $rolIds = $this->resolverRolIds();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'orden' => $orden,
            'icono' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'ayuda' => null,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function propagarMenuPadres(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        for ($guard = 0; $guard < 8; $guard++) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $this->asegurarMenuRol($padre, $rolId);
            $actual = $padre;
        }
    }
};
