<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa el módulo de Sueldos para los roles de Capital Humano.
 * Categorías, nombres de base, obras sociales y sindicatos se habían
 * creado solo para administrador. También el borrado de reportes
 * definibles y la nómina confidencial de liquidación.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const MENUS = [
        'sueldos/categoria',
        'sueldos/nombrebase',
        'sueldos/obrasocial',
        'sueldos/sindicato',
    ];

    /** Permisos de pantallas que ya ven, y que seguían solo en administrador. */
    /** @var list<string> */
    private const PERMISOS_EXTRA = [
        'eliminar-reporte-sueldos-definible',
        'ver-confidencial-recibo-sueldos',
        'importar-liquidacion-confidencial-sueldos',
    ];

    public function up(): void
    {
        $rolIds = $this->rolIdsCapitalHumano();
        if ($rolIds === []) {
            return;
        }

        $menuIds = DB::table('menu')->whereIn('url', self::MENUS)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        foreach ($menuIds as $menuId) {
            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
            }
        }

        $permisoIds = DB::table('permiso')
            ->where(function ($q) use ($menuIds) {
                $q->whereIn('menu_id', $menuIds)
                    ->orWhereIn('slug', self::PERMISOS_EXTRA);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
                $this->asegurarPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolIds = $this->rolIdsCapitalHumano();
        if ($rolIds === []) {
            return;
        }

        $menuIds = DB::table('menu')->whereIn('url', self::MENUS)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();
        if ($menuIds !== []) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->whereIn('rol_id', $rolIds)->delete();
        }

        $permisoIds = DB::table('permiso')
            ->where(function ($q) use ($menuIds) {
                $q->whereIn('menu_id', $menuIds)
                    ->orWhereIn('slug', self::PERMISOS_EXTRA);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->whereIn('rol_id', $rolIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function rolIdsCapitalHumano(): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', '%apital%umano%')
            ->orWhere('nombre', 'like', '%capitalhumano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        $existe = DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
        if (! $existe) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
    {
        $existe = DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists();
        if (! $existe) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }
};
