<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Estacionamiento';

    private const MENU_URL = 'caja/estacionamiento/proceso-facturacion';

    private const MENU_REF_ROLES_URL = 'caja/estacionamiento/configuracion-puntoventa';

    private const CENTRO_COSTO_CODIGO = 98;

    private const PERMISO_SLUG = 'usar-proceso-facturacion-estacionamiento';

    public function up(): void
    {
        $padreId = $this->resolverMenuPadreId();
        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Proceso de facturación', $padreId, $orden, 'fa-calculator');

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Usar proceso de facturación estacionamiento',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Usar proceso de facturación estacionamiento',
                'updated_at' => now(),
            ]);
        }

        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach ($rolIdsMenuRef as $rolId) {
            $this->vincularMenuPermisoRol($menuId, $permisoId, (int) $rolId);
        }

        foreach ($rolIdsMenuRef as $rolId) {
            if ($padreId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        $this->asignarRolesCentroCosto98($menuId, $padreId, $permisoId);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuPadreId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
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

    private function vincularMenuPermisoRol(int $menuId, int $permisoId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    private function asignarRolesCentroCosto98(int $menuId, int $padreMenuId, int $permisoId): void
    {
        $centrocostoId = (int) (DB::table('centrocosto')->where('codigo', self::CENTRO_COSTO_CODIGO)->value('id') ?? 0);
        if ($centrocostoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')->where('centrocosto_id', $centrocostoId)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($padreMenuId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rid]);
            }
            $this->vincularMenuPermisoRol($menuId, $permisoId, $rid);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
