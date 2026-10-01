<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Bierzo: artículo sin cargo para Despacho y Enc-contaduría.
 * Enc-contaduría recibe menú y permisos de pedidos y remitos que aún no tenía.
 */
return new class extends Migration
{
    private const ROL_DESPACHO = 'Despacho';

    private const ROL_CONTADURIA = 'Enc-contaduría';

    private const SLUG_SIN_CARGO = 'entregar-articulo-sin-cargo-pedido-venta';

    /** @var list<string> */
    private const SLUGS_CONTADURIA = [
        'entregar-articulo-sin-cargo-pedido-venta',
        'listar-pedidos',
        'crear-pedidos',
        'editar-pedidos',
        'actualizar-pedidos',
        'borrar-pedidos',
        'borrar-items-pedidos',
        'cierre-de-pedidos',
        'facturar-reparto-pedidos',
        'transferir-pedido-despacho',
        'borrar-remitos',
        'modificar-precio-remito',
        'listar-motivos-cierre-pedido',
        'crear-motivos-cierre-pedido',
        'editar-motivos-cierre-pedido',
        'actualizar-motivos-cierre-pedido',
        'borrar-motivos-cierre-pedido',
        'listar-asignacion-remito-factura',
        'ejecutar-asignacion-remito-factura',
        'listar-importar-pedido-anita',
        'ejecutar-importar-pedido-anita',
        'listar-importar-remito-anita',
        'ejecutar-importar-remito-anita',
    ];

    /** @var list<string> */
    private const MENUS_CONTADURIA = [
        'ventas/asignacion-remito-factura',
        'ventas/importar-pedido-anita',
        'ventas/importar-remito-anita',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $despachoId = $this->rolId(self::ROL_DESPACHO);
        $contaduriaId = $this->rolId(self::ROL_CONTADURIA);

        if ($despachoId > 0) {
            $this->asignarPermiso($despachoId, self::SLUG_SIN_CARGO);
        }

        if ($contaduriaId > 0) {
            foreach (self::SLUGS_CONTADURIA as $slug) {
                $this->asignarPermiso($contaduriaId, $slug);
            }
            foreach (self::MENUS_CONTADURIA as $url) {
                $this->asignarMenuConPadre($contaduriaId, $url);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $despachoId = $this->rolId(self::ROL_DESPACHO);
        $contaduriaId = $this->rolId(self::ROL_CONTADURIA);

        if ($despachoId > 0) {
            $this->quitarPermiso($despachoId, self::SLUG_SIN_CARGO);
        }

        if ($contaduriaId > 0) {
            foreach (self::SLUGS_CONTADURIA as $slug) {
                $this->quitarPermiso($contaduriaId, $slug);
            }
            foreach (self::MENUS_CONTADURIA as $url) {
                $this->quitarMenuYPadreVacio($contaduriaId, $url);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function rolId(string $nombre): int
    {
        return (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
    }

    private function asignarPermiso(int $rolId, string $slug): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }
        if (DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            return;
        }
        DB::table('permiso_rol')->insert([
            'permiso_id' => $permisoId,
            'rol_id' => $rolId,
        ]);
    }

    private function quitarPermiso(int $rolId, string $slug): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
    }

    private function asignarMenuConPadre(int $rolId, string $url): void
    {
        $menu = DB::table('menu')->where('url', $url)->first(['id', 'menu_id']);
        if (! $menu) {
            return;
        }

        $padreId = (int) ($menu->menu_id ?? 0);
        if ($padreId > 0) {
            $this->asignarMenu($rolId, $padreId);
        }
        $this->asignarMenu($rolId, (int) $menu->id);
    }

    private function asignarMenu(int $rolId, int $menuId): void
    {
        if ($menuId <= 0) {
            return;
        }
        if (DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            return;
        }
        DB::table('menu_rol')->insert([
            'menu_id' => $menuId,
            'rol_id' => $rolId,
        ]);
    }

    private function quitarMenuYPadreVacio(int $rolId, string $url): void
    {
        $menu = DB::table('menu')->where('url', $url)->first(['id', 'menu_id']);
        if (! $menu) {
            return;
        }

        DB::table('menu_rol')->where('menu_id', (int) $menu->id)->where('rol_id', $rolId)->delete();

        $padreId = (int) ($menu->menu_id ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $hijos = DB::table('menu')->where('menu_id', $padreId)->pluck('id');
        $quedaHijo = DB::table('menu_rol')
            ->where('rol_id', $rolId)
            ->whereIn('menu_id', $hijos)
            ->exists();
        if (! $quedaHijo) {
            DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->delete();
        }
    }
};
