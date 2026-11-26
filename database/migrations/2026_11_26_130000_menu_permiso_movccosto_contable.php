<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Totales por centro de costo (Anita l-movccosto) en Reportes Contables,
 * con permiso para todo el sector de contaduría.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/movimientos-centro-costo';

    private const MENU_NOMBRE = 'Totales por centro de costo';

    private const MENU_PADRE_NOMBRE = 'Reportes Contables';

    private const PERMISO_SLUG = 'listar-movccosto';

    private const PERMISO_NOMBRE = 'Listar totales por centro de costo';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Op-contaduria', 'Sup-contaduria'];

    public function up(): void
    {
        $permisoId = $this->upsertPermiso();
        $rolIds = $this->resolverRolIds();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        $padreId = $this->resolverMenuReportesContableId();
        if ($padreId === 0) {
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($padreId, $orden);

        foreach ($rolIds as $rolId) {
            foreach ([$menuId, $padreId] as $idMenu) {
                if (! DB::table('menu_rol')->where('menu_id', $idMenu)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $idMenu, 'rol_id' => $rolId]);
                }
            }
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

    private function upsertPermiso(): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => self::PERMISO_NOMBRE,
            'slug' => self::PERMISO_SLUG,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            ->value('id') ?? 0);
    }

    private function upsertMenu(int $padre, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-sitemap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => self::MENU_NOMBRE,
            'orden' => $orden,
            'icono' => 'fa-sitemap',
            'updated_at' => now(),
        ]);

        return $id;
    }
};
