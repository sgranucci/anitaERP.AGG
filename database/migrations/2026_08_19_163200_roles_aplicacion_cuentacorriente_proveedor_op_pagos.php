<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/aplicacion-cuentacorriente';

    private const MENU_NOMBRE = 'Aplicar cuenta corriente';

    private const PADRE_URL_HINT = 'compras/pagoproveedor';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Op-Pagos',
        'Enc-pagos',
    ];

    /** @var list<string> */
    private const USUARIOS = [
        'rbarrera',
        'mmatta',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Aplicar cuenta corriente de proveedor', 'slug' => 'aplicar-cuentacorriente-proveedor'],
        ['nombre' => 'Desaplicar cuenta corriente de proveedor', 'slug' => 'desaplicar-cuentacorriente-proveedor'],
    ];

    public function up(): void
    {
        $padreId = $this->resolverPadreId();
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($padreId, $orden);
        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        $comprasId = (int) (DB::table('menu')->where('nombre', 'Módulo de Compras')->where('menu_id', 0)->value('id') ?? 0);

        foreach ($rolIds as $rolId) {
            foreach (array_filter([$comprasId, $padreId, $menuId]) as $mid) {
                $this->vincularMenuRol($mid, $rolId);
            }
            foreach ($permisoIds as $permisoId) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }

    public function down(): void
    {
        $rolIds = $this->resolverRolIds();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);

        foreach (array_column(self::PERMISOS, 'slug') as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                if ($rolId === $adminId) {
                    continue;
                }
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
            }
        }

        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                if ($rolId === $adminId) {
                    continue;
                }
                DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverPadreId(): int
    {
        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL_HINT)->value('menu_id') ?? 0);
        if ($padreId > 0) {
            return $padreId;
        }

        return (int) (DB::table('menu')->where('nombre', 'Módulo de Compras')->where('menu_id', 0)->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $usuarioIds = DB::table('usuario')->whereIn('usuario', self::USUARIOS)->pluck('id');
        if ($usuarioIds->isNotEmpty()) {
            foreach (DB::table('usuario_rol')->whereIn('usuario_id', $usuarioIds)->pluck('rol_id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => 'fa-compress-alt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
