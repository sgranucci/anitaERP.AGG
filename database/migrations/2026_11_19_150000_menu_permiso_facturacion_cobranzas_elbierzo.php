<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Facturación y cobranzas (Anita l-factycobr) — solo El Bierzo.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/facturacion-cobranzas';

    private const PERMISO_SLUG = 'listar-facturacion-cobranzas';

    private const PERMISO_NOMBRE = 'Listar reporte facturación y cobranzas';

    /** Roles base + todos los que usan facturación (menú ventas/factura). */
    private const ROLES = [
        'administrador',
        'Despacho',
        'Enc-admin',
        'Enc-contaduría',
        'Facturacion',
        'Op-administracion',
        'Op-contaduria',
        'Ger-administracion',
        'Enc-impuestos',
    ];

    /** @var list<string> */
    private const MENU_REF_URLS = [
        'ventas/factura',
        'ventas/iva-ventas',
        'ventas/ventas-por-concepto',
        'ventas/reppedido',
        'ventas/repkilopedido',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $padreId = $this->resolverMenuReportesVentasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Facturación y cobranzas', $padreId, $orden, 'fa-file-invoice-dollar');

        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->resolverRolIds();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        $this->propagarMenuPadres($padreId, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

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

    private function resolverMenuReportesVentasId(): int
    {
        $ventasId = (int) (DB::table('menu')
            ->whereIn('nombre', ['Módulo de Ventas', 'Módulo Ventas'])
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($ventasId > 0) {
            $reportesId = (int) (DB::table('menu')
                ->where('menu_id', $ventasId)
                ->whereIn('nombre', ['Reportes de ventas', 'Reportes'])
                ->where('url', '#')
                ->orderByRaw("CASE WHEN nombre = 'Reportes de ventas' THEN 0 ELSE 1 END")
                ->value('id') ?? 0);
            if ($reportesId > 0) {
                return $reportesId;
            }
        }

        foreach (['ventas/iva-ventas', 'ventas/ventas-por-concepto', 'ventas/repkilopedido'] as $url) {
            $padre = (int) (DB::table('menu')->where('url', $url)->value('menu_id') ?? 0);
            if ($padre > 0) {
                return $padre;
            }
        }

        return $ventasId;
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

        foreach (self::MENU_REF_URLS as $url) {
            $refMenuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($refMenuId <= 0) {
                continue;
            }
            foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id') as $rolId) {
                $ids[] = (int) $rolId;
            }
        }

        // Roles con permiso listar/crear factura
        $permisoFacturaIds = DB::table('permiso')
            ->whereIn('slug', ['listar-factura', 'crear-factura', 'editar-factura'])
            ->pluck('id');
        if ($permisoFacturaIds->isNotEmpty()) {
            foreach (DB::table('permiso_rol')->whereIn('permiso_id', $permisoFacturaIds)->pluck('rol_id') as $rolId) {
                $ids[] = (int) $rolId;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function propagarMenuPadres(int $menuId, array $rolIds): void
    {
        $actual = $menuId;
        $guard = 0;
        while ($actual > 0 && $guard < 8) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $padre)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padre, 'rol_id' => $rolId]);
                }
            }
            $actual = $padre;
            $guard++;
        }
    }
};
