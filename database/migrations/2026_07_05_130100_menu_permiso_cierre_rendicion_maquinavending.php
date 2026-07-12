<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const MENU_URL = 'contable/cierre-rendiciones-maquinavending';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_TODOS_CONTADURIA = [
        ['nombre' => 'Listar cierre rendiciones vending', 'slug' => 'listar-cierre-rendicion-maquinavending-contable'],
        ['nombre' => 'Ejecutar cierre contable rendición vending', 'slug' => 'ejecutar-cierre-rendicion-maquinavending-contable'],
        ['nombre' => 'Exportar cierre rendiciones vending', 'slug' => 'exportar-cierre-rendicion-maquinavending-contable'],
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_SOLO_ENCARGADO = [
        ['nombre' => 'Anular cierre contable rendición vending', 'slug' => 'anular-cierre-rendicion-maquinavending-contable'],
    ];

    /** @var list<string> */
    private const ROLES_ENCARGADO = ['administrador', 'Enc-contaduría'];

    public function up(): void
    {
        $padreId = $this->resolverMenuContableId();
        if ($padreId === 0) {
            return;
        }

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);
        $menuId = $this->upsertMenu(
            self::MENU_URL,
            'Cierre rendiciones vending',
            $padreId,
            $ordenBase + 1,
            'fa-lock',
        );

        $rolesContaduria = $this->resolverTodosRolesContaduria();
        foreach (array_unique([$menuId, $padreId]) as $mid) {
            foreach ($rolesContaduria as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
        }

        foreach (self::PERMISOS_TODOS_CONTADURIA as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolesContaduria as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        $rolesEncargado = $this->resolverRolIdsPorNombre(self::ROLES_ENCARGADO);
        foreach (self::PERMISOS_SOLO_ENCARGADO as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolesEncargado as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_merge(
            array_column(self::PERMISOS_TODOS_CONTADURIA, 'slug'),
            array_column(self::PERMISOS_SOLO_ENCARGADO, 'slug'),
        );

        foreach ($slugs as $slug) {
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

    /** @return list<int> */
    private function resolverTodosRolesContaduria(): array
    {
        return DB::table('rol')
            ->where(function ($q) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
                    ->orWhere('nombre', 'Enc-impuestos')
                    ->orWhere('nombre', 'Enc-admin')
                    ->orWhere('nombre', 'Ger-administracion')
                    ->orWhere('nombre', 'administrador');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIdsPorNombre(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolverMenuContableId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 43);
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
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
};
