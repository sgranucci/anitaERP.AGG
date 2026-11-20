<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: rol Locales ve Pedidos Mercado Libre en el menú pero sin permiso_rol
 * no puede entrar (mismo patrón que Tiendanube en 2026_09_25_173000).
 * Asigna listar / sincronizar / facturar. No da autorizar ni configuración.
 */
return new class extends Migration
{
    private const ROL = 'Locales';

    /** @var list<string> */
    private const MENU_URLS = [
        '#facturacion-local',
        'ventas/mercadolibre-pedidos',
    ];

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-mercadolibre-pedidos',
        'sincronizar-mercadolibre-pedidos',
        'facturar-mercadolibre-pedidos',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $this->asignarMenus($rolId);
        $this->asignarPermisos($rolId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        // Solo quitar el ítem ML; el padre #facturacion-local lo usan otras pantallas.
        $menuIds = DB::table('menu')->where('url', 'ventas/mercadolibre-pedidos')->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->whereIn('menu_id', $menuIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asignarMenus(int $rolId): void
    {
        $menuIds = DB::table('menu')->whereIn('url', self::MENU_URLS)->pluck('id');
        foreach ($menuIds as $menuId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => (int) $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    private function asignarPermisos(int $rolId): void
    {
        $permisos = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->get(['id', 'slug']);

        foreach ($permisos as $permiso) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permiso->id)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => (int) $permiso->id,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
