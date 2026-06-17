<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{nombre: string, padre: int, icono: ?string}> */
    private const MENUS_A_CREAR = array (
  'caja/origenvoucher' => 
  array (
    'nombre' => 'Origenvoucher',
    'padre' => 104,
    'icono' => NULL,
  ),
  'caja/talonariorendicion' => 
  array (
    'nombre' => 'Talonariorendicion',
    'padre' => 104,
    'icono' => NULL,
  ),
  'caja/talonariovoucher' => 
  array (
    'nombre' => 'Talonariovoucher',
    'padre' => 104,
    'icono' => NULL,
  ),
  'caja/voucher' => 
  array (
    'nombre' => 'Voucher',
    'padre' => 104,
    'icono' => NULL,
  ),
  'configuracion/feriado' => 
  array (
    'nombre' => 'Feriado',
    'padre' => 33,
    'icono' => NULL,
  ),
  'configuracion/padron_exclusionpercepcioniva' => 
  array (
    'nombre' => 'Padron Exclusionpercepcioniva',
    'padre' => 33,
    'icono' => NULL,
  ),
  'configuracion/padron_mipyme' => 
  array (
    'nombre' => 'Padron Mipyme',
    'padre' => 33,
    'icono' => NULL,
  ),
  'stock/color' => 
  array (
    'nombre' => 'Color',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/combinacion/index' => 
  array (
    'nombre' => 'Combinaciones',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/compfondo' => 
  array (
    'nombre' => 'Compfondo',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/contrafuerte' => 
  array (
    'nombre' => 'Contrafuerte',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/crearimportaciontiendanube' => 
  array (
    'nombre' => 'Importación Tienda Nube',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/fondo' => 
  array (
    'nombre' => 'Fondo',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/forro' => 
  array (
    'nombre' => 'Forro',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/horma' => 
  array (
    'nombre' => 'Horma',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/lote' => 
  array (
    'nombre' => 'Lote',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/material' => 
  array (
    'nombre' => 'Material',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/materialavio' => 
  array (
    'nombre' => 'Materialavio',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/materialcapellada' => 
  array (
    'nombre' => 'Materialcapellada',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/modulo' => 
  array (
    'nombre' => 'Modulo',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/movimientostock' => 
  array (
    'nombre' => 'Movimientostock',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/numeracion' => 
  array (
    'nombre' => 'Numeracion',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/plarmado' => 
  array (
    'nombre' => 'Plarmado',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/plvista' => 
  array (
    'nombre' => 'Plvista',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/puntera' => 
  array (
    'nombre' => 'Puntera',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/talle' => 
  array (
    'nombre' => 'Talle',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/tipocorte' => 
  array (
    'nombre' => 'Tipocorte',
    'padre' => 10,
    'icono' => NULL,
  ),
  'stock/tiponumeracion' => 
  array (
    'nombre' => 'Tiponumeracion',
    'padre' => 10,
    'icono' => NULL,
  ),
  'ventas/incoterm' => 
  array (
    'nombre' => 'Incoterm',
    'padre' => 51,
    'icono' => NULL,
  ),
  'ventas/motivocierrepedido' => 
  array (
    'nombre' => 'Motivocierrepedido',
    'padre' => 51,
    'icono' => NULL,
  ),
  'ventas/ordenestrabajo' => 
  array (
    'nombre' => 'Ordenestrabajo',
    'padre' => 51,
    'icono' => NULL,
  ),
  'ventas/pedido' => 
  array (
    'nombre' => 'Pedido',
    'padre' => 51,
    'icono' => NULL,
  ),
);

    /** @var array<string, list<string>> */
    private const PERMISOS_POR_MENU = array (
  'caja/origenvoucher' => 
  array (
    0 => 'crear-origen-de-voucher',
    1 => 'crea-origen-de-voucher',
    2 => 'listar-origen-de-voucher',
    3 => 'lista-origen-de-voucher',
    4 => 'editar-origen-de-voucher',
    5 => 'edita-origen-de-voucher',
    6 => 'actualizar-origen-de-voucher',
    7 => 'actualiza-origen-de-voucher',
    8 => 'borrar-origen-de-voucher',
    9 => 'borra-origen-de-voucher',
  ),
  'caja/talonariorendicion' => 
  array (
    0 => 'crear-talonario-de-rendicion',
    1 => 'crea-talonario-de-rendicion',
    2 => 'listar-talonario-de-rendicion',
    3 => 'lista-talonario-de-rendicion',
    4 => 'editar-talonario-de-rendicion',
    5 => 'edita-talonario-de-rendicion',
    6 => 'actualizar-talonario-de-rendicion',
    7 => 'actualiza-talonario-de-rendicion',
    8 => 'borrar-talonario-de-rendicion',
    9 => 'borra-talonario-de-rendicion',
  ),
  'caja/talonariovoucher' => 
  array (
    0 => 'crear-talonario-de-voucher',
    1 => 'crea-talonario-de-voucher',
    2 => 'listar-talonario-de-voucher',
    3 => 'lista-talonario-de-voucher',
    4 => 'editar-talonario-de-voucher',
    5 => 'edita-talonario-de-voucher',
    6 => 'actualizar-talonario-de-voucher',
    7 => 'actualiza-talonario-de-voucher',
    8 => 'borrar-talonario-de-voucher',
    9 => 'borra-talonario-de-voucher',
  ),
  'caja/voucher' => 
  array (
    0 => 'crear-voucher',
    1 => 'crea-voucher',
    2 => 'listar-voucher',
    3 => 'lista-voucher',
    4 => 'editar-voucher',
    5 => 'edita-voucher',
    6 => 'actualizar-voucher',
    7 => 'actualiza-voucher',
    8 => 'borrar-voucher',
    9 => 'borra-voucher',
  ),
  'configuracion/feriado' => 
  array (
    0 => 'crear-feriado',
    1 => 'crea-feriado',
    2 => 'listar-feriado',
    3 => 'lista-feriado',
    4 => 'editar-feriado',
    5 => 'edita-feriado',
    6 => 'actualizar-feriado',
    7 => 'actualiza-feriado',
    8 => 'borrar-feriado',
    9 => 'borra-feriado',
  ),
  'configuracion/padron_exclusionpercepcioniva' => 
  array (
    0 => 'crear-padron-mipyme',
    1 => 'crea-padron-mipyme',
    2 => 'listar-padron-mipyme',
    3 => 'lista-padron-mipyme',
    4 => 'editar-padron-mipyme',
    5 => 'edita-padron-mipyme',
    6 => 'actualizar-padron-mipyme',
    7 => 'actualiza-padron-mipyme',
    8 => 'borrar-padron-mipyme',
    9 => 'borra-padron-mipyme',
    10 => 'editar-padron-exclusion-percepcion-iva',
    11 => 'edita-padron-exclusion-percepcion-iva',
    12 => 'importar-padron-exclusion-percepcion-iva',
  ),
  'configuracion/padron_mipyme' => 
  array (
    0 => 'importar-padron-mipyme',
  ),
  'stock/color' => 
  array (
    0 => 'crear-colores',
    1 => 'crea-colores',
    2 => 'listar-colores',
    3 => 'lista-colores',
    4 => 'editar-colores',
    5 => 'edita-colores',
    6 => 'actualizar-colores',
    7 => 'actualiza-colores',
    8 => 'borrar-colores',
    9 => 'borra-colores',
  ),
  'stock/combinacion/index' => 
  array (
    0 => 'crear-combinaciones',
    1 => 'crea-combinaciones',
    2 => 'cambiar-estado-combinaciones',
    3 => 'editar-combinaciones-disenio',
    4 => 'edita-combinaciones-disenio',
    5 => 'editar-combinaciones-tecnica',
    6 => 'edita-combinaciones-tecnica',
    7 => 'borrar-combinaciones',
    8 => 'borra-combinaciones',
    9 => 'actualizar-combinaciones-tecnica',
    10 => 'actualiza-combinaciones-tecnica',
  ),
  'stock/compfondo' => 
  array (
    0 => 'crear-composicion-fondos',
    1 => 'crea-composicion-fondos',
    2 => 'listar-composicion-fondos',
    3 => 'lista-composicion-fondos',
    4 => 'editar-composicion-fondos',
    5 => 'edita-composicion-fondos',
    6 => 'actualizar-composicion-fondos',
    7 => 'actualiza-composicion-fondos',
    8 => 'borrar-composicion-fondos',
    9 => 'borra-composicion-fondos',
  ),
  'stock/contrafuerte' => 
  array (
    0 => 'crear-contrafuertes',
    1 => 'crea-contrafuertes',
    2 => 'listar-contrafuertes',
    3 => 'lista-contrafuertes',
    4 => 'editar-contrafuertes',
    5 => 'edita-contrafuertes',
    6 => 'actualizar-contrafuertes',
    7 => 'actualiza-contrafuertes',
    8 => 'borrar-contrafuertes',
    9 => 'borra-contrafuertes',
  ),
  'stock/crearimportaciontiendanube' => 
  array (
    0 => 'importar-tiendanube',
  ),
  'stock/fondo' => 
  array (
    0 => 'crear-fondos',
    1 => 'crea-fondos',
    2 => 'listar-fondos',
    3 => 'lista-fondos',
    4 => 'editar-fondos',
    5 => 'edita-fondos',
    6 => 'actualizar-fondos',
    7 => 'actualiza-fondos',
    8 => 'borrar-fondos',
    9 => 'borra-fondos',
  ),
  'stock/forro' => 
  array (
    0 => 'crear-forros',
    1 => 'crea-forros',
    2 => 'listar-forros',
    3 => 'lista-forros',
    4 => 'editar-forros',
    5 => 'edita-forros',
    6 => 'actualizar-forros',
    7 => 'actualiza-forros',
    8 => 'borrar-forros',
    9 => 'borra-forros',
  ),
  'stock/horma' => 
  array (
    0 => 'crear-hormas',
    1 => 'crea-hormas',
    2 => 'listar-hormas',
    3 => 'lista-hormas',
    4 => 'editar-hormas',
    5 => 'edita-hormas',
    6 => 'actualizar-hormas',
    7 => 'actualiza-hormas',
    8 => 'borrar-hormas',
    9 => 'borra-hormas',
  ),
  'stock/lote' => 
  array (
    0 => 'crear-lotes',
    1 => 'crea-lotes',
    2 => 'listar-lotes',
    3 => 'lista-lotes',
    4 => 'editar-lotes',
    5 => 'edita-lotes',
    6 => 'actualizar-lotes',
    7 => 'actualiza-lotes',
    8 => 'borrar-lotes',
    9 => 'borra-lotes',
  ),
  'stock/material' => 
  array (
    0 => 'crear-materiales',
    1 => 'crea-materiales',
    2 => 'listar-materiales',
    3 => 'lista-materiales',
    4 => 'editar-materiales',
    5 => 'edita-materiales',
    6 => 'actualizar-materiales',
    7 => 'actualiza-materiales',
    8 => 'borrar-materiales',
    9 => 'borra-materiales',
  ),
  'stock/materialavio' => 
  array (
    0 => 'crear-avios',
    1 => 'crea-avios',
    2 => 'listar-avios',
    3 => 'lista-avios',
    4 => 'editar-avios',
    5 => 'edita-avios',
    6 => 'actualizar-avios',
    7 => 'actualiza-avios',
    8 => 'borrar-avios',
    9 => 'borra-avios',
  ),
  'stock/materialcapellada' => 
  array (
    0 => 'crear-capelladas',
    1 => 'crea-capelladas',
    2 => 'listar-capelladas',
    3 => 'lista-capelladas',
    4 => 'editar-capelladas',
    5 => 'edita-capelladas',
    6 => 'actualizar-capelladas',
    7 => 'actualiza-capelladas',
    8 => 'borrar-capelladas',
    9 => 'borra-capelladas',
  ),
  'stock/modulo' => 
  array (
    0 => 'crear-modulos',
    1 => 'crea-modulos',
    2 => 'listar-modulos',
    3 => 'lista-modulos',
    4 => 'editar-modulos',
    5 => 'edita-modulos',
    6 => 'actualizar-modulos',
    7 => 'actualiza-modulos',
    8 => 'borrar-modulos',
    9 => 'borra-modulos',
  ),
  'stock/movimientostock' => 
  array (
    0 => 'crear-movimientos-de-stock',
    1 => 'crea-movimientos-de-stock',
    2 => 'listar-movimientos-de-stock',
    3 => 'lista-movimientos-de-stock',
    4 => 'editar-movimientos-de-stock',
    5 => 'edita-movimientos-de-stock',
    6 => 'actualizar-movimientos-de-stock',
    7 => 'actualiza-movimientos-de-stock',
    8 => 'borrar-movimientos-de-stock',
    9 => 'borra-movimientos-de-stock',
  ),
  'stock/numeracion' => 
  array (
    0 => 'crear-numeraciones',
    1 => 'crea-numeraciones',
    2 => 'listar-numeraciones',
    3 => 'lista-numeraciones',
    4 => 'editar-numeraciones',
    5 => 'edita-numeraciones',
    6 => 'actualizar-numeraciones',
    7 => 'actualiza-numeraciones',
    8 => 'borrar-numeraciones',
    9 => 'borra-numeraciones',
  ),
  'stock/plarmado' => 
  array (
    0 => 'crear-plantilla-de-armado',
    1 => 'crea-plantilla-de-armado',
    2 => 'listar-plantilla-de-armado',
    3 => 'lista-plantilla-de-armado',
    4 => 'editar-plantilla-de-armado',
    5 => 'edita-plantilla-de-armado',
    6 => 'actualizar-plantilla-de-armado',
    7 => 'actualiza-plantilla-de-armado',
    8 => 'borrar-plantilla-de-armado',
    9 => 'borra-plantilla-de-armado',
  ),
  'stock/plvista' => 
  array (
    0 => 'crear-plantillas-vista',
    1 => 'crea-plantillas-vista',
    2 => 'listar-plantillas-vista',
    3 => 'lista-plantillas-vista',
    4 => 'editar-plantillas-vista',
    5 => 'edita-plantillas-vista',
    6 => 'actualizar-plantillas-vista',
    7 => 'actualiza-plantillas-vista',
    8 => 'borrar-plantillas-vista',
    9 => 'borra-plantillas-vista',
  ),
  'stock/puntera' => 
  array (
    0 => 'crear-punteras',
    1 => 'crea-punteras',
    2 => 'listar-punteras',
    3 => 'lista-punteras',
    4 => 'editar-punteras',
    5 => 'edita-punteras',
    6 => 'actualizar-punteras',
    7 => 'actualiza-punteras',
    8 => 'borrar-punteras',
    9 => 'borra-punteras',
  ),
  'stock/talle' => 
  array (
    0 => 'crear-talles',
    1 => 'crea-talles',
    2 => 'listar-talles',
    3 => 'lista-talles',
    4 => 'editar-talles',
    5 => 'edita-talles',
    6 => 'actualizar-talles',
    7 => 'actualiza-talles',
    8 => 'borrar-talles',
    9 => 'borra-talles',
  ),
  'stock/tipocorte' => 
  array (
    0 => 'crear-tipo-cortes',
    1 => 'crea-tipo-cortes',
    2 => 'listar-tipo-cortes',
    3 => 'lista-tipo-cortes',
    4 => 'editar-tipo-cortes',
    5 => 'edita-tipo-cortes',
    6 => 'actualizar-tipo-cortes',
    7 => 'actualiza-tipo-cortes',
    8 => 'borrar-tipo-cortes',
    9 => 'borra-tipo-cortes',
  ),
  'stock/tiponumeracion' => 
  array (
    0 => 'crear-tipo-numeraciones',
    1 => 'crea-tipo-numeraciones',
    2 => 'listar-tipo-numeraciones',
    3 => 'lista-tipo-numeraciones',
    4 => 'editar-tipo-numeraciones',
    5 => 'edita-tipo-numeraciones',
    6 => 'actualizar-tipo-numeraciones',
    7 => 'actualiza-tipo-numeraciones',
    8 => 'borrar-tipo-numeraciones',
    9 => 'borra-tipo-numeraciones',
  ),
  'ventas/factura' => 
  array (
    0 => 'generar-nota-de-credito',
  ),
  'ventas/incoterm' => 
  array (
    0 => 'crear-incoterms',
    1 => 'crea-incoterms',
    2 => 'listar-incoterms',
    3 => 'lista-incoterms',
    4 => 'editar-incoterms',
    5 => 'edita-incoterms',
    6 => 'actualizar-incoterms',
    7 => 'actualiza-incoterms',
    8 => 'borrar-incoterms',
    9 => 'borra-incoterms',
  ),
  'ventas/motivocierrepedido' => 
  array (
    0 => 'crear-motivos-cierre-pedido',
    1 => 'crea-motivos-cierre-pedido',
    2 => 'listar-motivos-cierre-pedido',
    3 => 'lista-motivos-cierre-pedido',
    4 => 'editar-motivos-cierre-pedido',
    5 => 'edita-motivos-cierre-pedido',
    6 => 'actualizar-motivos-cierre-pedido',
    7 => 'actualiza-motivos-cierre-pedido',
    8 => 'borrar-motivos-cierre-pedido',
    9 => 'borra-motivos-cierre-pedido',
  ),
  'ventas/ordenestrabajo' => 
  array (
    0 => 'crear-ordenes-de-trabajo',
    1 => 'crea-ordenes-de-trabajo',
    2 => 'listar-ordenes-de-trabajo',
    3 => 'lista-ordenes-de-trabajo',
    4 => 'editar-ordenes-de-trabajo',
    5 => 'edita-ordenes-de-trabajo',
    6 => 'actualizar-ordenes-de-trabajo',
    7 => 'actualiza-ordenes-de-trabajo',
    8 => 'borrar-ordenes-de-trabajo',
    9 => 'borra-ordenes-de-trabajo',
    10 => 'facturar-ordenes-de-trabajo',
  ),
  'ventas/pedido' => 
  array (
    0 => 'crear-pedidos',
    1 => 'crea-pedidos',
    2 => 'listar-pedidos',
    3 => 'lista-pedidos',
    4 => 'editar-pedidos',
    5 => 'edita-pedidos',
    6 => 'actualizar-pedidos',
    7 => 'actualiza-pedidos',
    8 => 'borrar-pedidos',
    9 => 'borra-pedidos',
    10 => 'cierre-de-pedidos',
    11 => 'borrar-items-pedidos',
    12 => 'borra-items-pedidos',
  ),
);

    public function up(): void
    {
        foreach (self::MENUS_A_CREAR as $url => $meta) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $meta['padre'])->max('orden') ?? 0) + 1;
                $menuId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $meta['padre'],
                    'nombre' => $meta['nombre'],
                    'url' => $url,
                    'orden' => $orden,
                    'icono' => $meta['icono'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (self::PERMISOS_POR_MENU as $menuUrl => $slugs) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }

            DB::table('permiso')
                ->whereIn('slug', $slugs)
                ->where(function ($query) {
                    $query->whereNull('menu_id')->orWhere('menu_id', 0);
                })
                ->update([
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [];
        foreach (self::PERMISOS_POR_MENU as $rows) {
            $slugs = array_merge($slugs, $rows);
        }

        DB::table('permiso')
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->update(['menu_id' => null, 'updated_at' => now()]);

        foreach (array_keys(self::MENUS_A_CREAR) as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
