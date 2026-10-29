<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de recetas para el perfil de Diego Dominguez (Ref-gastronomía)
 * y para Control de Gestión.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/recetas-reporte';

    private const PERMISO_SLUG = 'listar-recetas-reporte-gastronomia';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuReportesGastronomiaId();
        if ($parentMenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $datos = [
            'menu_id' => $parentMenuId,
            'nombre' => 'Recetas',
            'icono' => 'fa-book',
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

        $permisoId = $this->upsertPermiso($menuId);

        foreach ($this->resolverRolIds() as $rolId) {
            $this->asignarMenuRol($menuId, $rolId);
            $this->asignarPermisoRol($permisoId, $rolId);
            $this->asegurarAncestrosMenu($menuId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
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
        $nombres = [
            'administrador',
            'Ref-gastronomía',
            'Enc-Control de Gestión',
            'Op-Control de Gestión',
        ];

        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0 && str_starts_with($nombre, 'Ref-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ref-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Enc-Control')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-Control de Gesti%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Op-Control')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Op-Control de Gesti%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (DB::table('rol')->where('nombre', 'like', '%Control de Gesti%')->pluck('id') as $rolId) {
            $ids[] = (int) $rolId;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $datos = [
            'nombre' => 'Listar reporte de recetas gastronomía',
            'slug' => self::PERMISO_SLUG,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($datos);

            return $id;
        }

        $datos['created_at'] = now();

        return (int) DB::table('permiso')->insertGetId($datos);
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

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asegurarAncestrosMenu(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        for ($i = 0; $i < 8; $i++) {
            $padreId = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padreId <= 0) {
                break;
            }
            $this->asignarMenuRol($padreId, $rolId);
            $actual = $padreId;
        }
    }
};
