<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/venta-hora-reporte';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Ger-Gastronomia',
        'Enc-gastronomía',
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuReportesGastronomiaId();
        if ($parentMenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $datos = [
            'menu_id' => $parentMenuId,
            'nombre' => 'Venta hora por hora',
            'icono' => 'fa-clock-o',
            'updated_at' => now(),
        ];

        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId($datos + [
                'url' => self::MENU_URL,
                'orden' => (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1,
                'created_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update($datos);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            $this->asignarMenuRol($menuId, $rolId);
            $this->asignarMenuRol($parentMenuId, $rolId);
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
            ->where(function ($query) {
                $query->where('nombre', 'Reportes Gastronomía')
                    ->orWhere('nombre', 'like', 'Reportes Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('url', 'ventas/gastronomia/informe-gerente')
            ->value('menu_id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];

        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);

            if ($id <= 0 && str_starts_with($nombre, 'Ger-')) {
                $id = (int) (DB::table('rol')
                    ->where('nombre', 'like', 'Ger-gastronom%')
                    ->orderBy('id')
                    ->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Enc-')) {
                $id = (int) (DB::table('rol')
                    ->where('nombre', 'like', 'Enc-gastronom%')
                    ->orderBy('id')
                    ->value('id') ?? 0);
            }

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function asignarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
