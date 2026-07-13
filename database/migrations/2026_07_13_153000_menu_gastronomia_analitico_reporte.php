<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/reportes';

    /**
     * Supervisores, encargado y gerente de gastronomía + control de gestión.
     *
     * @var list<string>
     */
    private const ROLES = [
        'administrador',
        'Sup-Gastronomia',
        'Enc-gastronomía',
        'Ger-Gastronomia',
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuReportesGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/informe-gerente')->value('menu_id') ?? 236);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Reporte analítico',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-table',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Reporte analítico',
                'orden' => $orden,
                'icono' => 'fa-table',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rolId]);
            }
        }

        // También heredar roles del reporte de descuentos (por si hay extras).
        $refMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/descuento-reporte')->value('id') ?? 0);
        if ($refMenuId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique() as $rid) {
                $rid = (int) $rid;
                if ($rid <= 0) {
                    continue;
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
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

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuReportesGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'Reportes Gastronomía')
                    ->orWhere('nombre', 'like', 'Reportes Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'ventas/gastronomia/informe-gerente')->value('menu_id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);

            if ($id <= 0 && str_starts_with($nombre, 'Sup-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Enc-gastronom')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Ger-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ger-Gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_contains($nombre, 'Control de Gestión')) {
                $like = str_starts_with($nombre, 'Enc-')
                    ? 'Enc-Control de Gesti%'
                    : 'Op-Control de Gesti%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        // Todos los roles cuyo nombre contenga "Control de Gestión"
        foreach (DB::table('rol')->where('nombre', 'like', '%Control de Gesti%')->pluck('id') as $cgId) {
            $ids[] = (int) $cgId;
        }

        return array_values(array_unique($ids));
    }
};
