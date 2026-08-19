<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú/permisos:
 * - Caja: reporte pérdidas empleados (l-perdempl.c)
 * - Sueldos: proceso dto. fallos (p-dtofallo.c) + cta. cte. (l-fallo.c)
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_CAJA = [
        'administrador',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
    ];

    public function up(): void
    {
        $this->altaReportePerdida();
        $this->altaDtoFallo();
        $this->altaFalloReporte();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $this->bajarMenu('caja/perdida-personal-reporte', [
            'listar-perdida-personal-reporte',
        ]);
        $this->bajarMenu('sueldos/dtofallo', [
            'listar-dtofallo-sueldos',
            'crear-dtofallo-sueldos',
            'borrar-dtofallo-sueldos',
        ]);
        $this->bajarMenu('sueldos/fallo-reporte', [
            'listar-fallo-reporte-sueldos',
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function altaReportePerdida(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', 'Pérdidas de personal')
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(
            'caja/perdida-personal-reporte',
            'Reporte pérdidas empleados',
            $padreId,
            $orden,
            'fa-file-alt'
        );
        $permisoId = $this->upsertPermiso(
            'Listar reporte pérdidas de empleados',
            'listar-perdida-personal-reporte',
            $menuId
        );

        foreach ($this->resolverRolIds(self::ROLES_CAJA) as $rolId) {
            $this->asegurarMenuRol($padreId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            $this->asegurarPermisoRol($permisoId, $rolId);
        }
    }

    private function altaDtoFallo(): void
    {
        $moduloId = $this->resolverModuloSueldosId();
        $submenuId = $this->upsertSubmenu($moduloId, 'Liquidación', 'fa-calculator');
        $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(
            'sueldos/dtofallo',
            'Descuentos por fallos',
            $submenuId,
            $orden,
            'fa-balance-scale'
        );

        $permisos = [
            ['nombre' => 'Listar descuentos por fallos sueldos', 'slug' => 'listar-dtofallo-sueldos'],
            ['nombre' => 'Generar descuentos por fallos sueldos', 'slug' => 'crear-dtofallo-sueldos'],
            ['nombre' => 'Anular descuentos por fallos sueldos', 'slug' => 'borrar-dtofallo-sueldos'],
        ];
        $permisoIds = [];
        foreach ($permisos as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        foreach ($this->resolverRolIdsCapitalHumano() as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            foreach ($permisoIds as $permisoId) {
                $this->asegurarPermisoRol($permisoId, $rolId);
            }
        }
    }

    private function altaFalloReporte(): void
    {
        $moduloId = $this->resolverModuloSueldosId();
        $submenuId = $this->upsertSubmenu($moduloId, 'Reportes de Sueldos', 'fa-chart-bar');
        $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(
            'sueldos/fallo-reporte',
            'Cta. cte. fallos',
            $submenuId,
            $orden,
            'fa-file-invoice-dollar'
        );
        $permisoId = $this->upsertPermiso(
            'Listar cta. cte. fallos sueldos',
            'listar-fallo-reporte-sueldos',
            $menuId
        );

        foreach ($this->resolverRolIdsCapitalHumano() as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            $this->asegurarPermisoRol($permisoId, $rolId);
        }
    }

    private function resolverModuloSueldosId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Sueldos y Jornales')
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'nombre' => 'Módulo Sueldos y Jornales',
            'url' => '#',
            'menu_id' => 0,
            'orden' => $orden,
            'icono' => 'fa-money',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertSubmenu(int $moduloId, string $nombre, string $icono): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('menu_id', $moduloId)
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'nombre' => $nombre,
            'url' => '#',
            'menu_id' => $moduloId,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden, ?string $icono = null): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
    {
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @param  list<string>  $slugs
     */
    private function bajarMenu(string $url, array $slugs): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function resolverRolIdsCapitalHumano(): array
    {
        return DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
