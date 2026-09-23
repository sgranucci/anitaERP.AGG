<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú IVA compras bajo Compras → Reportes + permiso listar-iva-compras
 * para administrador, Enc-contaduría, Enc-impuestos y Op-impuestos.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/iva-compras';

    private const MENU_NOMBRE = 'IVA compras';

    private const PERMISO_SLUG = 'listar-iva-compras';

    private const PERMISO_NOMBRE = 'Listar reporte IVA compras';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    public function up(): void
    {
        $padreId = $this->resolverMenuReportesComprasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $padreId, $orden, 'fa-file-invoice');

        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->resolverRolIds();
        $this->asignarPermisoRoles($permisoId, $rolIds);
        $this->asignarMenuRoles($menuId, $rolIds);
        $this->vincularCadenaPadres($menuId, $rolIds);

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

    private function resolverMenuReportesComprasId(): int
    {
        // Preferir "Reportes" hijo directo de "Módulo de Compras" (url #, nombre Reportes).
        $moduloComprasId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Compras')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($moduloComprasId > 0) {
            $reportesId = (int) (DB::table('menu')
                ->where('menu_id', $moduloComprasId)
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
            if ($reportesId > 0) {
                return $reportesId;
            }
        }

        // Fallback: menú 479 visto en AGG.
        return (int) (DB::table('menu')->where('id', 479)->value('id') ?? 0);
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => self::PERMISO_NOMBRE,
            'slug' => self::PERMISO_SLUG,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarMenuRoles(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * Asegura que Enc/Op-impuestos vean la cadena de padres (Reportes → Módulo Compras).
     *
     * @param  list<int>  $rolIds
     */
    private function vincularCadenaPadres(int $menuId, array $rolIds): void
    {
        $actual = $menuId;
        $vistos = [];
        while ($actual > 0 && ! isset($vistos[$actual])) {
            $vistos[$actual] = true;
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $this->asignarMenuRoles($padre, $rolIds);
            $actual = $padre;
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
};
