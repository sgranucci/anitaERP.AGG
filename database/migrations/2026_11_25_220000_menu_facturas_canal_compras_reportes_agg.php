<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: menú «Facturas por canal» en Compras → Reportes para compras y pagos.
 * No abre Gobernanza IA completa: permiso propio listar-facturas-canal.
 */
return new class extends Migration
{
    private const MENU_URL = 'configuracion/ai-decisiones/facturas-canal';

    private const MENU_NOMBRE = 'Facturas por canal';

    private const PERMISO_SLUG = 'listar-facturas-canal';

    private const PERMISO_NOMBRE = 'Listar reporte facturas por canal';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-compras',
        'Op-Compras',
        'Op-Compras-arbol',
        'Op-Compras-SP',
        'Enc-pagos',
        'Op-Pagos',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $padreId = $this->resolverMenuReportesComprasId();
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($padreId, $orden);
        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->resolverRolIds();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            $this->asegurarMenuRol($menuId, $rolId);
            $this->propagarMenuPadres($menuId, $rolId);
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolIds = $this->resolverRolIds();

        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
            // Solo borrar el ítem si no quedó asignado a otros roles.
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->exists()) {
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolIds)->delete();
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->exists()) {
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuReportesComprasId(): int
    {
        $comprasId = (int) (DB::table('menu')
            ->where('url', '#')
            ->whereIn('nombre', ['Módulo de Compras', 'Módulo Compras'])
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($comprasId > 0) {
            $reportesId = (int) (DB::table('menu')
                ->where('menu_id', $comprasId)
                ->where('url', '#')
                ->where('nombre', 'Reportes')
                ->orderBy('id')
                ->value('id') ?? 0);
            if ($reportesId > 0) {
                return $reportesId;
            }
        }

        $kpiPadre = (int) (DB::table('menu')->where('url', 'compras/kpi')->value('menu_id') ?? 0);

        return $kpiPadre > 0 ? $kpiPadre : 0;
    }

    private function upsertMenu(int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-file-invoice',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'orden' => $orden,
            'icono' => 'fa-file-invoice',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'ayuda' => 'Reporte de precargas por canal (agente/API, scan, IA) y errores.',
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
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
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
