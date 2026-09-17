<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Reportes bajo Caja + informes Movimientos de caja (l-movim) y Cierre de caja (l-ciecaja).
 */
return new class extends Migration
{
    private const MENU_MOVIMIENTOS = 'caja/movimientos-caja-reporte';

    private const MENU_CIERRE = 'caja/cierre-caja-reporte';

    private const PERMISO_MOVIMIENTOS = 'listar-movimientos-caja-reporte';

    private const PERMISO_CIERRE = 'listar-cierre-caja-reporte';

    public function up(): void
    {
        $cajaMenuId = $this->resolverMenuCajaId();
        if ($cajaMenuId <= 0) {
            return;
        }

        $reportesPadreId = $this->resolverMenuReportesId($cajaMenuId);
        if ($reportesPadreId <= 0) {
            $ordenPadre = (int) (DB::table('menu')->where('menu_id', $cajaMenuId)->max('orden') ?? 0) + 1;
            $reportesPadreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $cajaMenuId,
                'nombre' => 'Reportes',
                'url' => '#',
                'orden' => min($ordenPadre, 10),
                'icono' => 'fa-print',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $reportesPadreId)->max('orden') ?? 0);

        $menuMovId = $this->upsertMenu(
            self::MENU_MOVIMIENTOS,
            'Movimientos de caja',
            $reportesPadreId,
            $ordenBase + 1,
            'fa-list-alt'
        );
        $permMovId = $this->upsertPermiso(
            'Listar reporte movimientos de caja',
            self::PERMISO_MOVIMIENTOS,
            $menuMovId
        );

        $menuCieId = $this->upsertMenu(
            self::MENU_CIERRE,
            'Cierre de caja',
            $reportesPadreId,
            $ordenBase + 2,
            'fa-lock'
        );
        $permCieId = $this->upsertPermiso(
            'Listar reporte cierre de caja',
            self::PERMISO_CIERRE,
            $menuCieId
        );

        $rolIds = $this->resolverRolIds($cajaMenuId);
        $this->asignarRolesMenu($cajaMenuId, $rolIds);
        $this->asignarRolesMenu($reportesPadreId, $rolIds);
        $this->asignarRoles($menuMovId, [$permMovId], $rolIds);
        $this->asignarRoles($menuCieId, [$permCieId], $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach ([self::PERMISO_MOVIMIENTOS, self::PERMISO_CIERRE] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        foreach ([self::MENU_MOVIMIENTOS, self::MENU_CIERRE] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuCajaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Caja')
                    ->orWhere('nombre', 'like', '%Módulo de Caja%')
                    ->orWhere('nombre', 'like', '%Modulo de Caja%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'caja/cuentacaja')->value('menu_id') ?? 0);
    }

    private function resolverMenuReportesId(int $cajaMenuId): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', $cajaMenuId)
            ->where('url', '#')
            ->where('nombre', 'Reportes')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(int $cajaMenuId): array
    {
        $ids = DB::table('menu_rol')
            ->where('menu_id', $cajaMenuId)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $ieMenuId = (int) (DB::table('menu')->where('url', 'caja/ingresoegreso')->value('id') ?? 0);
        if ($ieMenuId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $ieMenuId)->pluck('rol_id') as $id) {
                $ids[] = (int) $id;
            }
        }

        foreach (['administrador', 'Enc-admin', 'Enc-contaduría', 'Enc-contaduria'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
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
        $payload = ['nombre' => $nombre, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $exists = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $permisoIds
     * @param  list<int>  $rolIds
     */
    private function asignarRoles(int $menuId, array $permisoIds, array $rolIds): void
    {
        $this->asignarRolesMenu($menuId, $rolIds);

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
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
};
