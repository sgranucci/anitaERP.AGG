<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/cierres-turno';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/habilitacion-turno')->value('menu_id') ?? 10);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $rolId = $this->resolverRolEncGastronomiaId();

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Cierres de turno',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Cierres de turno',
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar cierres de turno gastronomía', 'slug' => 'listar-cierres-turno-gastronomia'],
            ['nombre' => 'Ver comprobante cierre turno gastronomía', 'slug' => 'ver-comprobante-cierre-turno-gastronomia'],
        ];

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            if ($rolId > 0) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        $permisoVer = (int) (DB::table('permiso')->where('slug', 'ver-comprobante-cierre-turno-gastronomia')->value('id') ?? 0);
        $permisoListar = (int) (DB::table('permiso')->where('slug', 'listar-cierres-turno-gastronomia')->value('id') ?? 0);
        $permisoGestionar = (int) (DB::table('permiso')->where('slug', 'gestionar-habilitacion-turno-gastronomia')->value('id') ?? 0);
        $permisoFact = (int) (DB::table('permiso')->where('slug', 'usar-proceso-facturacion-gastronomia')->value('id') ?? 0);

        foreach ([$permisoFact, $permisoGestionar] as $refId) {
            if ($refId <= 0) {
                continue;
            }
            foreach (DB::table('permiso_rol')->where('permiso_id', $refId)->pluck('rol_id')->unique() as $rid) {
                $rid = (int) $rid;
                if ($permisoListar > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoListar)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoListar, 'rol_id' => $rid]);
                }
                if ($permisoVer > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoVer)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoVer, 'rol_id' => $rid]);
                }
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

    private function resolverRolEncGastronomiaId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->value('id') ?? 0);
    }

    public function down(): void
    {
        $slugs = ['listar-cierres-turno-gastronomia', 'ver-comprobante-cierre-turno-gastronomia'];
        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }
        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
