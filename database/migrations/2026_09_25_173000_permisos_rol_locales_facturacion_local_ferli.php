<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: rol Locales ve el menú de Facturación Local pero sin permiso_rol
 * → "No tienes permisos para entrar en este modulo".
 * Asigna permisos de entrada y operación a Lugano / Caballito / Speratti Fer.
 */
return new class extends Migration
{
    private const ROL = 'Locales';

    /** @var list<string> */
    private const MENU_URLS = [
        'ventas/facturacion-local/stock',
        'ventas/facturacion-local/consulta-precios',
        'ventas/facturacion-local/cierres-turno',
        'ventas/tiendanube-pedidos',
        'ventas/facturacion-local/cambios-devolucion',
        'ventas/factura-mail-configuracion',
        '#facturacion-local',
    ];

    /**
     * Entrada a cada pantalla + operación típica de local.
     *
     * @var list<string>
     */
    private const PERMISO_SLUGS = [
        // Stock / precios
        'consultar-stock-local',
        'consultar-precios-local',
        // Cierres de turno
        'listar-turno-facturacion-local',
        'abrir-turno-facturacion-local',
        'cerrar-turno-facturacion-local',
        // Pedidos Tiendanube
        'listar-tiendanube-pedidos',
        'sincronizar-tiendanube-pedidos',
        'facturar-tiendanube-pedidos',
        // Cambios / devoluciones marketplace
        'listar-cambio-devolucion-marketplace-facturacion-local',
        'crear-cambio-devolucion-marketplace-facturacion-local',
        'ver-cambio-devolucion-marketplace-facturacion-local',
        'actualizar-cambio-devolucion-marketplace-facturacion-local',
        'emitir-fac-cambio-devolucion-marketplace-facturacion-local',
        'registrar-recepcion-cambio-devolucion-marketplace-facturacion-local',
        'emitir-nc-cambio-devolucion-marketplace-facturacion-local',
        'registrar-compensacion-cambio-devolucion-marketplace-facturacion-local',
        'anular-cambio-devolucion-marketplace-facturacion-local',
        // Mail de facturas
        'editar-factura-mail-configuracion',
        'actualizar-factura-mail-configuracion',
        'enviar-factura-mail',
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

        $menuIds = DB::table('menu')->whereIn('url', self::MENU_URLS)->pluck('id');
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
