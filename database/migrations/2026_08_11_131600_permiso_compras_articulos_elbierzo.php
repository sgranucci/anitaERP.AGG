<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solapas Compras / Proveedores del ABM Artículos.
 * Requiere editar-compras-articulos / actualizar-compras-articulos (tabs_header).
 * Solo EL BIERZO. En AGG no tiene efecto.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/articulo';

    /** Rol Enc-produccion-surmar (joaquinm). */
    private const ROL_ID = 10;

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar compras articulos', 'slug' => 'editar-compras-articulos'],
        ['nombre' => 'Actualizar compras articulos', 'slug' => 'actualizar-compras-articulos'],
    ];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('id', self::ROL_ID)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }

        foreach ($permisoIds as $permisoId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        try {
            cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isEmpty()) {
            return;
        }

        DB::table('permiso_rol')
            ->where('rol_id', self::ROL_ID)
            ->whereIn('permiso_id', $permisoIds)
            ->delete();

        DB::table('permiso')->whereIn('id', $permisoIds)->delete();

        SuitecrmPermiso::flushCachePermisos();
        try {
            cache()->tags('Permiso')->forget('Permiso.rolid.'.self::ROL_ID);
        } catch (\Throwable) {
        }
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
