<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/ventas-por-concepto';

    private const PERMISO_SLUG = 'listar-ventas-por-concepto';

    private const PERMISO_NOMBRE = 'Listar reporte ventas por concepto';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Ger-administracion',
        'Enc-contaduría',
        'Op-contaduria',
        'Enc-impuestos',
    ];

    /** @var list<string> */
    private const MENU_REF_URLS = [
        'ventas/reppedido',
        'ventas/repkilocategoria',
        'ventas/reparticulovendido',
        'ventas/iva-ventas',
        'ventas/concepto-venta',
    ];

    public function up(): void
    {
        $padreId = $this->resolverMenuReportesVentasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Ventas por concepto', $padreId, $orden, 'fa-chart-bar');

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
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->value('id') ?? 0);
            if ($reportesId > 0) {
                return $reportesId;
            }
        }

        foreach (['ventas/reppedido', 'ventas/repkilocategoria', 'ventas/reparticulovendido'] as $url) {
            $padre = (int) (DB::table('menu')->where('url', $url)->value('menu_id') ?? 0);
            if ($padre > 0) {
                return $padre;
            }
        }

        return $ventasId;
    }

    /** @return list<int> */
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

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
};
