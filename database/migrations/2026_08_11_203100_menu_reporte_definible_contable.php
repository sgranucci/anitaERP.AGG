<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/reporte-definible';

    private const MENU_PADRE_NOMBRE = 'Reportes Contables';

    private const MENU_NOMBRE = 'Reportes definibles';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        ['slug' => 'listar-reporte-definible', 'nombre' => 'Listar reportes contables definibles'],
        ['slug' => 'crear-reporte-definible', 'nombre' => 'Crear reporte contable definible'],
        ['slug' => 'editar-reporte-definible', 'nombre' => 'Editar reporte contable definible'],
        ['slug' => 'actualizar-reporte-definible', 'nombre' => 'Actualizar reporte contable definible'],
        ['slug' => 'eliminar-reporte-definible', 'nombre' => 'Eliminar reporte contable definible'],
        ['slug' => 'ejecutar-reporte-definible', 'nombre' => 'Ejecutar reporte contable definible'],
        ['slug' => 'importar-reporte-definible', 'nombre' => 'Importar reportes definibles desde Anita'],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría'];

    public function up(): void
    {
        $permisoIds = [];
        foreach (self::PERMISOS as $p) {
            $permisoIds[] = $this->upsertPermiso($p['slug'], $p['nombre']);
        }
        foreach ($permisoIds as $permisoId) {
            $this->asignarPermisoRoles($permisoId);
        }

        $padreId = $this->resolverMenuReportesContableId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $padreId, $orden, 'fa-sitemap');

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $slug, string $nombre): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => $nombre,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asignarPermisoRoles(int $permisoId): void
    {
        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @return list<int>
     */
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

    private function resolverMenuReportesContableId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'Módulo Contable')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 43);
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

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        foreach (self::PERMISOS as $p) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $p['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
