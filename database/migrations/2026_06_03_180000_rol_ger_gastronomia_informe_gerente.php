<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/informe-gerente';

    private const ROL_ENC = 'Enc-gastronomía';

    private const ROL_GER = 'Ger-gastronomía';

    private const SLUG_INFORME = 'ver-informe-gerente-gastronomia';

    public function up(): void
    {
        $rolGerId = $this->resolverOCrearRolGer();
        $rolEncId = $this->resolverRolEncId();

        if ($rolGerId > 0 && $rolEncId > 0) {
            $this->copiarMenusYPermisosDesdeEnc($rolEncId, $rolGerId);
        }

        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/facturas-dia')->value('menu_id') ?? 10);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Informe gerente',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-pie-chart',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Informe gerente',
                'orden' => $orden,
                'icono' => 'fa-pie-chart',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_INFORME)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Ver informe gerente gastronomía',
                'slug' => self::SLUG_INFORME,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Ver informe gerente gastronomía',
                'updated_at' => now(),
            ]);
        }

        if ($rolGerId > 0) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolGerId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolGerId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolGerId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolGerId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverOCrearRolGer(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_GER)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ger-gastronom%')->orderBy('id')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_GER,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolverRolEncId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
    }

    private function copiarMenusYPermisosDesdeEnc(int $rolEncId, int $rolGerId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id') as $menuId) {
            $mid = (int) $menuId;
            if ($mid > 0 && ! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolGerId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolGerId]);
            }
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id') as $permisoId) {
            $pid = (int) $permisoId;
            if ($pid > 0 && ! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolGerId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolGerId]);
            }
        }
    }

    private function resolverMenuGastronomiaId(): int
    {
        return (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_INFORME)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
