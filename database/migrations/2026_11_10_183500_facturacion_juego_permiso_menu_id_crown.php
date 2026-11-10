<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: los permisos de facturación juego se crearon sin menu_id,
 * así que no aparecen en la matriz menú–rol y el rol que tiene el menú
 * no puede entrar (can() rechaza el slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuProceso = (int) (DB::table('menu')->where('url', 'ventas/facturacion-juego')->value('id') ?? 0);
        $menuConfig = (int) (DB::table('menu')->where('url', 'ventas/facturacion-juego/configuracion')->value('id') ?? 0);
        $ahora = now();

        $porMenu = [
            'usar-facturacion-juego' => $menuProceso,
            'ejecutar-facturacion-juego' => $menuProceso,
            'configurar-facturacion-juego' => $menuConfig > 0 ? $menuConfig : $menuProceso,
        ];

        foreach ($porMenu as $slug => $menuId) {
            if ($menuId <= 0) {
                continue;
            }
            DB::table('permiso')->where('slug', $slug)->where(function ($q) use ($menuId) {
                $q->whereNull('menu_id')->orWhere('menu_id', '!=', $menuId);
            })->update([
                'menu_id' => $menuId,
                'updated_at' => $ahora,
            ]);
        }

        $this->otorgarARolesDelMenu($menuProceso, [
            'usar-facturacion-juego',
            'ejecutar-facturacion-juego',
        ]);
        if ($menuConfig > 0) {
            $this->otorgarARolesDelMenu($menuConfig, [
                'configurar-facturacion-juego',
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', [
                'usar-facturacion-juego',
                'ejecutar-facturacion-juego',
                'configurar-facturacion-juego',
            ])
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  list<string>  $slugs
     */
    private function otorgarARolesDelMenu(int $menuId, array $slugs): void
    {
        if ($menuId <= 0 || $slugs === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        $rolIds = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id');

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existe = DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('permiso_rol')->insert([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                ]);
            }
        }
    }
};
