<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos CRUD de cotización tesorería para roles de tesorería + administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/cotizacion-tesoreria';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar cotización tesorería', 'slug' => 'listar-cotizacion-tesoreria'],
        ['nombre' => 'Crear cotización tesorería', 'slug' => 'crear-cotizacion-tesoreria'],
        ['nombre' => 'Editar cotización tesorería', 'slug' => 'editar-cotizacion-tesoreria'],
        ['nombre' => 'Actualizar cotización tesorería', 'slug' => 'actualizar-cotizacion-tesoreria'],
        ['nombre' => 'Borrar cotización tesorería', 'slug' => 'borrar-cotizacion-tesoreria'],
    ];

    private const ROLES_TESORERIA = [
        'administrador',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    public function up(): void
    {
        $cajaId = $this->resolverMenuCajaId();
        $orden = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Cotización tesorería', $cajaId, $orden, 'fa-exchange');

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $this->upsertPermisos($menuId, $refMenuId);
        $this->asignarRolesTesoreria($menuId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (array_column(self::PERMISOS, 'slug') as $slug) {
            $pid = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($pid > 0) {
                DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                DB::table('permiso')->where('id', $pid)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuCajaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'like', '%Caja%')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 104);
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

    private function upsertPermisos(int $menuId, int $refMenuId): void
    {
        $rolIdsRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach (self::PERMISOS as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsRef as $rolId) {
                $exists = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $exists) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    private function asignarRolesTesoreria(int $menuId): void
    {
        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_column(self::PERMISOS, 'slug'))
            ->pluck('id')
            ->all();

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $pid) {
                if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $pid,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }
};
