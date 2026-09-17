<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: vincular/crear permisos de opciones de menú.
 *
 * - Crea slugs que el controller exige y no existen.
 * - Vincula a menu_id los permisos con menu_id null (p.ej. mayor plano).
 * - Asigna a administrador + roles con menu_rol (+ roles del slug legacy).
 *
 * Solo corre en CALZADOS FERLI.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_NOMBRE = ['administrador'];

    private const LEGACY_ALIAS = [
        'listar-caja' => 'lista-caja',
        'crear-caja' => 'crea-caja',
        'editar-caja' => 'edita-caja',
        'actualizar-caja' => 'actualiza-caja',
        'borrar-caja' => 'borra-caja',
        'listar-movimientos-caja' => 'lista-movimientos-caja',
        'crear-movimientos-caja' => 'crea-movimientos-caja',
        'editar-movimientos-caja' => 'edita-movimientos-caja',
        'actualizar-movimientos-caja' => 'actualiza-movimientos-caja',
        'borrar-movimientos-caja' => 'borra-movimientos-caja',
        'listar-chequera' => 'lista-chequera',
        'crear-chequera' => 'crea-chequera',
        'editar-chequera' => 'edita-chequera',
        'actualizar-chequera' => 'actualiza-chequera',
        'borrar-chequera' => 'borra-chequera',
        'listar-estado-cheque-banco' => 'lista-estado-cheque-banco',
        'crear-estado-cheque-banco' => 'crea-estado-cheque-banco',
        'editar-estado-cheque-banco' => 'edita-estado-cheque-banco',
        'actualizar-estado-cheque-banco' => 'actualiza-estado-cheque-banco',
        'borrar-estado-cheque-banco' => 'borra-estado-cheque-banco',
        'listar-serigrafias' => 'listar-serigrafrias',
        'crear-serigrafias' => 'crear-serigrafrias',
        'editar-serigrafias' => 'editar-serigrafrias',
        'actualizar-serigrafias' => 'actualizar-serigrafrias',
        'borrar-serigrafias' => 'borrar-serigrafrias',
    ];

    /** @var array<string, list<array{nombre: string, slug: string}>> */
    private const PERMISOS_POR_MENU = array (
  'caja/banco' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar banco',
      'slug' => 'actualizar-banco',
    ),
    1 => 
    array (
      'nombre' => 'Borrar banco',
      'slug' => 'borrar-banco',
    ),
    2 => 
    array (
      'nombre' => 'Crear banco',
      'slug' => 'crear-banco',
    ),
    3 => 
    array (
      'nombre' => 'Editar banco',
      'slug' => 'editar-banco',
    ),
    4 => 
    array (
      'nombre' => 'Listar banco',
      'slug' => 'listar-banco',
    ),
  ),
  'caja/bingo/carton' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cartones bingo',
      'slug' => 'actualizar-bingo-carton',
    ),
    1 => 
    array (
      'nombre' => 'Borrar cartones bingo',
      'slug' => 'borrar-bingo-carton',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar cartones bingo',
      'slug' => 'crear-bingo-carton',
    ),
    3 => 
    array (
      'nombre' => 'Editar cartones bingo',
      'slug' => 'editar-bingo-carton',
    ),
    4 => 
    array (
      'nombre' => 'Listar cartones bingo',
      'slug' => 'listar-bingo-carton',
    ),
  ),
  'caja/bingo/cierres-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar cierres de turno bingo',
      'slug' => 'listar-cierres-turno-bingo',
    ),
  ),
  'caja/bingo/concepto-rendicion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar conceptos rendición bingo',
      'slug' => 'actualizar-bingo-concepto-rendicion',
    ),
    1 => 
    array (
      'nombre' => 'Borrar conceptos rendición bingo',
      'slug' => 'borrar-bingo-concepto-rendicion',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar conceptos rendición bingo',
      'slug' => 'crear-bingo-concepto-rendicion',
    ),
    3 => 
    array (
      'nombre' => 'Editar conceptos rendición bingo',
      'slug' => 'editar-bingo-concepto-rendicion',
    ),
    4 => 
    array (
      'nombre' => 'Listar conceptos rendición bingo',
      'slug' => 'listar-bingo-concepto-rendicion',
    ),
  ),
  'caja/bingo/configuracion-puntoventa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar config. terminal bingo',
      'slug' => 'actualizar-configuracion-puntoventa-bingo',
    ),
    1 => 
    array (
      'nombre' => 'Borrar config. terminal bingo',
      'slug' => 'borrar-configuracion-puntoventa-bingo',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar config. terminal bingo',
      'slug' => 'crear-configuracion-puntoventa-bingo',
    ),
    3 => 
    array (
      'nombre' => 'Editar config. terminal bingo',
      'slug' => 'editar-configuracion-puntoventa-bingo',
    ),
    4 => 
    array (
      'nombre' => 'Listar config. terminal bingo',
      'slug' => 'listar-configuracion-puntoventa-bingo',
    ),
  ),
  'caja/bingo/habilitacion-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Gestionar habilitación de turno bingo',
      'slug' => 'gestionar-habilitacion-turno-bingo',
    ),
  ),
  'caja/bingo/jornada' => 
  array (
    0 => 
    array (
      'nombre' => 'Abrir jornada bingo',
      'slug' => 'abrir-jornada-bingo',
    ),
    1 => 
    array (
      'nombre' => 'Cerrar jornada bingo',
      'slug' => 'cerrar-jornada-bingo',
    ),
    2 => 
    array (
      'nombre' => 'Eliminar jornada bingo',
      'slug' => 'eliminar-jornada-bingo',
    ),
    3 => 
    array (
      'nombre' => 'Gestionar jornada bingo',
      'slug' => 'gestionar-jornada-bingo',
    ),
  ),
  'caja/bingo/turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar turnos bingo',
      'slug' => 'actualizar-turno-bingo',
    ),
    1 => 
    array (
      'nombre' => 'Borrar turnos bingo',
      'slug' => 'borrar-turno-bingo',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar turnos bingo',
      'slug' => 'crear-turno-bingo',
    ),
    3 => 
    array (
      'nombre' => 'Editar turnos bingo',
      'slug' => 'editar-turno-bingo',
    ),
    4 => 
    array (
      'nombre' => 'Listar turnos bingo',
      'slug' => 'listar-turno-bingo',
    ),
  ),
  'caja/caja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar caja',
      'slug' => 'actualizar-caja',
    ),
    1 => 
    array (
      'nombre' => 'Borrar caja',
      'slug' => 'borrar-caja',
    ),
    2 => 
    array (
      'nombre' => 'Crear caja',
      'slug' => 'crear-caja',
    ),
    3 => 
    array (
      'nombre' => 'Editar caja',
      'slug' => 'editar-caja',
    ),
    4 => 
    array (
      'nombre' => 'Listar caja',
      'slug' => 'listar-caja',
    ),
  ),
  'caja/cheque' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cheque',
      'slug' => 'actualizar-cheque',
    ),
    1 => 
    array (
      'nombre' => 'Borrar cheque',
      'slug' => 'borrar-cheque',
    ),
    2 => 
    array (
      'nombre' => 'Crear cheque',
      'slug' => 'crear-cheque',
    ),
    3 => 
    array (
      'nombre' => 'Editar cheque',
      'slug' => 'editar-cheque',
    ),
    4 => 
    array (
      'nombre' => 'Listar cheque',
      'slug' => 'listar-cheque',
    ),
  ),
  'caja/chequera' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar chequera',
      'slug' => 'actualizar-chequera',
    ),
    1 => 
    array (
      'nombre' => 'Borrar chequera',
      'slug' => 'borrar-chequera',
    ),
    2 => 
    array (
      'nombre' => 'Crear chequera',
      'slug' => 'crear-chequera',
    ),
    3 => 
    array (
      'nombre' => 'Editar chequera',
      'slug' => 'editar-chequera',
    ),
    4 => 
    array (
      'nombre' => 'Listar chequera',
      'slug' => 'listar-chequera',
    ),
  ),
  'caja/conceptogasto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar conceptos de gastos',
      'slug' => 'actualizar-conceptos-de-gastos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar conceptos de gastos',
      'slug' => 'borrar-conceptos-de-gastos',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar conceptos de gastos',
      'slug' => 'crear-conceptos-de-gastos',
    ),
    3 => 
    array (
      'nombre' => 'Editar conceptos de gastos',
      'slug' => 'editar-conceptos-de-gastos',
    ),
    4 => 
    array (
      'nombre' => 'Listar conceptos de gastos',
      'slug' => 'listar-conceptos-de-gastos',
    ),
  ),
  'caja/cuentacaja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cuentas de caja',
      'slug' => 'actualizar-cuentas-de-caja',
    ),
    1 => 
    array (
      'nombre' => 'Borrar cuentas de caja',
      'slug' => 'borrar-cuentas-de-caja',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar cuentas de caja',
      'slug' => 'crear-cuentas-de-caja',
    ),
    3 => 
    array (
      'nombre' => 'Editar cuentas de caja',
      'slug' => 'editar-cuentas-de-caja',
    ),
    4 => 
    array (
      'nombre' => 'Listar cuentas de caja',
      'slug' => 'listar-cuentas-de-caja',
    ),
  ),
  'caja/estacionamiento/categoria-automovil' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar categorías automóvil estacionamiento',
      'slug' => 'actualizar-estacionamiento-categoria-automovil',
    ),
    1 => 
    array (
      'nombre' => 'Borrar categorías automóvil estacionamiento',
      'slug' => 'borrar-estacionamiento-categoria-automovil',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar categorías automóvil estacionamiento',
      'slug' => 'crear-estacionamiento-categoria-automovil',
    ),
    3 => 
    array (
      'nombre' => 'Editar categorías automóvil estacionamiento',
      'slug' => 'editar-estacionamiento-categoria-automovil',
    ),
    4 => 
    array (
      'nombre' => 'Listar categorías automóvil estacionamiento',
      'slug' => 'listar-estacionamiento-categoria-automovil',
    ),
  ),
  'caja/estacionamiento/cierres-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar cierres de turno estacionamiento',
      'slug' => 'listar-cierres-turno-estacionamiento',
    ),
  ),
  'caja/estacionamiento/configuracion-puntoventa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuracion puntoventa estacionamient',
      'slug' => 'actualizar-configuracion-puntoventa-estacionamiento',
    ),
    1 => 
    array (
      'nombre' => 'Borrar config. PV estacionamiento',
      'slug' => 'borrar-configuracion-puntoventa-estacionamiento',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar config. PV estacionamiento',
      'slug' => 'crear-configuracion-puntoventa-estacionamiento',
    ),
    3 => 
    array (
      'nombre' => 'Editar config. PV estacionamiento',
      'slug' => 'editar-configuracion-puntoventa-estacionamiento',
    ),
    4 => 
    array (
      'nombre' => 'Listar config. PV estacionamiento',
      'slug' => 'listar-configuracion-puntoventa-estacionamiento',
    ),
  ),
  'caja/estacionamiento/descuento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar descuentos estacionamiento',
      'slug' => 'actualizar-descuento-estacionamiento',
    ),
    1 => 
    array (
      'nombre' => 'Borrar descuentos estacionamiento',
      'slug' => 'borrar-descuento-estacionamiento',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar descuentos estacionamiento',
      'slug' => 'crear-descuento-estacionamiento',
    ),
    3 => 
    array (
      'nombre' => 'Editar descuentos estacionamiento',
      'slug' => 'editar-descuento-estacionamiento',
    ),
    4 => 
    array (
      'nombre' => 'Listar descuentos estacionamiento',
      'slug' => 'listar-descuento-estacionamiento',
    ),
  ),
  'caja/estacionamiento/facturas-dia' => 
  array (
    0 => 
    array (
      'nombre' => 'Cambiar medio pago estacionamiento facturas dia',
      'slug' => 'cambiar-medio-pago-estacionamiento-facturas-dia',
    ),
    1 => 
    array (
      'nombre' => 'Generar nota credito estacionamiento facturas dia',
      'slug' => 'generar-nota-credito-estacionamiento-facturas-dia',
    ),
  ),
  'caja/estacionamiento/habilitacion-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Gestionar habilitación de turno estacionamiento',
      'slug' => 'gestionar-habilitacion-turno-estacionamiento',
    ),
  ),
  'caja/estacionamiento/item' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ítems estacionamiento',
      'slug' => 'actualizar-estacionamiento-item',
    ),
    1 => 
    array (
      'nombre' => 'Borrar ítems estacionamiento',
      'slug' => 'borrar-estacionamiento-item',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar ítems estacionamiento',
      'slug' => 'crear-estacionamiento-item',
    ),
    3 => 
    array (
      'nombre' => 'Editar ítems estacionamiento',
      'slug' => 'editar-estacionamiento-item',
    ),
    4 => 
    array (
      'nombre' => 'Listar ítems estacionamiento',
      'slug' => 'listar-estacionamiento-item',
    ),
  ),
  'caja/estacionamiento/jornada' => 
  array (
    0 => 
    array (
      'nombre' => 'Abrir jornada estacionamiento',
      'slug' => 'abrir-jornada-estacionamiento',
    ),
    1 => 
    array (
      'nombre' => 'Cerrar jornada estacionamiento',
      'slug' => 'cerrar-jornada-estacionamiento',
    ),
    2 => 
    array (
      'nombre' => 'Eliminar jornada estacionamiento',
      'slug' => 'eliminar-jornada-estacionamiento',
    ),
    3 => 
    array (
      'nombre' => 'Gestionar jornada estacionamiento',
      'slug' => 'gestionar-jornada-estacionamiento',
    ),
  ),
  'caja/estacionamiento/lista-precio' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar listas de precios estacionamiento',
      'slug' => 'actualizar-estacionamiento-lista-precio',
    ),
    1 => 
    array (
      'nombre' => 'Borrar listas de precios estacionamiento',
      'slug' => 'borrar-estacionamiento-lista-precio',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar listas de precios estacionamiento',
      'slug' => 'crear-estacionamiento-lista-precio',
    ),
    3 => 
    array (
      'nombre' => 'Editar listas de precios estacionamiento',
      'slug' => 'editar-estacionamiento-lista-precio',
    ),
    4 => 
    array (
      'nombre' => 'Listar listas de precios estacionamiento',
      'slug' => 'listar-estacionamiento-lista-precio',
    ),
  ),
  'caja/estacionamiento/saneamiento-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Ejecutar saneamiento turno estacionamiento',
      'slug' => 'ejecutar-saneamiento-turno-estacionamiento',
    ),
    1 => 
    array (
      'nombre' => 'Gestionar saneamiento turno estacionamiento',
      'slug' => 'gestionar-saneamiento-turno-estacionamiento',
    ),
  ),
  'caja/estacionamiento/turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar turnos estacionamiento',
      'slug' => 'actualizar-turno-estacionamiento',
    ),
    1 => 
    array (
      'nombre' => 'Borrar turnos estacionamiento',
      'slug' => 'borrar-turno-estacionamiento',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar turnos estacionamiento',
      'slug' => 'crear-turno-estacionamiento',
    ),
    3 => 
    array (
      'nombre' => 'Editar turnos estacionamiento',
      'slug' => 'editar-turno-estacionamiento',
    ),
    4 => 
    array (
      'nombre' => 'Listar turnos estacionamiento',
      'slug' => 'listar-turno-estacionamiento',
    ),
  ),
  'caja/estadocheque_banco' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar estado cheque banco',
      'slug' => 'actualizar-estado-cheque-banco',
    ),
    1 => 
    array (
      'nombre' => 'Borrar estado cheque banco',
      'slug' => 'borrar-estado-cheque-banco',
    ),
    2 => 
    array (
      'nombre' => 'Crear estado cheque banco',
      'slug' => 'crear-estado-cheque-banco',
    ),
    3 => 
    array (
      'nombre' => 'Editar estado cheque banco',
      'slug' => 'editar-estado-cheque-banco',
    ),
    4 => 
    array (
      'nombre' => 'Listar estado cheque banco',
      'slug' => 'listar-estado-cheque-banco',
    ),
  ),
  'caja/flash/parametro' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar parámetros flash',
      'slug' => 'actualizar-flash-parametro',
    ),
    1 => 
    array (
      'nombre' => 'Borrar parámetros flash',
      'slug' => 'borrar-flash-parametro',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar parámetros flash',
      'slug' => 'crear-flash-parametro',
    ),
    3 => 
    array (
      'nombre' => 'Editar parámetros flash',
      'slug' => 'editar-flash-parametro',
    ),
    4 => 
    array (
      'nombre' => 'Listar parámetros flash',
      'slug' => 'listar-flash-parametro',
    ),
  ),
  'caja/interbanking/movimientos-persistidos' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar movimientos Interbanking persistidos',
      'slug' => 'listar-interbanking-movimientos-persistidos',
    ),
  ),
  'caja/interbanking/transferencias-persistidas' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar transferencias Interbanking persistidas',
      'slug' => 'listar-interbanking-transferencias-persistidas',
    ),
  ),
  'caja/tipotransaccion_caja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo transaccion caja',
      'slug' => 'actualizar-tipo-transaccion-caja',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo transaccion caja',
      'slug' => 'borrar-tipo-transaccion-caja',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo transaccion caja',
      'slug' => 'crear-tipo-transaccion-caja',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo transaccion caja',
      'slug' => 'editar-tipo-transaccion-caja',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipo transaccion caja',
      'slug' => 'listar-tipo-transaccion-caja',
    ),
  ),
  'caja/usocuentacaja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar usos de cuenta de caja',
      'slug' => 'actualizar-usocuentacaja',
    ),
    1 => 
    array (
      'nombre' => 'Borrar usos de cuenta de caja',
      'slug' => 'borrar-usocuentacaja',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar usos de cuenta de caja',
      'slug' => 'crear-usocuentacaja',
    ),
    3 => 
    array (
      'nombre' => 'Editar usos de cuenta de caja',
      'slug' => 'editar-usocuentacaja',
    ),
    4 => 
    array (
      'nombre' => 'Listar usos de cuenta de caja',
      'slug' => 'listar-usocuentacaja',
    ),
  ),
  'compras/columna_ivacompra' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar columna iva compra',
      'slug' => 'actualizar-columna-iva-compra',
    ),
    1 => 
    array (
      'nombre' => 'Borrar columna iva compra',
      'slug' => 'borrar-columna-iva-compra',
    ),
    2 => 
    array (
      'nombre' => 'Crear columna iva compra',
      'slug' => 'crear-columna-iva-compra',
    ),
    3 => 
    array (
      'nombre' => 'Editar columna iva compra',
      'slug' => 'editar-columna-iva-compra',
    ),
    4 => 
    array (
      'nombre' => 'Listar columna iva compra',
      'slug' => 'listar-columna-iva-compra',
    ),
  ),
  'compras/comprobante-proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar comprobante de proveedor',
      'slug' => 'actualizar-comprobante-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Actualizar precarga proveedores',
      'slug' => 'actualizar-precarga-proveedores',
    ),
    2 => 
    array (
      'nombre' => 'Borrar comprobante de proveedor',
      'slug' => 'borrar-comprobante-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Borrar precarga proveedores',
      'slug' => 'borrar-precarga-proveedores',
    ),
    4 => 
    array (
      'nombre' => 'Contabilizar comprobante de proveedor',
      'slug' => 'contabilizar-comprobante-proveedor',
    ),
    5 => 
    array (
      'nombre' => 'Crear comprobante de proveedor',
      'slug' => 'crear-comprobante-proveedor',
    ),
    6 => 
    array (
      'nombre' => 'Ingresar precarga proveedores',
      'slug' => 'crear-precarga-proveedores',
    ),
    7 => 
    array (
      'nombre' => 'Editar comprobante de proveedor',
      'slug' => 'editar-comprobante-proveedor',
    ),
    8 => 
    array (
      'nombre' => 'Editar precarga proveedores',
      'slug' => 'editar-precarga-proveedores',
    ),
    9 => 
    array (
      'nombre' => 'Listar comprobantes de proveedor',
      'slug' => 'listar-comprobante-proveedor',
    ),
    10 => 
    array (
      'nombre' => 'Listar precarga proveedores',
      'slug' => 'listar-precarga-proveedores',
    ),
  ),
  'compras/comprobante-proveedor-imputacion-ap-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar comprobante de proveedor',
      'slug' => 'editar-comprobante-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Editar proveedores',
      'slug' => 'editar-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Listar comprobantes de proveedor',
      'slug' => 'listar-comprobante-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Listar proveedores',
      'slug' => 'listar-proveedor',
    ),
  ),
  'compras/concepto_ivacompra' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar concepto iva compra',
      'slug' => 'actualizar-concepto-iva-compra',
    ),
    1 => 
    array (
      'nombre' => 'Borrar concepto iva compra',
      'slug' => 'borrar-concepto-iva-compra',
    ),
    2 => 
    array (
      'nombre' => 'Crear concepto iva compra',
      'slug' => 'crear-concepto-iva-compra',
    ),
    3 => 
    array (
      'nombre' => 'Editar concepto iva compra',
      'slug' => 'editar-concepto-iva-compra',
    ),
    4 => 
    array (
      'nombre' => 'Listar concepto iva compra',
      'slug' => 'listar-concepto-iva-compra',
    ),
  ),
  'compras/condicioncompra' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar condicion de compra',
      'slug' => 'actualizar-condicion-de-compra',
    ),
    1 => 
    array (
      'nombre' => 'Borrar condicion de compra',
      'slug' => 'borrar-condicion-de-compra',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar condicion de compra',
      'slug' => 'crear-condicion-de-compra',
    ),
    3 => 
    array (
      'nombre' => 'Editar condicion de compra',
      'slug' => 'editar-condicion-de-compra',
    ),
    4 => 
    array (
      'nombre' => 'Listar condicion de compra',
      'slug' => 'listar-condicion-de-compra',
    ),
  ),
  'compras/condicionentrega' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar condicion de entrega',
      'slug' => 'actualizar-condicion-de-entrega',
    ),
    1 => 
    array (
      'nombre' => 'Borrar condicion de entrega',
      'slug' => 'borrar-condicion-de-entrega',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar condicion de entrega',
      'slug' => 'crear-condicion-de-entrega',
    ),
    3 => 
    array (
      'nombre' => 'Editar condicion de entrega',
      'slug' => 'editar-condicion-de-entrega',
    ),
    4 => 
    array (
      'nombre' => 'Listar condicion de entrega',
      'slug' => 'listar-condicion-de-entrega',
    ),
  ),
  'compras/condicionpago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar condicion de pago',
      'slug' => 'actualizar-condicion-de-pago',
    ),
    1 => 
    array (
      'nombre' => 'Borrar condicion de pago',
      'slug' => 'borrar-condicion-de-pago',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar condicion de pago',
      'slug' => 'crear-condicion-de-pago',
    ),
    3 => 
    array (
      'nombre' => 'Editar condicion de pago',
      'slug' => 'editar-condicion-de-pago',
    ),
    4 => 
    array (
      'nombre' => 'Listar condicion de pago',
      'slug' => 'listar-condicion-de-pago',
    ),
  ),
  'compras/configuracion-comprobante-proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración comprobante proveedor',
      'slug' => 'actualizar-configuracion-comprobante-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración comprobante proveedor',
      'slug' => 'editar-configuracion-comprobante-proveedor',
    ),
  ),
  'compras/configuracion-propuesta-pago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuracion propuesta pago',
      'slug' => 'actualizar-configuracion-propuesta-pago',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuracion propuesta pago',
      'slug' => 'editar-configuracion-propuesta-pago',
    ),
  ),
  'compras/cumplir-requisicion-compra' => 
  array (
    0 => 
    array (
      'nombre' => 'Cumplir requisiciones de compra',
      'slug' => 'cumplir-requisicion-compra',
    ),
  ),
  'compras/kpi' => 
  array (
    0 => 
    array (
      'nombre' => 'Tablero KPIs de Compras',
      'slug' => 'listar-kpi-compras',
    ),
  ),
  'compras/legajos/seguimiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Consultar seguimiento de legajos de compras',
      'slug' => 'listar-seguimiento-legajo-compra',
    ),
  ),
  'compras/ordencompra' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ordencompra',
      'slug' => 'actualizar-ordencompra',
    ),
    1 => 
    array (
      'nombre' => 'Borrar ordencompra',
      'slug' => 'borrar-ordencompra',
    ),
    2 => 
    array (
      'nombre' => 'Crear ordencompra',
      'slug' => 'crear-ordencompra',
    ),
    3 => 
    array (
      'nombre' => 'Editar ordencompra',
      'slug' => 'editar-ordencompra',
    ),
    4 => 
    array (
      'nombre' => 'Listar ordencompra',
      'slug' => 'listar-ordencompra',
    ),
  ),
  'compras/ordencompra-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar ordencompra',
      'slug' => 'editar-ordencompra',
    ),
    1 => 
    array (
      'nombre' => 'Listar ordencompra',
      'slug' => 'listar-ordencompra',
    ),
  ),
  'compras/pagoproveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar pago a proveedores',
      'slug' => 'actualizar-pagoproveedor',
    ),
    1 => 
    array (
      'nombre' => 'Anular pagoproveedor',
      'slug' => 'anular-pagoproveedor',
    ),
    2 => 
    array (
      'nombre' => 'Borrar pago a proveedores',
      'slug' => 'borrar-pagoproveedor',
    ),
    3 => 
    array (
      'nombre' => 'Confirmar pago a proveedores',
      'slug' => 'confirmar-pagoproveedor',
    ),
    4 => 
    array (
      'nombre' => 'Crear pago a proveedores',
      'slug' => 'crear-pagoproveedor',
    ),
    5 => 
    array (
      'nombre' => 'Editar pago a proveedores',
      'slug' => 'editar-pagoproveedor',
    ),
    6 => 
    array (
      'nombre' => 'Listar pago a proveedores',
      'slug' => 'listar-pagoproveedor',
    ),
    7 => 
    array (
      'nombre' => 'Revertir pagoproveedor',
      'slug' => 'revertir-pagoproveedor',
    ),
  ),
  'compras/programa-pago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar programa de pagos',
      'slug' => 'actualizar-programa-pago',
    ),
    1 => 
    array (
      'nombre' => 'Borrar programa de pagos',
      'slug' => 'borrar-programa-pago',
    ),
    2 => 
    array (
      'nombre' => 'Crear programa de pagos',
      'slug' => 'crear-programa-pago',
    ),
    3 => 
    array (
      'nombre' => 'Editar programa de pagos',
      'slug' => 'editar-programa-pago',
    ),
    4 => 
    array (
      'nombre' => 'Listar programa de pagos',
      'slug' => 'listar-programa-pago',
    ),
  ),
  'compras/propuesta-pago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar propuesta de pagos',
      'slug' => 'actualizar-propuesta-pago',
    ),
    1 => 
    array (
      'nombre' => 'Borrar propuesta de pagos',
      'slug' => 'borrar-propuesta-pago',
    ),
    2 => 
    array (
      'nombre' => 'Crear propuesta de pagos',
      'slug' => 'crear-propuesta-pago',
    ),
    3 => 
    array (
      'nombre' => 'Editar propuesta de pagos',
      'slug' => 'editar-propuesta-pago',
    ),
    4 => 
    array (
      'nombre' => 'Ejecutar propuesta de pagos',
      'slug' => 'ejecutar-propuesta-pago',
    ),
    5 => 
    array (
      'nombre' => 'Listar propuesta de pagos',
      'slug' => 'listar-propuesta-pago',
    ),
  ),
  'compras/proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar proveedores',
      'slug' => 'actualizar-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Borrar proveedores',
      'slug' => 'borrar-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Crear proveedores',
      'slug' => 'crear-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Editar proveedores',
      'slug' => 'editar-proveedor',
    ),
    4 => 
    array (
      'nombre' => 'Listar proveedores',
      'slug' => 'listar-proveedor',
    ),
  ),
  'compras/proveedor-cuentacorriente-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar proveedores',
      'slug' => 'editar-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Listar proveedores',
      'slug' => 'listar-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Listar reporte deuda / CC proveedores',
      'slug' => 'listar-proveedor-cuentacorriente-reporte',
    ),
  ),
  'compras/requisicion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar requisicion',
      'slug' => 'actualizar-requisicion',
    ),
    1 => 
    array (
      'nombre' => 'Borrar requisicion',
      'slug' => 'borrar-requisicion',
    ),
    2 => 
    array (
      'nombre' => 'Confirmar requisicion',
      'slug' => 'confirmar-requisicion',
    ),
    3 => 
    array (
      'nombre' => 'Crear requisicion',
      'slug' => 'crear-requisicion',
    ),
    4 => 
    array (
      'nombre' => 'Editar requisicion',
      'slug' => 'editar-requisicion',
    ),
    5 => 
    array (
      'nombre' => 'Listar requisicion',
      'slug' => 'listar-requisicion',
    ),
  ),
  'compras/requisicion-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar requisicion',
      'slug' => 'editar-requisicion',
    ),
    1 => 
    array (
      'nombre' => 'Listar requisicion',
      'slug' => 'listar-requisicion',
    ),
  ),
  'compras/retencionIIBB' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ingreso bruto',
      'slug' => 'actualizar-retencion-de-IIBB',
    ),
    1 => 
    array (
      'nombre' => 'Borrar retencion de IIBB',
      'slug' => 'borrar-retencion-de-IIBB',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar retencion de IIBB',
      'slug' => 'crear-retencion-de-IIBB',
    ),
    3 => 
    array (
      'nombre' => 'Editar retencion de IIBB',
      'slug' => 'editar-retencion-de-IIBB',
    ),
    4 => 
    array (
      'nombre' => 'Listar retencion de IIBB',
      'slug' => 'listar-retencion-de-IIBB',
    ),
  ),
  'compras/retencionganancia' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar retencion de ganancias',
      'slug' => 'actualizar-retencion-de-ganancias',
    ),
    1 => 
    array (
      'nombre' => 'Borrar retencion de ganancias',
      'slug' => 'borrar-retencion-de-ganancias',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar retencion de ganancias',
      'slug' => 'crear-retencion-de-ganancias',
    ),
    3 => 
    array (
      'nombre' => 'Editar retencion de ganancias',
      'slug' => 'editar-retencion-de-ganancias',
    ),
    4 => 
    array (
      'nombre' => 'Listar retencion de ganancias',
      'slug' => 'listar-retencion-de-ganancias',
    ),
  ),
  'compras/retencionsuss' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar retencion de suss',
      'slug' => 'actualizar-retencion-de-suss',
    ),
    1 => 
    array (
      'nombre' => 'Borrar retencion de suss',
      'slug' => 'borrar-retencion-de-suss',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar retencion de suss',
      'slug' => 'crear-retencion-de-suss',
    ),
    3 => 
    array (
      'nombre' => 'Editar retencion de suss',
      'slug' => 'editar-retencion-de-suss',
    ),
    4 => 
    array (
      'nombre' => 'Listar retencion de suss',
      'slug' => 'listar-retencion-de-suss',
    ),
  ),
  'compras/suscripciones' => 
  array (
    0 => 
    array (
      'nombre' => 'Crear / editar suscripciones',
      'slug' => 'crear-suscripcion',
    ),
    1 => 
    array (
      'nombre' => 'Listar suscripciones (OC abiertas)',
      'slug' => 'listar-suscripcion',
    ),
  ),
  'compras/suscripciones/comprobantes-pendientes' => 
  array (
    0 => 
    array (
      'nombre' => 'Conciliar el resumen de tarjeta corporativa',
      'slug' => 'conciliar-suscripcion',
    ),
    1 => 
    array (
      'nombre' => 'Listar suscripciones (OC abiertas)',
      'slug' => 'listar-suscripcion',
    ),
  ),
  'compras/suscripciones/conciliacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Conciliar el resumen de tarjeta corporativa',
      'slug' => 'conciliar-suscripcion',
    ),
  ),
  'compras/tiposuspensionproveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipos suspension proveedor',
      'slug' => 'actualizar-tipos-suspension-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipos suspension proveedor',
      'slug' => 'borrar-tipos-suspension-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar tipos suspension proveedor',
      'slug' => 'crear-tipos-suspension-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipos suspension proveedor',
      'slug' => 'editar-tipos-suspension-proveedor',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos suspension proveedor',
      'slug' => 'listar-tipos-suspension-proveedor',
    ),
  ),
  'compras/tipotransaccion_compra' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo transaccion compra',
      'slug' => 'actualizar-tipo-transaccion-compra',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo transaccion compra',
      'slug' => 'borrar-tipo-transaccion-compra',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo transaccion compra',
      'slug' => 'crear-tipo-transaccion-compra',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo transaccion compra',
      'slug' => 'editar-tipo-transaccion-compra',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipo transaccion compra',
      'slug' => 'listar-tipo-transaccion-compra',
    ),
  ),
  'compras/tracking-facturas' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar el tracking de facturas',
      'slug' => 'listar-tracking-facturas',
    ),
  ),
  'configuracion/actividad_arca' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar actividad arca',
      'slug' => 'actualizar-actividad-arca',
    ),
    1 => 
    array (
      'nombre' => 'Borrar actividad arca',
      'slug' => 'borrar-actividad-arca',
    ),
    2 => 
    array (
      'nombre' => 'Crear actividad arca',
      'slug' => 'crear-actividad-arca',
    ),
    3 => 
    array (
      'nombre' => 'Editar actividad arca',
      'slug' => 'editar-actividad-arca',
    ),
    4 => 
    array (
      'nombre' => 'Listar actividad arca',
      'slug' => 'listar-actividad-arca',
    ),
  ),
  'configuracion/ai-decisiones' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar gobernanza IA (decisiones)',
      'slug' => 'listar-ai-decisiones',
    ),
  ),
  'configuracion/auditoria-sesiones' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar auditoría de sesiones y logs',
      'slug' => 'listar-auditoria-sesiones',
    ),
  ),
  'configuracion/condicioniva' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar condiciones de iva',
      'slug' => 'actualizar-condiciones-de-iva',
    ),
    1 => 
    array (
      'nombre' => 'Borrar condiciones de iva',
      'slug' => 'borrar-condiciones-de-iva',
    ),
    2 => 
    array (
      'nombre' => 'Crear condiciones de iva',
      'slug' => 'crear-condiciones-de-iva',
    ),
    3 => 
    array (
      'nombre' => 'Editar condiciones de iva',
      'slug' => 'editar-condiciones-de-iva',
    ),
    4 => 
    array (
      'nombre' => 'Listar condiciones de iva',
      'slug' => 'listar-condiciones-de-iva',
    ),
  ),
  'configuracion/empresa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar empresas',
      'slug' => 'actualizar-empresas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar empresas',
      'slug' => 'borrar-empresas',
    ),
    2 => 
    array (
      'nombre' => 'Crear empresas',
      'slug' => 'crear-empresas',
    ),
    3 => 
    array (
      'nombre' => 'Editar empresas',
      'slug' => 'editar-empresas',
    ),
    4 => 
    array (
      'nombre' => 'Listar empresas',
      'slug' => 'listar-empresas',
    ),
  ),
  'configuracion/feriado' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar feriado',
      'slug' => 'actualizar-feriado',
    ),
    1 => 
    array (
      'nombre' => 'Borrar feriado',
      'slug' => 'borrar-feriado',
    ),
    2 => 
    array (
      'nombre' => 'Crear feriado',
      'slug' => 'crear-feriado',
    ),
    3 => 
    array (
      'nombre' => 'Editar feriado',
      'slug' => 'editar-feriado',
    ),
    4 => 
    array (
      'nombre' => 'Importar feriados',
      'slug' => 'importar-feriado',
    ),
    5 => 
    array (
      'nombre' => 'Listar feriados',
      'slug' => 'listar-feriado',
    ),
  ),
  'configuracion/general' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración general del sistema',
      'slug' => 'actualizar-configuracion-general',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración general del sistema',
      'slug' => 'editar-configuracion-general',
    ),
  ),
  'configuracion/impuesto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar impuestos',
      'slug' => 'actualizar-impuestos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar impuestos',
      'slug' => 'borrar-impuestos',
    ),
    2 => 
    array (
      'nombre' => 'Crear impuestos',
      'slug' => 'crear-impuestos',
    ),
    3 => 
    array (
      'nombre' => 'Editar impuestos',
      'slug' => 'editar-impuestos',
    ),
    4 => 
    array (
      'nombre' => 'Listar impuestos',
      'slug' => 'listar-impuestos',
    ),
  ),
  'configuracion/localidad' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar localidades',
      'slug' => 'actualizar-localidades',
    ),
    1 => 
    array (
      'nombre' => 'Borrar localidades',
      'slug' => 'borrar-localidades',
    ),
    2 => 
    array (
      'nombre' => 'Crear localidades',
      'slug' => 'crear-localidades',
    ),
    3 => 
    array (
      'nombre' => 'Editar localidades',
      'slug' => 'editar-localidades',
    ),
    4 => 
    array (
      'nombre' => 'Listar localidades',
      'slug' => 'listar-localidades',
    ),
  ),
  'configuracion/modeloetiqueta' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar modelos de etiquetas',
      'slug' => 'actualizar-modeloetiqueta',
    ),
    1 => 
    array (
      'nombre' => 'Borrar modelos de etiquetas',
      'slug' => 'borrar-modeloetiqueta',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar modelos de etiquetas',
      'slug' => 'crear-modeloetiqueta',
    ),
    3 => 
    array (
      'nombre' => 'Editar modelos de etiquetas',
      'slug' => 'editar-modeloetiqueta',
    ),
    4 => 
    array (
      'nombre' => 'Listar modelos de etiquetas',
      'slug' => 'listar-modeloetiqueta',
    ),
  ),
  'configuracion/modulo-aviso' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración de avisos por módulo',
      'slug' => 'actualizar-modulo-aviso',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración de avisos por módulo',
      'slug' => 'editar-modulo-aviso',
    ),
    2 => 
    array (
      'nombre' => 'Listar tipos de aviso por módulo',
      'slug' => 'listar-modulo-aviso',
    ),
  ),
  'configuracion/moneda' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar monedas',
      'slug' => 'actualizar-monedas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar monedas',
      'slug' => 'borrar-monedas',
    ),
    2 => 
    array (
      'nombre' => 'Crear monedas',
      'slug' => 'crear-monedas',
    ),
    3 => 
    array (
      'nombre' => 'Editar monedas',
      'slug' => 'editar-monedas',
    ),
    4 => 
    array (
      'nombre' => 'Listar monedas',
      'slug' => 'listar-monedas',
    ),
  ),
  'configuracion/pais' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar paises',
      'slug' => 'actualizar-paises',
    ),
    1 => 
    array (
      'nombre' => 'Borrar paises',
      'slug' => 'borrar-paises',
    ),
    2 => 
    array (
      'nombre' => 'Crear paises',
      'slug' => 'crear-paises',
    ),
    3 => 
    array (
      'nombre' => 'Editar paises',
      'slug' => 'editar-paises',
    ),
    4 => 
    array (
      'nombre' => 'Listar paises',
      'slug' => 'listar-paises',
    ),
  ),
  'configuracion/provincia' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar provincias',
      'slug' => 'actualizar-provincias',
    ),
    1 => 
    array (
      'nombre' => 'Borrar provincias',
      'slug' => 'borrar-provincias',
    ),
    2 => 
    array (
      'nombre' => 'Crear provincias',
      'slug' => 'crear-provincias',
    ),
    3 => 
    array (
      'nombre' => 'Editar provincias',
      'slug' => 'editar-provincias',
    ),
    4 => 
    array (
      'nombre' => 'Listar provincias',
      'slug' => 'listar-provincias',
    ),
  ),
  'configuracion/recepcion-proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración recepción proveedores',
      'slug' => 'actualizar-configuracion-recepcion-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración recepción proveedores',
      'slug' => 'editar-configuracion-recepcion-proveedor',
    ),
  ),
  'configuracion/reemplazo-firmante-arbol' => 
  array (
    0 => 
    array (
      'nombre' => 'Ejecutar reemplazo firmante árbol',
      'slug' => 'ejecutar-reemplazo-firmante-arbol',
    ),
    1 => 
    array (
      'nombre' => 'Listar reemplazo firmante árbol',
      'slug' => 'listar-reemplazo-firmante-arbol',
    ),
  ),
  'configuracion/salida' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar salidas',
      'slug' => 'actualizar-salidas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar salidas',
      'slug' => 'borrar-salidas',
    ),
    2 => 
    array (
      'nombre' => 'Crear salidas',
      'slug' => 'crear-salidas',
    ),
    3 => 
    array (
      'nombre' => 'Editar salidas',
      'slug' => 'editar-salidas',
    ),
    4 => 
    array (
      'nombre' => 'Listar salidas',
      'slug' => 'listar-salidas',
    ),
  ),
  'configuracion/sistema-numerador' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar numeradores del sistema',
      'slug' => 'actualizar-sistema-numerador',
    ),
    1 => 
    array (
      'nombre' => 'Borrar numeradores del sistema',
      'slug' => 'borrar-sistema-numerador',
    ),
    2 => 
    array (
      'nombre' => 'Crear numeradores del sistema',
      'slug' => 'crear-sistema-numerador',
    ),
    3 => 
    array (
      'nombre' => 'Editar numeradores del sistema',
      'slug' => 'editar-sistema-numerador',
    ),
    4 => 
    array (
      'nombre' => 'Listar numeradores del sistema',
      'slug' => 'listar-sistema-numerador',
    ),
  ),
  'configuracion/ubicacion-impresora' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ubicaciones de impresora',
      'slug' => 'actualizar-ubicacion-impresora',
    ),
    1 => 
    array (
      'nombre' => 'Borrar ubicaciones de impresora',
      'slug' => 'borrar-ubicacion-impresora',
    ),
    2 => 
    array (
      'nombre' => 'Crear ubicaciones de impresora',
      'slug' => 'crear-ubicacion-impresora',
    ),
    3 => 
    array (
      'nombre' => 'Editar ubicaciones de impresora',
      'slug' => 'editar-ubicacion-impresora',
    ),
    4 => 
    array (
      'nombre' => 'Listar ubicaciones de impresora',
      'slug' => 'listar-ubicacion-impresora',
    ),
  ),
  'configuracion/uso-salida-impresora' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar usos de salida de impresión',
      'slug' => 'actualizar-uso-salida-impresora',
    ),
    1 => 
    array (
      'nombre' => 'Borrar usos de salida de impresión',
      'slug' => 'borrar-uso-salida-impresora',
    ),
    2 => 
    array (
      'nombre' => 'Crear usos de salida de impresión',
      'slug' => 'crear-uso-salida-impresora',
    ),
    3 => 
    array (
      'nombre' => 'Editar usos de salida de impresión',
      'slug' => 'editar-uso-salida-impresora',
    ),
    4 => 
    array (
      'nombre' => 'Listar usos de salida de impresión',
      'slug' => 'listar-uso-salida-impresora',
    ),
  ),
  'contable/ajuste-inflacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Confirmar ajuste y generar asiento AJ',
      'slug' => 'confirmar-ajuste-inflacion',
    ),
    1 => 
    array (
      'nombre' => 'Consultar ajuste por inflación',
      'slug' => 'listar-ajuste-inflacion',
    ),
  ),
  'contable/apertura-periodo' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar aperturas de período contable',
      'slug' => 'listar-apertura-periodo-contable',
    ),
  ),
  'contable/asiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar asientos',
      'slug' => 'actualizar-asiento',
    ),
    1 => 
    array (
      'nombre' => 'Borrar asientos',
      'slug' => 'borrar-asiento',
    ),
    2 => 
    array (
      'nombre' => 'Crear asientos',
      'slug' => 'crear-asiento',
    ),
    3 => 
    array (
      'nombre' => 'Editar asientos',
      'slug' => 'editar-asiento',
    ),
    4 => 
    array (
      'nombre' => 'Listar asientos',
      'slug' => 'listar-asiento',
    ),
  ),
  'contable/bien-uso' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar bienes de uso',
      'slug' => 'actualizar-bien-uso',
    ),
    1 => 
    array (
      'nombre' => 'Borrar bienes de uso',
      'slug' => 'borrar-bien-uso',
    ),
    2 => 
    array (
      'nombre' => 'Crear bienes de uso',
      'slug' => 'crear-bien-uso',
    ),
    3 => 
    array (
      'nombre' => 'Editar bienes de uso',
      'slug' => 'editar-bien-uso',
    ),
    4 => 
    array (
      'nombre' => 'Listar bienes de uso',
      'slug' => 'listar-bien-uso',
    ),
  ),
  'contable/canon-municipal-config' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración canon municipal',
      'slug' => 'actualizar-canon-municipal-config',
    ),
    1 => 
    array (
      'nombre' => 'Crear configuración canon municipal',
      'slug' => 'crear-canon-municipal-config',
    ),
    2 => 
    array (
      'nombre' => 'Editar configuración canon municipal',
      'slug' => 'editar-canon-municipal-config',
    ),
    3 => 
    array (
      'nombre' => 'Eliminar configuración canon municipal',
      'slug' => 'eliminar-canon-municipal-config',
    ),
    4 => 
    array (
      'nombre' => 'Listar configuración canon municipal',
      'slug' => 'listar-canon-municipal-config',
    ),
  ),
  'contable/centrocosto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar centros de costo',
      'slug' => 'actualizar-centro-costo',
    ),
    1 => 
    array (
      'nombre' => 'Borrar centros de costo',
      'slug' => 'borrar-centro-costo',
    ),
    2 => 
    array (
      'nombre' => 'Crear centros de costo',
      'slug' => 'crear-centro-costo',
    ),
    3 => 
    array (
      'nombre' => 'Editar centros de costo',
      'slug' => 'editar-centro-costo',
    ),
    4 => 
    array (
      'nombre' => 'Listar centros de costo',
      'slug' => 'listar-centro-costo',
    ),
  ),
  'contable/cierre-periodo' => 
  array (
    0 => 
    array (
      'nombre' => 'Ejecutar cierre de período contable',
      'slug' => 'ejecutar-cierre-periodo-contable',
    ),
    1 => 
    array (
      'nombre' => 'Listar cierres de período contable',
      'slug' => 'listar-cierre-periodo-contable',
    ),
  ),
  'contable/conciliacion-bancaria' => 
  array (
    0 => 
    array (
      'nombre' => 'Ejecutar conciliación bancaria',
      'slug' => 'ejecutar-conciliacion-bancaria',
    ),
    1 => 
    array (
      'nombre' => 'Exportar conciliación bancaria',
      'slug' => 'exportar-conciliacion-bancaria',
    ),
  ),
  'contable/configuracion-asiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración de aprobación de asientos',
      'slug' => 'actualizar-configuracion-asiento-contable',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración de aprobación de asientos',
      'slug' => 'editar-configuracion-asiento-contable',
    ),
  ),
  'contable/control-retencion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar retención impositiva ARCA',
      'slug' => 'actualizar-retencion-impositiva-arca',
    ),
    1 => 
    array (
      'nombre' => 'Borrar retención impositiva ARCA',
      'slug' => 'borrar-retencion-impositiva-arca',
    ),
    2 => 
    array (
      'nombre' => 'Conciliar retención impositiva ARCA',
      'slug' => 'conciliar-retencion-impositiva-arca',
    ),
    3 => 
    array (
      'nombre' => 'Crear retención impositiva ARCA',
      'slug' => 'crear-retencion-impositiva-arca',
    ),
    4 => 
    array (
      'nombre' => 'Editar retención impositiva ARCA',
      'slug' => 'editar-retencion-impositiva-arca',
    ),
    5 => 
    array (
      'nombre' => 'Importar retención impositiva ARCA',
      'slug' => 'importar-retencion-impositiva-arca',
    ),
    6 => 
    array (
      'nombre' => 'Listar retención impositiva ARCA',
      'slug' => 'listar-retencion-impositiva-arca',
    ),
  ),
  'contable/cuentas-automaticas' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cuentas automáticas del sistema',
      'slug' => 'actualizar-cuentas-automaticas-contables',
    ),
    1 => 
    array (
      'nombre' => 'Editar cuentas automáticas del sistema',
      'slug' => 'editar-cuentas-automaticas-contables',
    ),
  ),
  'contable/efe-mensual' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar efe mensual',
      'slug' => 'listar-efe-mensual',
    ),
  ),
  'contable/ingresos-brutos' => 
  array (
    0 => 
    array (
      'nombre' => 'Exportar Ingresos Brutos',
      'slug' => 'exportar-ingresos-brutos',
    ),
    1 => 
    array (
      'nombre' => 'Listar Ingresos Brutos',
      'slug' => 'listar-ingresos-brutos',
    ),
  ),
  'contable/ingresos-brutos-config' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración IIBB',
      'slug' => 'actualizar-ingresos-brutos-config',
    ),
    1 => 
    array (
      'nombre' => 'Crear configuración IIBB',
      'slug' => 'crear-ingresos-brutos-config',
    ),
    2 => 
    array (
      'nombre' => 'Editar configuración IIBB',
      'slug' => 'editar-ingresos-brutos-config',
    ),
    3 => 
    array (
      'nombre' => 'Eliminar configuración IIBB',
      'slug' => 'eliminar-ingresos-brutos-config',
    ),
    4 => 
    array (
      'nombre' => 'Listar configuración IIBB',
      'slug' => 'listar-ingresos-brutos-config',
    ),
  ),
  'contable/libro-iva-digital' => 
  array (
    0 => 
    array (
      'nombre' => 'Exportar Libro IVA Digital',
      'slug' => 'exportar-libro-iva-digital',
    ),
    1 => 
    array (
      'nombre' => 'Listar Libro IVA Digital',
      'slug' => 'listar-libro-iva-digital',
    ),
  ),
  'contable/mayor-concepto' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar mayor concepto',
      'slug' => 'listar-mayor-concepto',
    ),
  ),
  'contable/mayor-plano-cuenta' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar mayor plano por cuenta contable',
      'slug' => 'listar-mayor-plano-cuenta',
    ),
  ),
  'contable/reporte-definible' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar reporte contable definible',
      'slug' => 'actualizar-reporte-definible',
    ),
    1 => 
    array (
      'nombre' => 'Crear reporte contable definible',
      'slug' => 'crear-reporte-definible',
    ),
    2 => 
    array (
      'nombre' => 'Editar reporte contable definible',
      'slug' => 'editar-reporte-definible',
    ),
    3 => 
    array (
      'nombre' => 'Ejecutar reporte contable definible',
      'slug' => 'ejecutar-reporte-definible',
    ),
    4 => 
    array (
      'nombre' => 'Eliminar reporte contable definible',
      'slug' => 'eliminar-reporte-definible',
    ),
    5 => 
    array (
      'nombre' => 'Importar reportes definibles desde Anita',
      'slug' => 'importar-reporte-definible',
    ),
    6 => 
    array (
      'nombre' => 'Listar reportes contables definibles',
      'slug' => 'listar-reporte-definible',
    ),
  ),
  'contable/rubrocontable' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar rubros contables',
      'slug' => 'actualizar-rubros-contables',
    ),
    1 => 
    array (
      'nombre' => 'Borrar rubros contables',
      'slug' => 'borrar-rubros-contables',
    ),
    2 => 
    array (
      'nombre' => 'Crear rubros contables',
      'slug' => 'crear-rubros-contables',
    ),
    3 => 
    array (
      'nombre' => 'Editar rubros contables',
      'slug' => 'editar-rubros-contables',
    ),
    4 => 
    array (
      'nombre' => 'Listar rubros contables',
      'slug' => 'listar-rubros-contables',
    ),
  ),
  'contable/sicore' => 
  array (
    0 => 
    array (
      'nombre' => 'Exportar archivo SICORE',
      'slug' => 'exportar-sicore',
    ),
    1 => 
    array (
      'nombre' => 'Listar proceso SICORE',
      'slug' => 'listar-sicore',
    ),
  ),
  'contable/sicore-config' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar sicore config',
      'slug' => 'actualizar-sicore-config',
    ),
    1 => 
    array (
      'nombre' => 'Crear sicore config',
      'slug' => 'crear-sicore-config',
    ),
    2 => 
    array (
      'nombre' => 'Editar sicore config',
      'slug' => 'editar-sicore-config',
    ),
    3 => 
    array (
      'nombre' => 'Eliminar sicore config',
      'slug' => 'eliminar-sicore-config',
    ),
    4 => 
    array (
      'nombre' => 'Listar sicore config',
      'slug' => 'listar-sicore-config',
    ),
  ),
  'contable/sumas-saldos' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar balance de sumas y saldos',
      'slug' => 'listar-sumas-saldos',
    ),
  ),
  'contable/suss' => 
  array (
    0 => 
    array (
      'nombre' => 'Exportar SUSS',
      'slug' => 'exportar-suss',
    ),
    1 => 
    array (
      'nombre' => 'Listar SUSS',
      'slug' => 'listar-suss',
    ),
  ),
  'contable/suss-config' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración SUSS',
      'slug' => 'actualizar-suss-config',
    ),
    1 => 
    array (
      'nombre' => 'Crear configuración SUSS',
      'slug' => 'crear-suss-config',
    ),
    2 => 
    array (
      'nombre' => 'Editar configuración SUSS',
      'slug' => 'editar-suss-config',
    ),
    3 => 
    array (
      'nombre' => 'Eliminar configuración SUSS',
      'slug' => 'eliminar-suss-config',
    ),
    4 => 
    array (
      'nombre' => 'Listar configuración SUSS',
      'slug' => 'listar-suss-config',
    ),
  ),
  'contable/tipoasiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipos de asiento',
      'slug' => 'actualizar-tipo-asiento',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipos de asiento',
      'slug' => 'borrar-tipo-asiento',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipos de asiento',
      'slug' => 'crear-tipo-asiento',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipos de asiento',
      'slug' => 'editar-tipo-asiento',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos de asiento',
      'slug' => 'listar-tipo-asiento',
    ),
  ),
  'contable/usuario_cuentacontable' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar usuario cuenta contable',
      'slug' => 'actualizar-usuario-cuentacontable',
    ),
    1 => 
    array (
      'nombre' => 'Borrar usuario cuenta contable',
      'slug' => 'borrar-usuario-cuentacontable',
    ),
    2 => 
    array (
      'nombre' => 'Crear usuario cuenta contable',
      'slug' => 'crear-usuario-cuentacontable',
    ),
    3 => 
    array (
      'nombre' => 'Editar usuario cuenta contable',
      'slug' => 'editar-usuario-cuentacontable',
    ),
    4 => 
    array (
      'nombre' => 'Listar usuario cuenta contable',
      'slug' => 'listar-usuario-cuentacontable',
    ),
  ),
  'presupuesto/capex' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar Capex',
      'slug' => 'actualizar-capex',
    ),
    1 => 
    array (
      'nombre' => 'Borrar Capex',
      'slug' => 'borrar-capex',
    ),
    2 => 
    array (
      'nombre' => 'Crear Capex',
      'slug' => 'crear-capex',
    ),
    3 => 
    array (
      'nombre' => 'Editar Capex',
      'slug' => 'editar-capex',
    ),
    4 => 
    array (
      'nombre' => 'Editar presupuestos',
      'slug' => 'editar-presupuesto',
    ),
    5 => 
    array (
      'nombre' => 'Listar Capex',
      'slug' => 'listar-capex',
    ),
    6 => 
    array (
      'nombre' => 'Listar presupuestos',
      'slug' => 'listar-presupuesto',
    ),
  ),
  'presupuesto/capex-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar Capex',
      'slug' => 'editar-capex',
    ),
    1 => 
    array (
      'nombre' => 'Editar presupuestos',
      'slug' => 'editar-presupuesto',
    ),
    2 => 
    array (
      'nombre' => 'Listar Capex',
      'slug' => 'listar-capex',
    ),
    3 => 
    array (
      'nombre' => 'Listar reporte CAPEX',
      'slug' => 'listar-capex-reporte',
    ),
    4 => 
    array (
      'nombre' => 'Listar presupuestos',
      'slug' => 'listar-presupuesto',
    ),
  ),
  'presupuesto/generaasiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar presupuestos',
      'slug' => 'editar-presupuesto',
    ),
    1 => 
    array (
      'nombre' => 'Listar presupuestos',
      'slug' => 'listar-presupuesto',
    ),
  ),
  'presupuesto/partidagasto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar partidas de gasto',
      'slug' => 'actualizar-partidagasto',
    ),
    1 => 
    array (
      'nombre' => 'Borrar partidas de gasto',
      'slug' => 'borrar-partidagasto',
    ),
    2 => 
    array (
      'nombre' => 'Crear partidas de gasto',
      'slug' => 'crear-partidagasto',
    ),
    3 => 
    array (
      'nombre' => 'Editar partidas de gasto',
      'slug' => 'editar-partidagasto',
    ),
    4 => 
    array (
      'nombre' => 'Editar presupuestos',
      'slug' => 'editar-presupuesto',
    ),
    5 => 
    array (
      'nombre' => 'Listar partidas de gasto',
      'slug' => 'listar-partidagasto',
    ),
    6 => 
    array (
      'nombre' => 'Listar presupuestos',
      'slug' => 'listar-presupuesto',
    ),
  ),
  'presupuesto/presupuesto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar presupuestos',
      'slug' => 'actualizar-presupuesto',
    ),
    1 => 
    array (
      'nombre' => 'Borrar presupuestos',
      'slug' => 'borrar-presupuesto',
    ),
    2 => 
    array (
      'nombre' => 'Crear presupuestos',
      'slug' => 'crear-presupuesto',
    ),
    3 => 
    array (
      'nombre' => 'Editar presupuestos',
      'slug' => 'editar-presupuesto',
    ),
    4 => 
    array (
      'nombre' => 'Listar presupuestos',
      'slug' => 'listar-presupuesto',
    ),
  ),
  'produccion/empleado' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar empleados',
      'slug' => 'actualizar-empleados',
    ),
    1 => 
    array (
      'nombre' => 'Borrar empleados',
      'slug' => 'borrar-empleados',
    ),
    2 => 
    array (
      'nombre' => 'Crear empleados',
      'slug' => 'crear-empleados',
    ),
    3 => 
    array (
      'nombre' => 'Editar empleados',
      'slug' => 'editar-empleados',
    ),
    4 => 
    array (
      'nombre' => 'Listar empleados',
      'slug' => 'listar-empleados',
    ),
  ),
  'produccion/operacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar operaciones',
      'slug' => 'actualizar-operaciones',
    ),
    1 => 
    array (
      'nombre' => 'Borrar operaciones',
      'slug' => 'borrar-operaciones',
    ),
    2 => 
    array (
      'nombre' => 'Crear operaciones',
      'slug' => 'crear-operaciones',
    ),
    3 => 
    array (
      'nombre' => 'Editar operaciones',
      'slug' => 'editar-operaciones',
    ),
    4 => 
    array (
      'nombre' => 'Listar operaciones',
      'slug' => 'listar-operaciones',
    ),
  ),
  'produccion/tarea' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tareas',
      'slug' => 'actualizar-tareas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tareas',
      'slug' => 'borrar-tareas',
    ),
    2 => 
    array (
      'nombre' => 'Crear tareas',
      'slug' => 'crear-tareas',
    ),
    3 => 
    array (
      'nombre' => 'Editar tareas',
      'slug' => 'editar-tareas',
    ),
    4 => 
    array (
      'nombre' => 'Listar tareas',
      'slug' => 'listar-tareas',
    ),
  ),
  'seguridad/ingreso-proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ingreso de proveedor',
      'slug' => 'actualizar-ingreso-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Autorizar / registrar ingreso-egreso',
      'slug' => 'autorizar-ingreso-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Borrar ingreso de proveedor',
      'slug' => 'borrar-ingreso-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Crear ingreso de proveedor',
      'slug' => 'crear-ingreso-proveedor',
    ),
    4 => 
    array (
      'nombre' => 'Editar ingreso de proveedor',
      'slug' => 'editar-ingreso-proveedor',
    ),
    5 => 
    array (
      'nombre' => 'Listar ingreso de proveedor',
      'slug' => 'listar-ingreso-proveedor',
    ),
  ),
  'solicitudpago/concepto_solicitudpago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar concepto solicitud de pago',
      'slug' => 'actualizar-concepto-solicitud-pago',
    ),
    1 => 
    array (
      'nombre' => 'Actualizar solicitud de pago',
      'slug' => 'actualizar-solicitud-pago',
    ),
    2 => 
    array (
      'nombre' => 'Borrar concepto solicitud de pago',
      'slug' => 'borrar-concepto-solicitud-pago',
    ),
    3 => 
    array (
      'nombre' => 'Crear concepto solicitud de pago',
      'slug' => 'crear-concepto-solicitud-pago',
    ),
    4 => 
    array (
      'nombre' => 'Crear solicitud de pago',
      'slug' => 'crear-solicitud-pago',
    ),
    5 => 
    array (
      'nombre' => 'Editar concepto solicitud de pago',
      'slug' => 'editar-concepto-solicitud-pago',
    ),
    6 => 
    array (
      'nombre' => 'Editar solicitud de pago',
      'slug' => 'editar-solicitud-pago',
    ),
    7 => 
    array (
      'nombre' => 'Listar concepto solicitud de pago',
      'slug' => 'listar-concepto-solicitud-pago',
    ),
    8 => 
    array (
      'nombre' => 'Listar solicitud de pago',
      'slug' => 'listar-solicitud-pago',
    ),
  ),
  'solicitudpago/formapagosol' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar forma de pago solicitud',
      'slug' => 'actualizar-forma-pago-solicitud',
    ),
    1 => 
    array (
      'nombre' => 'Borrar forma de pago solicitud',
      'slug' => 'borrar-forma-pago-solicitud',
    ),
    2 => 
    array (
      'nombre' => 'Crear forma de pago solicitud',
      'slug' => 'crear-forma-pago-solicitud',
    ),
    3 => 
    array (
      'nombre' => 'Editar forma de pago solicitud',
      'slug' => 'editar-forma-pago-solicitud',
    ),
    4 => 
    array (
      'nombre' => 'Listar forma de pago solicitud',
      'slug' => 'listar-forma-pago-solicitud',
    ),
  ),
  'solicitudpago/informe-solicitudpago' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar informe solicitud de pago',
      'slug' => 'listar-informe-solicitudpago',
    ),
  ),
  'solicitudpago/sector_solicitudpago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar sector solicitud de pago',
      'slug' => 'actualizar-sector-solicitud-pago',
    ),
    1 => 
    array (
      'nombre' => 'Borrar sector solicitud de pago',
      'slug' => 'borrar-sector-solicitud-pago',
    ),
    2 => 
    array (
      'nombre' => 'Crear sector solicitud de pago',
      'slug' => 'crear-sector-solicitud-pago',
    ),
    3 => 
    array (
      'nombre' => 'Editar sector solicitud de pago',
      'slug' => 'editar-sector-solicitud-pago',
    ),
    4 => 
    array (
      'nombre' => 'Listar sector solicitud de pago',
      'slug' => 'listar-sector-solicitud-pago',
    ),
  ),
  'solicitudpago/solicitudpago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar solicitud de pago',
      'slug' => 'actualizar-solicitud-pago',
    ),
    1 => 
    array (
      'nombre' => 'Anular pago solicitud pago',
      'slug' => 'anular-pago-solicitud-pago',
    ),
    2 => 
    array (
      'nombre' => 'Borrar solicitud de pago',
      'slug' => 'borrar-solicitud-pago',
    ),
    3 => 
    array (
      'nombre' => 'Crear solicitud de pago',
      'slug' => 'crear-solicitud-pago',
    ),
    4 => 
    array (
      'nombre' => 'Editar solicitud de pago',
      'slug' => 'editar-solicitud-pago',
    ),
    5 => 
    array (
      'nombre' => 'Listar solicitud de pago',
      'slug' => 'listar-solicitud-pago',
    ),
    6 => 
    array (
      'nombre' => 'Revertir pago solicitud pago',
      'slug' => 'revertir-pago-solicitud-pago',
    ),
  ),
  'stock/caja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cajas',
      'slug' => 'actualizar-cajas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar cajas',
      'slug' => 'borrar-cajas',
    ),
    2 => 
    array (
      'nombre' => 'Crear cajas',
      'slug' => 'crear-cajas',
    ),
    3 => 
    array (
      'nombre' => 'Editar cajas',
      'slug' => 'editar-cajas',
    ),
    4 => 
    array (
      'nombre' => 'Listar cajas',
      'slug' => 'listar-cajas',
    ),
  ),
  'stock/categoria' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar categorias',
      'slug' => 'actualizar-categorias',
    ),
    1 => 
    array (
      'nombre' => 'Borrar categorias',
      'slug' => 'borrar-categorias',
    ),
    2 => 
    array (
      'nombre' => 'Crear categorias',
      'slug' => 'crear-categorias',
    ),
    3 => 
    array (
      'nombre' => 'Editar categorias',
      'slug' => 'editar-categorias',
    ),
    4 => 
    array (
      'nombre' => 'Listar categorias',
      'slug' => 'listar-categorias',
    ),
  ),
  'stock/color' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar colores',
      'slug' => 'actualizar-colores',
    ),
    1 => 
    array (
      'nombre' => 'Borrar colores',
      'slug' => 'borrar-colores',
    ),
    2 => 
    array (
      'nombre' => 'Crear colores',
      'slug' => 'crear-colores',
    ),
    3 => 
    array (
      'nombre' => 'Editar colores',
      'slug' => 'editar-colores',
    ),
    4 => 
    array (
      'nombre' => 'Listar colores',
      'slug' => 'listar-colores',
    ),
  ),
  'stock/configuracion-salida-bienes' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración salida de bienes',
      'slug' => 'actualizar-configuracion-salida-bienes',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración salida de bienes',
      'slug' => 'editar-configuracion-salida-bienes',
    ),
  ),
  'stock/contrafuerte' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar contrafuertes',
      'slug' => 'actualizar-contrafuertes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar contrafuertes',
      'slug' => 'borrar-contrafuertes',
    ),
    2 => 
    array (
      'nombre' => 'Crear contrafuertes',
      'slug' => 'crear-contrafuertes',
    ),
    3 => 
    array (
      'nombre' => 'Editar contrafuertes',
      'slug' => 'editar-contrafuertes',
    ),
    4 => 
    array (
      'nombre' => 'Listar contrafuertes',
      'slug' => 'listar-contrafuertes',
    ),
  ),
  'stock/crearimportaciontiendanube' => 
  array (
    0 => 
    array (
      'nombre' => 'Importar Tienda Nube',
      'slug' => 'importar-tiendanube',
    ),
  ),
  'stock/depmae' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar depositos',
      'slug' => 'actualizar-depositos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar depositos',
      'slug' => 'borrar-depositos',
    ),
    2 => 
    array (
      'nombre' => 'Crear depositos',
      'slug' => 'crear-depositos',
    ),
    3 => 
    array (
      'nombre' => 'Editar depositos',
      'slug' => 'editar-depositos',
    ),
    4 => 
    array (
      'nombre' => 'Listar depositos',
      'slug' => 'listar-depositos',
    ),
  ),
  'stock/deposito-administrador' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar administrador de depósito',
      'slug' => 'actualizar-deposito-administrador',
    ),
    1 => 
    array (
      'nombre' => 'Borrar administrador de depósito',
      'slug' => 'borrar-deposito-administrador',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar administrador de depósito',
      'slug' => 'crear-deposito-administrador',
    ),
    3 => 
    array (
      'nombre' => 'Editar administrador de depósito',
      'slug' => 'editar-deposito-administrador',
    ),
    4 => 
    array (
      'nombre' => 'Listar administradores de depósito',
      'slug' => 'listar-deposito-administrador',
    ),
  ),
  'stock/fondo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar fondos',
      'slug' => 'actualizar-fondos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar fondos',
      'slug' => 'borrar-fondos',
    ),
    2 => 
    array (
      'nombre' => 'Crear fondos',
      'slug' => 'crear-fondos',
    ),
    3 => 
    array (
      'nombre' => 'Editar fondos',
      'slug' => 'editar-fondos',
    ),
    4 => 
    array (
      'nombre' => 'Listar fondos',
      'slug' => 'listar-fondos',
    ),
  ),
  'stock/formula-articulo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar fórmulas de artículos',
      'slug' => 'actualizar-formula-articulo',
    ),
    1 => 
    array (
      'nombre' => 'Borrar fórmulas de artículos',
      'slug' => 'borrar-formula-articulo',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar fórmulas de artículos',
      'slug' => 'crear-formula-articulo',
    ),
    3 => 
    array (
      'nombre' => 'Editar fórmulas de artículos',
      'slug' => 'editar-formula-articulo',
    ),
    4 => 
    array (
      'nombre' => 'Listar fórmulas de artículos',
      'slug' => 'listar-formula-articulo',
    ),
  ),
  'stock/forro' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar forros',
      'slug' => 'actualizar-forros',
    ),
    1 => 
    array (
      'nombre' => 'Borrar forros',
      'slug' => 'borrar-forros',
    ),
    2 => 
    array (
      'nombre' => 'Crear forros',
      'slug' => 'crear-forros',
    ),
    3 => 
    array (
      'nombre' => 'Editar forros',
      'slug' => 'editar-forros',
    ),
    4 => 
    array (
      'nombre' => 'Listar forros',
      'slug' => 'listar-forros',
    ),
  ),
  'stock/horma' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar hormas',
      'slug' => 'actualizar-hormas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar hormas',
      'slug' => 'borrar-hormas',
    ),
    2 => 
    array (
      'nombre' => 'Crear hormas',
      'slug' => 'crear-hormas',
    ),
    3 => 
    array (
      'nombre' => 'Editar hormas',
      'slug' => 'editar-hormas',
    ),
    4 => 
    array (
      'nombre' => 'Listar hormas',
      'slug' => 'listar-hormas',
    ),
  ),
  'stock/linea' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar lineas',
      'slug' => 'actualizar-lineas',
    ),
    1 => 
    array (
      'nombre' => 'Borrar lineas',
      'slug' => 'borrar-lineas',
    ),
    2 => 
    array (
      'nombre' => 'Crear lineas',
      'slug' => 'crear-lineas',
    ),
    3 => 
    array (
      'nombre' => 'Editar lineas',
      'slug' => 'editar-lineas',
    ),
    4 => 
    array (
      'nombre' => 'Listar lineas',
      'slug' => 'listar-lineas',
    ),
  ),
  'stock/listaprecio' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar listas de precio',
      'slug' => 'actualizar-listaprecio',
    ),
    1 => 
    array (
      'nombre' => 'Borrar listas de precio',
      'slug' => 'borrar-listaprecio',
    ),
    2 => 
    array (
      'nombre' => 'Crear listas de precio',
      'slug' => 'crear-listaprecio',
    ),
    3 => 
    array (
      'nombre' => 'Editar listas de precio',
      'slug' => 'editar-listaprecio',
    ),
    4 => 
    array (
      'nombre' => 'Listar listas de precio',
      'slug' => 'listar-listaprecio',
    ),
  ),
  'stock/lote' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar lotes',
      'slug' => 'actualizar-lotes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar lotes',
      'slug' => 'borrar-lotes',
    ),
    2 => 
    array (
      'nombre' => 'Crear lotes',
      'slug' => 'crear-lotes',
    ),
    3 => 
    array (
      'nombre' => 'Editar lotes',
      'slug' => 'editar-lotes',
    ),
    4 => 
    array (
      'nombre' => 'Listar lotes',
      'slug' => 'listar-lotes',
    ),
  ),
  'stock/material' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar materiales',
      'slug' => 'actualizar-materiales',
    ),
    1 => 
    array (
      'nombre' => 'Borrar materiales',
      'slug' => 'borrar-materiales',
    ),
    2 => 
    array (
      'nombre' => 'Crear materiales',
      'slug' => 'crear-materiales',
    ),
    3 => 
    array (
      'nombre' => 'Editar materiales',
      'slug' => 'editar-materiales',
    ),
    4 => 
    array (
      'nombre' => 'Listar materiales',
      'slug' => 'listar-materiales',
    ),
  ),
  'stock/modulo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar modulos',
      'slug' => 'actualizar-modulos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar modulos',
      'slug' => 'borrar-modulos',
    ),
    2 => 
    array (
      'nombre' => 'Crear modulos',
      'slug' => 'crear-modulos',
    ),
    3 => 
    array (
      'nombre' => 'Editar modulos',
      'slug' => 'editar-modulos',
    ),
    4 => 
    array (
      'nombre' => 'Listar modulos',
      'slug' => 'listar-modulos',
    ),
  ),
  'stock/mventa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar marcas de venta',
      'slug' => 'actualizar-marcas-de-venta',
    ),
    1 => 
    array (
      'nombre' => 'Borrar marcas de venta',
      'slug' => 'borrar-marcas-de-venta',
    ),
    2 => 
    array (
      'nombre' => 'Crear marcas de venta',
      'slug' => 'crear-marcas-de-venta',
    ),
    3 => 
    array (
      'nombre' => 'Editar marcas de venta',
      'slug' => 'editar-marcas-de-venta',
    ),
    4 => 
    array (
      'nombre' => 'Listar marcas de venta',
      'slug' => 'listar-marcas-de-venta',
    ),
  ),
  'stock/numeracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar numeraciones',
      'slug' => 'actualizar-numeraciones',
    ),
    1 => 
    array (
      'nombre' => 'Borrar numeraciones',
      'slug' => 'borrar-numeraciones',
    ),
    2 => 
    array (
      'nombre' => 'Crear numeraciones',
      'slug' => 'crear-numeraciones',
    ),
    3 => 
    array (
      'nombre' => 'Editar numeraciones',
      'slug' => 'editar-numeraciones',
    ),
    4 => 
    array (
      'nombre' => 'Listar numeraciones',
      'slug' => 'listar-numeraciones',
    ),
  ),
  'stock/precio' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar precios',
      'slug' => 'actualizar-precios',
    ),
    1 => 
    array (
      'nombre' => 'Borrar precios',
      'slug' => 'borrar-precios',
    ),
    2 => 
    array (
      'nombre' => 'Crear precios',
      'slug' => 'crear-precios',
    ),
    3 => 
    array (
      'nombre' => 'Editar precios',
      'slug' => 'editar-precios',
    ),
    4 => 
    array (
      'nombre' => 'Listar precios',
      'slug' => 'listar-precios',
    ),
  ),
  'stock/puntera' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar punteras',
      'slug' => 'actualizar-punteras',
    ),
    1 => 
    array (
      'nombre' => 'Borrar punteras',
      'slug' => 'borrar-punteras',
    ),
    2 => 
    array (
      'nombre' => 'Crear punteras',
      'slug' => 'crear-punteras',
    ),
    3 => 
    array (
      'nombre' => 'Editar punteras',
      'slug' => 'editar-punteras',
    ),
    4 => 
    array (
      'nombre' => 'Listar punteras',
      'slug' => 'listar-punteras',
    ),
  ),
  'stock/recepcion-proveedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar recepción de proveedor',
      'slug' => 'actualizar-recepcion-proveedor',
    ),
    1 => 
    array (
      'nombre' => 'Anular recepción de proveedor',
      'slug' => 'anular-recepcion-proveedor',
    ),
    2 => 
    array (
      'nombre' => 'Borrar recepcion proveedor',
      'slug' => 'borrar-recepcion-proveedor',
    ),
    3 => 
    array (
      'nombre' => 'Confirmar recepción de proveedor',
      'slug' => 'confirmar-recepcion-proveedor',
    ),
    4 => 
    array (
      'nombre' => 'Ingresar recepción de proveedor',
      'slug' => 'crear-recepcion-proveedor',
    ),
    5 => 
    array (
      'nombre' => 'Registrar devolución a proveedor',
      'slug' => 'devolver-recepcion-proveedor',
    ),
    6 => 
    array (
      'nombre' => 'Editar recepción de proveedor',
      'slug' => 'editar-recepcion-proveedor',
    ),
    7 => 
    array (
      'nombre' => 'Listar recepciones de proveedor',
      'slug' => 'listar-recepcion-proveedor',
    ),
    8 => 
    array (
      'nombre' => 'Cargar recepción por OCR',
      'slug' => 'ocr-recepcion-proveedor',
    ),
  ),
  'stock/recuento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar recuento',
      'slug' => 'actualizar-recuento',
    ),
    1 => 
    array (
      'nombre' => 'Anular recuento',
      'slug' => 'anular-recuento',
    ),
    2 => 
    array (
      'nombre' => 'Borrar recuento',
      'slug' => 'borrar-recuento',
    ),
    3 => 
    array (
      'nombre' => 'Cerrar recuento parcial',
      'slug' => 'cerrar-recuento-parcial',
    ),
    4 => 
    array (
      'nombre' => 'Cerrar recuento total',
      'slug' => 'cerrar-recuento-total',
    ),
    5 => 
    array (
      'nombre' => 'Ingresar recuento',
      'slug' => 'crear-recuento',
    ),
    6 => 
    array (
      'nombre' => 'Editar recuento',
      'slug' => 'editar-recuento',
    ),
    7 => 
    array (
      'nombre' => 'Importar recuento Excel',
      'slug' => 'importar-recuento',
    ),
    8 => 
    array (
      'nombre' => 'Imprimir recuento PDF',
      'slug' => 'imprimir-recuento',
    ),
    9 => 
    array (
      'nombre' => 'Listar recuentos',
      'slug' => 'listar-recuento',
    ),
    10 => 
    array (
      'nombre' => 'Suspender recuento',
      'slug' => 'suspender-recuento',
    ),
    11 => 
    array (
      'nombre' => 'Ver recuento',
      'slug' => 'ver-recuento',
    ),
  ),
  'stock/reporte-baja-npu' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar reporte baja NPU',
      'slug' => 'listar-reporte-baja-npu',
    ),
  ),
  'stock/salida-bienes' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar salida de bienes',
      'slug' => 'actualizar-salida-bienes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar salida de bienes',
      'slug' => 'borrar-salida-bienes',
    ),
    2 => 
    array (
      'nombre' => 'Cerrar salida de bienes sin devolución',
      'slug' => 'cerrar-salida-bienes',
    ),
    3 => 
    array (
      'nombre' => 'Ingresar salida de bienes',
      'slug' => 'crear-salida-bienes',
    ),
    4 => 
    array (
      'nombre' => 'Registrar devolución salida de bienes',
      'slug' => 'devolver-salida-bienes',
    ),
    5 => 
    array (
      'nombre' => 'Editar salida de bienes',
      'slug' => 'editar-salida-bienes',
    ),
    6 => 
    array (
      'nombre' => 'Listar salida de bienes',
      'slug' => 'listar-salida-bienes',
    ),
  ),
  'stock/serigrafia' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar serigrafias',
      'slug' => 'actualizar-serigrafias',
    ),
    1 => 
    array (
      'nombre' => 'Borrar serigrafias',
      'slug' => 'borrar-serigrafias',
    ),
    2 => 
    array (
      'nombre' => 'Crear serigrafias',
      'slug' => 'crear-serigrafias',
    ),
    3 => 
    array (
      'nombre' => 'Editar serigrafias',
      'slug' => 'editar-serigrafias',
    ),
    4 => 
    array (
      'nombre' => 'Listar serigrafias',
      'slug' => 'listar-serigrafias',
    ),
  ),
  'stock/subcategoria' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar subcategorias',
      'slug' => 'actualizar-subcategorias',
    ),
    1 => 
    array (
      'nombre' => 'Borrar categorias',
      'slug' => 'borrar-subcategorias',
    ),
    2 => 
    array (
      'nombre' => 'Crear subcategorias',
      'slug' => 'crear-subcategorias',
    ),
    3 => 
    array (
      'nombre' => 'Editar subcategorias',
      'slug' => 'editar-subcategorias',
    ),
    4 => 
    array (
      'nombre' => 'Listar subcategorias',
      'slug' => 'listar-subcategorias',
    ),
  ),
  'stock/talle' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar medidas talles',
      'slug' => 'actualizar-talles',
    ),
    1 => 
    array (
      'nombre' => 'Borrar medidas talles',
      'slug' => 'borrar-talles',
    ),
    2 => 
    array (
      'nombre' => 'Crear medidas talles',
      'slug' => 'crear-talles',
    ),
    3 => 
    array (
      'nombre' => 'Editar medidas talles',
      'slug' => 'editar-talles',
    ),
    4 => 
    array (
      'nombre' => 'Listar medidas talles',
      'slug' => 'listar-talles',
    ),
  ),
  'stock/tipoarticulo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipos de articulo',
      'slug' => 'actualizar-tipo-articulo',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipos de articulo',
      'slug' => 'borrar-tipo-articulo',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipos de articulo',
      'slug' => 'crear-tipo-articulo',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipos de articulo',
      'slug' => 'editar-tipo-articulo',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos de articulo',
      'slug' => 'listar-tipo-articulo',
    ),
  ),
  'stock/tipocorte' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo de cortes',
      'slug' => 'actualizar-tipo-cortes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo de cortes',
      'slug' => 'borrar-tipo-cortes',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo de cortes',
      'slug' => 'crear-tipo-cortes',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo de cortes',
      'slug' => 'editar-tipo-cortes',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipo de cortes',
      'slug' => 'listar-tipo-cortes',
    ),
  ),
  'stock/tiponumeracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipos de numeracion',
      'slug' => 'actualizar-tipo-numeraciones',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipos de numeracion',
      'slug' => 'borrar-tipo-numeraciones',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipos de numeracion',
      'slug' => 'crear-tipo-numeraciones',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipos de numeracion',
      'slug' => 'editar-tipo-numeraciones',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos de numeracion',
      'slug' => 'listar-tipo-numeraciones',
    ),
  ),
  'stock/transferencia-mercaderia' => 
  array (
    0 => 
    array (
      'nombre' => 'Transferir mercadería entre depósitos',
      'slug' => 'crear-transferencia-mercaderia',
    ),
  ),
  'stock/unidadmedida' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar unidades de medida',
      'slug' => 'actualizar-unidades-de-medida',
    ),
    1 => 
    array (
      'nombre' => 'Borrar unidades de medida',
      'slug' => 'borrar-unidades-de-medida',
    ),
    2 => 
    array (
      'nombre' => 'Crear unidades de medida',
      'slug' => 'crear-unidades-de-medida',
    ),
    3 => 
    array (
      'nombre' => 'Editar unidades de medida',
      'slug' => 'editar-unidades-de-medida',
    ),
    4 => 
    array (
      'nombre' => 'Listar unidades de medida',
      'slug' => 'listar-unidades-de-medida',
    ),
  ),
  'stock/usoarticulo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar uso de articulos',
      'slug' => 'actualizar-uso-de-articulos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar uso de articulos',
      'slug' => 'borrar-uso-de-articulos',
    ),
    2 => 
    array (
      'nombre' => 'Crear uso de articulos',
      'slug' => 'crear-uso-de-articulos',
    ),
    3 => 
    array (
      'nombre' => 'Editar uso de articulos',
      'slug' => 'editar-uso-de-articulos',
    ),
    4 => 
    array (
      'nombre' => 'Listar uso de articulos',
      'slug' => 'listar-uso-de-articulos',
    ),
  ),
  'sueldos/acumulador' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar acumulador sueldos',
      'slug' => 'actualizar-acumulador-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar acumulador sueldos',
      'slug' => 'borrar-acumulador-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear acumulador sueldos',
      'slug' => 'crear-acumulador-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar acumulador sueldos',
      'slug' => 'editar-acumulador-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar acumulador sueldos',
      'slug' => 'listar-acumulador-sueldos',
    ),
  ),
  'sueldos/agrupamiento' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar agrupamiento sueldos',
      'slug' => 'actualizar-agrupamiento-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar agrupamiento sueldos',
      'slug' => 'borrar-agrupamiento-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear agrupamiento sueldos',
      'slug' => 'crear-agrupamiento-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar agrupamiento sueldos',
      'slug' => 'editar-agrupamiento-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar agrupamiento sueldos',
      'slug' => 'listar-agrupamiento-sueldos',
    ),
  ),
  'sueldos/art' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar art sueldos',
      'slug' => 'actualizar-art-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar art sueldos',
      'slug' => 'borrar-art-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear art sueldos',
      'slug' => 'crear-art-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar art sueldos',
      'slug' => 'editar-art-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar art sueldos',
      'slug' => 'listar-art-sueldos',
    ),
  ),
  'sueldos/categoria' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar categoría sueldos',
      'slug' => 'actualizar-categoria-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar categoría sueldos',
      'slug' => 'borrar-categoria-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear categoría sueldos',
      'slug' => 'crear-categoria-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar categoría sueldos',
      'slug' => 'editar-categoria-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar categoría sueldos',
      'slug' => 'listar-categoria-sueldos',
    ),
  ),
  'sueldos/concepto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar concepto sueldos',
      'slug' => 'actualizar-concepto-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar concepto sueldos',
      'slug' => 'borrar-concepto-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear concepto sueldos',
      'slug' => 'crear-concepto-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar concepto sueldos',
      'slug' => 'editar-concepto-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar concepto sueldos',
      'slug' => 'listar-concepto-sueldos',
    ),
  ),
  'sueldos/empleado' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar empleado sueldos',
      'slug' => 'actualizar-empleado-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Autorizar alta empleado sueldos',
      'slug' => 'autorizar-empleado-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Borrar empleado sueldos',
      'slug' => 'borrar-empleado-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Crear empleado sueldos',
      'slug' => 'crear-empleado-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Editar empleado sueldos',
      'slug' => 'editar-empleado-sueldos',
    ),
    5 => 
    array (
      'nombre' => 'Listar empleado sueldos',
      'slug' => 'listar-empleado-sueldos',
    ),
  ),
  'sueldos/entrega-prenda' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar entrega de prendas',
      'slug' => 'listar-entrega-prenda',
    ),
  ),
  'sueldos/fallo-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar cta. cte. fallos sueldos',
      'slug' => 'listar-fallo-reporte-sueldos',
    ),
  ),
  'sueldos/fallocaja' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar fallo de caja sueldos',
      'slug' => 'actualizar-fallocaja-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar fallo de caja sueldos',
      'slug' => 'borrar-fallocaja-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear fallo de caja sueldos',
      'slug' => 'crear-fallocaja-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar fallo de caja sueldos',
      'slug' => 'editar-fallocaja-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar fallos de caja sueldos',
      'slug' => 'listar-fallocaja-sueldos',
    ),
  ),
  'sueldos/ganancia-deduccion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ganancia deduccion sueldos',
      'slug' => 'actualizar-ganancia-deduccion-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Editar ganancia deduccion sueldos',
      'slug' => 'editar-ganancia-deduccion-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Listar ganancia deduccion sueldos',
      'slug' => 'listar-ganancia-deduccion-sueldos',
    ),
  ),
  'sueldos/ganancia-escala' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ganancia escala sueldos',
      'slug' => 'actualizar-ganancia-escala-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Editar ganancia escala sueldos',
      'slug' => 'editar-ganancia-escala-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Listar ganancia escala sueldos',
      'slug' => 'listar-ganancia-escala-sueldos',
    ),
  ),
  'sueldos/ganancia-linea' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar ganancia linea sueldos',
      'slug' => 'actualizar-ganancia-linea-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar ganancia linea sueldos',
      'slug' => 'borrar-ganancia-linea-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear ganancia linea sueldos',
      'slug' => 'crear-ganancia-linea-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar ganancia linea sueldos',
      'slug' => 'editar-ganancia-linea-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar ganancia linea sueldos',
      'slug' => 'listar-ganancia-linea-sueldos',
    ),
  ),
  'sueldos/grupo-concepto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar grupo concepto sueldos',
      'slug' => 'actualizar-grupo-concepto-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar grupo concepto sueldos',
      'slug' => 'borrar-grupo-concepto-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear grupo concepto sueldos',
      'slug' => 'crear-grupo-concepto-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar grupo concepto sueldos',
      'slug' => 'editar-grupo-concepto-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar grupo concepto sueldos',
      'slug' => 'listar-grupo-concepto-sueldos',
    ),
  ),
  'sueldos/imputacion-concepto' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar imputacion concepto sueldos',
      'slug' => 'actualizar-imputacion-concepto-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar imputacion concepto sueldos',
      'slug' => 'borrar-imputacion-concepto-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear imputacion concepto sueldos',
      'slug' => 'crear-imputacion-concepto-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar imputacion concepto sueldos',
      'slug' => 'editar-imputacion-concepto-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar imputacion concepto sueldos',
      'slug' => 'listar-imputacion-concepto-sueldos',
    ),
  ),
  'sueldos/indumentaria/aprobacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar árbol de aprobación de indumentaria',
      'slug' => 'editar-aprobacion-indumentaria',
    ),
    1 => 
    array (
      'nombre' => 'Ver árbol de aprobación de indumentaria',
      'slug' => 'ver-aprobacion-indumentaria',
    ),
  ),
  'sueldos/indumentaria/configuracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar configuración indumentaria',
      'slug' => 'editar-configuracion-indumentaria',
    ),
    1 => 
    array (
      'nombre' => 'Ver configuración indumentaria',
      'slug' => 'ver-configuracion-indumentaria',
    ),
  ),
  'sueldos/indumentaria/planificacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Ver planificación de indumentaria',
      'slug' => 'ver-planificacion-indumentaria',
    ),
  ),
  'sueldos/liquidacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar liquidacion sueldos',
      'slug' => 'actualizar-liquidacion-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar liquidacion sueldos',
      'slug' => 'borrar-liquidacion-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Cerrar liquidacion sueldos',
      'slug' => 'cerrar-liquidacion-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Crear liquidacion sueldos',
      'slug' => 'crear-liquidacion-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Editar liquidacion sueldos',
      'slug' => 'editar-liquidacion-sueldos',
    ),
    5 => 
    array (
      'nombre' => 'Importar liquidacion confidencial sueldos',
      'slug' => 'importar-liquidacion-confidencial-sueldos',
    ),
    6 => 
    array (
      'nombre' => 'Listar liquidacion sueldos',
      'slug' => 'listar-liquidacion-sueldos',
    ),
  ),
  'sueldos/lugartrabajo' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar lugar trabajo sueldos',
      'slug' => 'actualizar-lugartrabajo-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar lugar trabajo sueldos',
      'slug' => 'borrar-lugartrabajo-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear lugar trabajo sueldos',
      'slug' => 'crear-lugartrabajo-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar lugar trabajo sueldos',
      'slug' => 'editar-lugartrabajo-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar lugar trabajo sueldos',
      'slug' => 'listar-lugartrabajo-sueldos',
    ),
  ),
  'sueldos/motivo-sancion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar motivo sancion sueldos',
      'slug' => 'actualizar-motivo-sancion-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar motivo sancion sueldos',
      'slug' => 'borrar-motivo-sancion-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear motivo sancion sueldos',
      'slug' => 'crear-motivo-sancion-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar motivo sancion sueldos',
      'slug' => 'editar-motivo-sancion-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar motivo sancion sueldos',
      'slug' => 'listar-motivo-sancion-sueldos',
    ),
  ),
  'sueldos/motivoegreso' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar motivo egreso sueldos',
      'slug' => 'actualizar-motivoegreso-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar motivo egreso sueldos',
      'slug' => 'borrar-motivoegreso-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear motivo egreso sueldos',
      'slug' => 'crear-motivoegreso-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar motivo egreso sueldos',
      'slug' => 'editar-motivoegreso-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar motivo egreso sueldos',
      'slug' => 'listar-motivoegreso-sueldos',
    ),
  ),
  'sueldos/nombrebase' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar nombre de base sueldos',
      'slug' => 'actualizar-nombrebase-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar nombre de base sueldos',
      'slug' => 'borrar-nombrebase-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear nombre de base sueldos',
      'slug' => 'crear-nombrebase-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar nombre de base sueldos',
      'slug' => 'editar-nombrebase-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar nombre de base sueldos',
      'slug' => 'listar-nombrebase-sueldos',
    ),
  ),
  'sueldos/novedad' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar novedad sueldos',
      'slug' => 'actualizar-novedad-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar novedad sueldos',
      'slug' => 'borrar-novedad-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear novedad sueldos',
      'slug' => 'crear-novedad-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar novedad sueldos',
      'slug' => 'editar-novedad-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar novedad sueldos',
      'slug' => 'listar-novedad-sueldos',
    ),
  ),
  'sueldos/obrasocial' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar obra social sueldos',
      'slug' => 'actualizar-obrasocial-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar obra social sueldos',
      'slug' => 'borrar-obrasocial-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear obra social sueldos',
      'slug' => 'crear-obrasocial-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar obra social sueldos',
      'slug' => 'editar-obrasocial-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar obra social sueldos',
      'slug' => 'listar-obrasocial-sueldos',
    ),
  ),
  'sueldos/parametro' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar parametro sueldos',
      'slug' => 'actualizar-parametro-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar parametro sueldos',
      'slug' => 'borrar-parametro-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear parametro sueldos',
      'slug' => 'crear-parametro-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar parametro sueldos',
      'slug' => 'editar-parametro-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar parametro sueldos',
      'slug' => 'listar-parametro-sueldos',
    ),
  ),
  'sueldos/prenda' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar prenda sueldos',
      'slug' => 'actualizar-prenda-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar prenda sueldos',
      'slug' => 'borrar-prenda-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear prenda sueldos',
      'slug' => 'crear-prenda-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar prenda sueldos',
      'slug' => 'editar-prenda-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar prenda sueldos',
      'slug' => 'listar-prenda-sueldos',
    ),
  ),
  'sueldos/saldo-vacaciones' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar saldo vacaciones sueldos',
      'slug' => 'listar-saldo-vacaciones-sueldos',
    ),
  ),
  'sueldos/sancion-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar sancion reporte sueldos',
      'slug' => 'listar-sancion-reporte-sueldos',
    ),
  ),
  'sueldos/sindicato' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar sindicato sueldos',
      'slug' => 'actualizar-sindicato-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar sindicato sueldos',
      'slug' => 'borrar-sindicato-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear sindicato sueldos',
      'slug' => 'crear-sindicato-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar sindicato sueldos',
      'slug' => 'editar-sindicato-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar sindicato sueldos',
      'slug' => 'listar-sindicato-sueldos',
    ),
  ),
  'sueldos/siradig' => 
  array (
    0 => 
    array (
      'nombre' => 'Borrar SiRADIG sueldos',
      'slug' => 'borrar-siradig-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Importar SiRADIG sueldos',
      'slug' => 'importar-siradig-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Listar SiRADIG sueldos',
      'slug' => 'listar-siradig-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Ver SiRADIG sueldos',
      'slug' => 'ver-siradig-sueldos',
    ),
  ),
  'sueldos/tipo-ausencia' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo ausencia sueldos',
      'slug' => 'actualizar-tipo-ausencia-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo ausencia sueldos',
      'slug' => 'borrar-tipo-ausencia-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo ausencia sueldos',
      'slug' => 'crear-tipo-ausencia-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo ausencia sueldos',
      'slug' => 'editar-tipo-ausencia-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipo ausencia sueldos',
      'slug' => 'listar-tipo-ausencia-sueldos',
    ),
  ),
  'sueldos/tipo-sancion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo sancion sueldos',
      'slug' => 'actualizar-tipo-sancion-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo sancion sueldos',
      'slug' => 'borrar-tipo-sancion-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo sancion sueldos',
      'slug' => 'crear-tipo-sancion-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo sancion sueldos',
      'slug' => 'editar-tipo-sancion-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipo sancion sueldos',
      'slug' => 'listar-tipo-sancion-sueldos',
    ),
  ),
  'sueldos/vacacion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar vacación sueldos',
      'slug' => 'actualizar-vacacion-sueldos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar vacación sueldos',
      'slug' => 'borrar-vacacion-sueldos',
    ),
    2 => 
    array (
      'nombre' => 'Crear vacación sueldos',
      'slug' => 'crear-vacacion-sueldos',
    ),
    3 => 
    array (
      'nombre' => 'Editar vacación sueldos',
      'slug' => 'editar-vacacion-sueldos',
    ),
    4 => 
    array (
      'nombre' => 'Listar vacación sueldos',
      'slug' => 'listar-vacacion-sueldos',
    ),
  ),
  'ticket/configuracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuracion ticket',
      'slug' => 'actualizar-configuracion-ticket',
    ),
    1 => 
    array (
      'nombre' => 'Listar configuracion ticket',
      'slug' => 'listar-configuracion-ticket',
    ),
  ),
  'ventas/arca-caea' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar CAEA ARCA',
      'slug' => 'listar-arca-caea',
    ),
    1 => 
    array (
      'nombre' => 'Ver detalle CAEA ARCA',
      'slug' => 'ver-arca-caea',
    ),
  ),
  'ventas/auditoria-notas-suitecrm' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar auditoría de notas SuiteCRM',
      'slug' => 'listar-auditoria-notas-suitecrm',
    ),
  ),
  'ventas/cai' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar CAI',
      'slug' => 'actualizar-cai',
    ),
    1 => 
    array (
      'nombre' => 'Borrar CAI',
      'slug' => 'borrar-cai',
    ),
    2 => 
    array (
      'nombre' => 'Crear CAI',
      'slug' => 'crear-cai',
    ),
    3 => 
    array (
      'nombre' => 'Editar CAI',
      'slug' => 'editar-cai',
    ),
    4 => 
    array (
      'nombre' => 'Listar CAI',
      'slug' => 'listar-cai',
    ),
  ),
  'ventas/certificados-arca' => 
  array (
    0 => 
    array (
      'nombre' => 'Instalar certificados ARCA',
      'slug' => 'instalar-certificados-arca',
    ),
    1 => 
    array (
      'nombre' => 'Listar certificados ARCA',
      'slug' => 'listar-certificados-arca',
    ),
  ),
  'ventas/cliente' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar clientes',
      'slug' => 'actualizar-clientes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar clientes',
      'slug' => 'borrar-clientes',
    ),
    2 => 
    array (
      'nombre' => 'Crear clientes',
      'slug' => 'crear-clientes',
    ),
    3 => 
    array (
      'nombre' => 'Editar clientes',
      'slug' => 'editar-clientes',
    ),
    4 => 
    array (
      'nombre' => 'Listar clientes',
      'slug' => 'listar-clientes',
    ),
  ),
  'ventas/cliente-cuentacorriente-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar reporte deuda / CC clientes',
      'slug' => 'listar-cliente-cuentacorriente-reporte',
    ),
  ),
  'ventas/cobrador' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar cobrador',
      'slug' => 'actualizar-cobrador',
    ),
    1 => 
    array (
      'nombre' => 'Borrar cobrador',
      'slug' => 'borrar-cobrador',
    ),
    2 => 
    array (
      'nombre' => 'Crear cobrador',
      'slug' => 'crear-cobrador',
    ),
    3 => 
    array (
      'nombre' => 'Editar cobrador',
      'slug' => 'editar-cobrador',
    ),
    4 => 
    array (
      'nombre' => 'Listar cobradores',
      'slug' => 'listar-cobrador',
    ),
  ),
  'ventas/condicionventa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar condiciones de venta',
      'slug' => 'actualizar-condiciones-de-venta',
    ),
    1 => 
    array (
      'nombre' => 'Borrar condiciones de venta',
      'slug' => 'borrar-condiciones-de-venta',
    ),
    2 => 
    array (
      'nombre' => 'Crear condiciones de venta',
      'slug' => 'crear-condiciones-de-venta',
    ),
    3 => 
    array (
      'nombre' => 'Editar condiciones de venta',
      'slug' => 'editar-condiciones-de-venta',
    ),
    4 => 
    array (
      'nombre' => 'Listar condiciones de venta',
      'slug' => 'listar-condiciones-de-venta',
    ),
  ),
  'ventas/configuracion-puntoventa-gastronomia' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar config. PV gastronomía',
      'slug' => 'actualizar-configuracion-puntoventa-gastronomia',
    ),
    1 => 
    array (
      'nombre' => 'Borrar config. PV gastronomía',
      'slug' => 'borrar-configuracion-puntoventa-gastronomia',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar config. PV gastronomía',
      'slug' => 'crear-configuracion-puntoventa-gastronomia',
    ),
    3 => 
    array (
      'nombre' => 'Editar config. PV gastronomía',
      'slug' => 'editar-configuracion-puntoventa-gastronomia',
    ),
    4 => 
    array (
      'nombre' => 'Listar config. PV gastronomía',
      'slug' => 'listar-configuracion-puntoventa-gastronomia',
    ),
  ),
  'ventas/contrato-venta' => 
  array (
    0 => 
    array (
      'nombre' => 'Cola de facturación de abonos',
      'slug' => 'listar-contrato-venta-cola',
    ),
  ),
  'ventas/contrato-venta-cola' => 
  array (
    0 => 
    array (
      'nombre' => 'Cola de facturación de abonos',
      'slug' => 'listar-contrato-venta-cola',
    ),
  ),
  'ventas/cot-configuracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar configuración COT ARBA',
      'slug' => 'actualizar-cot-configuracion',
    ),
    1 => 
    array (
      'nombre' => 'Editar configuración COT ARBA',
      'slug' => 'editar-cot-configuracion',
    ),
  ),
  'ventas/distribuidor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar distribuidor',
      'slug' => 'actualizar-distribuidor',
    ),
    1 => 
    array (
      'nombre' => 'Borrar distribuidor',
      'slug' => 'borrar-distribuidor',
    ),
    2 => 
    array (
      'nombre' => 'Crear distribuidor',
      'slug' => 'crear-distribuidor',
    ),
    3 => 
    array (
      'nombre' => 'Editar distribuidor',
      'slug' => 'editar-distribuidor',
    ),
    4 => 
    array (
      'nombre' => 'Listar distribuidores',
      'slug' => 'listar-distribuidor',
    ),
  ),
  'ventas/factura' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualiza factura',
      'slug' => 'actualizar-factura',
    ),
    1 => 
    array (
      'nombre' => 'Ingresa factura',
      'slug' => 'crear-factura',
    ),
    2 => 
    array (
      'nombre' => 'Edita factura',
      'slug' => 'editar-factura',
    ),
    3 => 
    array (
      'nombre' => 'Lista factura',
      'slug' => 'listar-factura',
    ),
  ),
  'ventas/factura-mail-configuracion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar mail de facturas',
      'slug' => 'actualizar-factura-mail-configuracion',
    ),
    1 => 
    array (
      'nombre' => 'Editar mail de facturas',
      'slug' => 'editar-factura-mail-configuracion',
    ),
    2 => 
    array (
      'nombre' => 'Enviar factura por mail',
      'slug' => 'enviar-factura-mail',
    ),
  ),
  'ventas/factura-pdf-parametro' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar parámetros PDF factura',
      'slug' => 'actualizar-factura-pdf-parametro',
    ),
    1 => 
    array (
      'nombre' => 'Editar parámetros PDF factura',
      'slug' => 'editar-factura-pdf-parametro',
    ),
  ),
  'ventas/facturacion-local/informe-stock' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar informe stock locales',
      'slug' => 'listar-informe-stock-local',
    ),
  ),
  'ventas/facturacion-local/reportes' => 
  array (
    0 => 
    array (
      'nombre' => 'Reportes Facturación Local',
      'slug' => 'reportes-facturacion-local',
    ),
  ),
  'ventas/facturacion-local/stock' => 
  array (
    0 => 
    array (
      'nombre' => 'Consultar stock locales',
      'slug' => 'consultar-stock-local',
    ),
  ),
  'ventas/formapago' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar formas de pago',
      'slug' => 'actualizar-formas-de-pago',
    ),
    1 => 
    array (
      'nombre' => 'Borrar formas de pago',
      'slug' => 'borrar-formas-de-pago',
    ),
    2 => 
    array (
      'nombre' => 'Crear formas de pago',
      'slug' => 'crear-formas-de-pago',
    ),
    3 => 
    array (
      'nombre' => 'Editar formas de pago',
      'slug' => 'editar-formas-de-pago',
    ),
    4 => 
    array (
      'nombre' => 'Listar formas de pago',
      'slug' => 'listar-formas-de-pago',
    ),
  ),
  'ventas/gastronomia/articulos-vendidos' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar articulos',
      'slug' => 'editar-articulos',
    ),
    1 => 
    array (
      'nombre' => 'Listar articulos',
      'slug' => 'listar-articulos',
    ),
    2 => 
    array (
      'nombre' => 'Listar artículos vendidos gastronomía',
      'slug' => 'listar-articulos-vendidos-gastronomia',
    ),
  ),
  'ventas/gastronomia/canjes/cliente-vip' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar clientes VIP gastronomía',
      'slug' => 'actualizar-cliente-vip-gastronomia',
    ),
    1 => 
    array (
      'nombre' => 'Borrar clientes VIP gastronomía',
      'slug' => 'borrar-cliente-vip-gastronomia',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar clientes VIP gastronomía',
      'slug' => 'crear-cliente-vip-gastronomia',
    ),
    3 => 
    array (
      'nombre' => 'Editar clientes VIP gastronomía',
      'slug' => 'editar-cliente-vip-gastronomia',
    ),
    4 => 
    array (
      'nombre' => 'Listar clientes VIP gastronomía',
      'slug' => 'listar-cliente-vip-gastronomia',
    ),
  ),
  'ventas/gastronomia/cierre-turno-central' => 
  array (
    0 => 
    array (
      'nombre' => 'Gestionar cierre centralizado turno gastronomía',
      'slug' => 'gestionar-cierre-turno-central-gastronomia',
    ),
  ),
  'ventas/gastronomia/cierres-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar cierres de turno gastronomía',
      'slug' => 'listar-cierres-turno-gastronomia',
    ),
    1 => 
    array (
      'nombre' => 'Ver cierres turno todas terminales gastronomia',
      'slug' => 'ver-cierres-turno-todas-terminales-gastronomia',
    ),
  ),
  'ventas/gastronomia/facturas-dia' => 
  array (
    0 => 
    array (
      'nombre' => 'Cambiar medio pago gastronomia facturas dia',
      'slug' => 'cambiar-medio-pago-gastronomia-facturas-dia',
    ),
    1 => 
    array (
      'nombre' => 'Generar nota credito gastronomia facturas dia',
      'slug' => 'generar-nota-credito-gastronomia-facturas-dia',
    ),
  ),
  'ventas/gastronomia/habilitacion-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Gestionar habilitación de turno gastronomía',
      'slug' => 'gestionar-habilitacion-turno-gastronomia',
    ),
  ),
  'ventas/gastronomia/jornada' => 
  array (
    0 => 
    array (
      'nombre' => 'Abrir jornada gastronomía',
      'slug' => 'abrir-jornada-gastronomia',
    ),
    1 => 
    array (
      'nombre' => 'Cerrar jornada gastronomía',
      'slug' => 'cerrar-jornada-gastronomia',
    ),
    2 => 
    array (
      'nombre' => 'Eliminar jornada gastronomia',
      'slug' => 'eliminar-jornada-gastronomia',
    ),
    3 => 
    array (
      'nombre' => 'Gestionar jornada gastronomía',
      'slug' => 'gestionar-jornada-gastronomia',
    ),
  ),
  'ventas/gastronomia/saneamiento-turno' => 
  array (
    0 => 
    array (
      'nombre' => 'Ejecutar saneamiento turno gastronomía',
      'slug' => 'ejecutar-saneamiento-turno-gastronomia',
    ),
    1 => 
    array (
      'nombre' => 'Gestionar saneamiento turno gastronomía',
      'slug' => 'gestionar-saneamiento-turno-gastronomia',
    ),
  ),
  'ventas/gastronomia/ventas-articulos-reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Editar articulos',
      'slug' => 'editar-articulos',
    ),
    1 => 
    array (
      'nombre' => 'Listar articulos',
      'slug' => 'listar-articulos',
    ),
  ),
  'ventas/gastronomia/viandas/configuracion-terminal' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar terminales de vianda',
      'slug' => 'actualizar-configuracion-terminal-vianda',
    ),
    1 => 
    array (
      'nombre' => 'Borrar terminales de vianda',
      'slug' => 'borrar-configuracion-terminal-vianda',
    ),
    2 => 
    array (
      'nombre' => 'Ingresar terminales de vianda',
      'slug' => 'crear-configuracion-terminal-vianda',
    ),
    3 => 
    array (
      'nombre' => 'Editar terminales de vianda',
      'slug' => 'editar-configuracion-terminal-vianda',
    ),
    4 => 
    array (
      'nombre' => 'Listar terminales de vianda',
      'slug' => 'listar-configuracion-terminal-vianda',
    ),
  ),
  'ventas/gastronomia/viandas/dia' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar viandas del día',
      'slug' => 'listar-viandas-dia-gastronomia',
    ),
  ),
  'ventas/gastronomia/viandas/reporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar reporte de viandas',
      'slug' => 'listar-reporte-vianda-gastronomia',
    ),
  ),
  'ventas/incoterm' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar incoterms',
      'slug' => 'actualizar-incoterms',
    ),
    1 => 
    array (
      'nombre' => 'Borrar incoterms',
      'slug' => 'borrar-incoterms',
    ),
    2 => 
    array (
      'nombre' => 'Crear incoterms',
      'slug' => 'crear-incoterms',
    ),
    3 => 
    array (
      'nombre' => 'Editar incoterms',
      'slug' => 'editar-incoterms',
    ),
    4 => 
    array (
      'nombre' => 'Listar incoterms',
      'slug' => 'listar-incoterms',
    ),
  ),
  'ventas/iva-ventas' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar reporte IVA ventas',
      'slug' => 'listar-iva-ventas',
    ),
  ),
  'ventas/pedido' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar pedidos',
      'slug' => 'actualizar-pedidos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar pedidos',
      'slug' => 'borrar-pedidos',
    ),
    2 => 
    array (
      'nombre' => 'Crear pedidos',
      'slug' => 'crear-pedidos',
    ),
    3 => 
    array (
      'nombre' => 'Editar pedidos',
      'slug' => 'editar-pedidos',
    ),
    4 => 
    array (
      'nombre' => 'Listar pedidos',
      'slug' => 'listar-pedidos',
    ),
  ),
  'ventas/programa-impresion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar programa de impresión',
      'slug' => 'actualizar-programa-impresion',
    ),
    1 => 
    array (
      'nombre' => 'Borrar programa de impresión',
      'slug' => 'borrar-programa-impresion',
    ),
    2 => 
    array (
      'nombre' => 'Crear programa de impresión',
      'slug' => 'crear-programa-impresion',
    ),
    3 => 
    array (
      'nombre' => 'Editar programa de impresión',
      'slug' => 'editar-programa-impresion',
    ),
    4 => 
    array (
      'nombre' => 'Listar programas de impresión',
      'slug' => 'listar-programa-impresion',
    ),
  ),
  'ventas/puntoventa' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar puntos de venta',
      'slug' => 'actualizar-puntos-de-venta',
    ),
    1 => 
    array (
      'nombre' => 'Crear puntos de venta',
      'slug' => 'crear-puntos-de-venta',
    ),
    2 => 
    array (
      'nombre' => 'Editar puntos de venta',
      'slug' => 'editar-puntos-de-venta',
    ),
    3 => 
    array (
      'nombre' => 'Listar puntos de venta',
      'slug' => 'listar-puntos-de-venta',
    ),
  ),
  'ventas/remito' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar remitos',
      'slug' => 'actualizar-remitos',
    ),
    1 => 
    array (
      'nombre' => 'Borrar remitos',
      'slug' => 'borrar-remitos',
    ),
    2 => 
    array (
      'nombre' => 'Crear remitos',
      'slug' => 'crear-remitos',
    ),
    3 => 
    array (
      'nombre' => 'Editar remitos',
      'slug' => 'editar-remitos',
    ),
    4 => 
    array (
      'nombre' => 'Listar remitos',
      'slug' => 'listar-remitos',
    ),
  ),
  'ventas/subzonavta' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar subzonas de venta',
      'slug' => 'actualizar-subzonas-de-venta',
    ),
    1 => 
    array (
      'nombre' => 'Borrar subzonas de venta',
      'slug' => 'borrar-subzonas-de-venta',
    ),
    2 => 
    array (
      'nombre' => 'Crear subzonas de venta',
      'slug' => 'crear-subzonas-de-venta',
    ),
    3 => 
    array (
      'nombre' => 'Editar subzonas de venta',
      'slug' => 'editar-subzonas-de-venta',
    ),
    4 => 
    array (
      'nombre' => 'Listar subzonas de venta',
      'slug' => 'listar-subzonas-de-venta',
    ),
  ),
  'ventas/tiendanube-pedidos' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar pedidos Tiendanube',
      'slug' => 'listar-tiendanube-pedidos',
    ),
  ),
  'ventas/tipoempresa-cliente' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipo empresa cliente',
      'slug' => 'actualizar-tipo-empresa-cliente',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipo empresa cliente',
      'slug' => 'borrar-tipo-empresa-cliente',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipo empresa cliente',
      'slug' => 'crear-tipo-empresa-cliente',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipo empresa cliente',
      'slug' => 'editar-tipo-empresa-cliente',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos empresa cliente',
      'slug' => 'listar-tipo-empresa-cliente',
    ),
  ),
  'ventas/tipotransaccion' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar tipos de transacciones',
      'slug' => 'actualizar-tipos-transacciones',
    ),
    1 => 
    array (
      'nombre' => 'Borrar tipos de transacciones',
      'slug' => 'borrar-tipos-transacciones',
    ),
    2 => 
    array (
      'nombre' => 'Crear tipos de transacciones',
      'slug' => 'crear-tipos-transacciones',
    ),
    3 => 
    array (
      'nombre' => 'Editar tipos de transacciones',
      'slug' => 'editar-tipos-transacciones',
    ),
    4 => 
    array (
      'nombre' => 'Listar tipos de transacciones',
      'slug' => 'listar-tipos-transacciones',
    ),
  ),
  'ventas/transporte' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar transportes',
      'slug' => 'actualizar-transportes',
    ),
    1 => 
    array (
      'nombre' => 'Borrar transportes',
      'slug' => 'borrar-transportes',
    ),
    2 => 
    array (
      'nombre' => 'Crear transportes',
      'slug' => 'crear-transportes',
    ),
    3 => 
    array (
      'nombre' => 'Editar transportes',
      'slug' => 'editar-transportes',
    ),
    4 => 
    array (
      'nombre' => 'Listar transportes',
      'slug' => 'listar-transportes',
    ),
  ),
  'ventas/vendedor' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar vendedores',
      'slug' => 'actualizar-vendedores',
    ),
    1 => 
    array (
      'nombre' => 'Borrar vendedores',
      'slug' => 'borrar-vendedores',
    ),
    2 => 
    array (
      'nombre' => 'Crear vendedores',
      'slug' => 'crear-vendedores',
    ),
    3 => 
    array (
      'nombre' => 'Editar vendedores',
      'slug' => 'editar-vendedores',
    ),
    4 => 
    array (
      'nombre' => 'Listar vendedores',
      'slug' => 'listar-vendedores',
    ),
  ),
  'ventas/ventas-por-concepto' => 
  array (
    0 => 
    array (
      'nombre' => 'Listar reporte ventas por concepto',
      'slug' => 'listar-ventas-por-concepto',
    ),
  ),
  'ventas/zonavta' => 
  array (
    0 => 
    array (
      'nombre' => 'Actualizar zonas de venta',
      'slug' => 'actualizar-zonas-de-venta',
    ),
    1 => 
    array (
      'nombre' => 'Borrar zonas de venta',
      'slug' => 'borrar-zonas-de-venta',
    ),
    2 => 
    array (
      'nombre' => 'Crear zonas de venta',
      'slug' => 'crear-zonas-de-venta',
    ),
    3 => 
    array (
      'nombre' => 'Editar zonas de venta',
      'slug' => 'editar-zonas-de-venta',
    ),
    4 => 
    array (
      'nombre' => 'Listar zonas de venta',
      'slug' => 'listar-zonas-de-venta',
    ),
  ),
);

    /** @var list<string> */
    private array $slugsCreados = [];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolIdsGlobal = [];

        foreach (self::PERMISOS_POR_MENU as $menuUrl => $permisos) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $permisoIds = [];
            foreach ($permisos as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['slug'], $perm['nombre'], $menuId);
            }

            $padreMenuId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
            $rolIds = $this->resolverRolIds($menuId, $permisos);
            $rolIdsGlobal = array_merge($rolIdsGlobal, $rolIds);
            $this->asignarRoles($menuId, $padreMenuId, $permisoIds, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache(array_values(array_unique($rolIdsGlobal)));
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        // Solo elimina slugs creados por esta corrida si quedaron registrados;
        // en down frío no hay lista: no borra permisos históricos.
        if ($this->slugsCreados === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', $this->slugsCreados)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => mb_substr($nombre, 0, 50),
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->slugsCreados[] = $slug;
            $this->copiarRolesDesdeLegacy($slug, $permisoId);
        } else {
            $currMenu = (int) (DB::table('permiso')->where('id', $permisoId)->value('menu_id') ?? 0);
            $payload = [
                'nombre' => mb_substr($nombre, 0, 50),
                'updated_at' => now(),
            ];
            if ($currMenu === 0 || $currMenu === $menuId) {
                $payload['menu_id'] = $menuId;
            }
            DB::table('permiso')->where('id', $permisoId)->update($payload);
        }

        return $permisoId;
    }

    private function copiarRolesDesdeLegacy(string $slug, int $permisoId): void
    {
        $legacy = self::LEGACY_ALIAS[$slug] ?? null;
        if ($legacy === null) {
            return;
        }
        $legacyId = (int) (DB::table('permiso')->where('slug', $legacy)->value('id') ?? 0);
        if ($legacyId <= 0) {
            return;
        }
        foreach (DB::table('permiso_rol')->where('permiso_id', $legacyId)->pluck('rol_id') as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<array{nombre: string, slug: string}>  $permisos
     * @return list<int>
     */
    private function resolverRolIds(int $menuId, array $permisos): array
    {
        $rolIds = [];

        foreach (self::ROLES_NOMBRE as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        foreach (DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id') as $rolId) {
            $rolIds[] = (int) $rolId;
        }

        $slugs = array_column($permisos, 'slug');
        foreach ($slugs as $slug) {
            if (isset(self::LEGACY_ALIAS[$slug])) {
                $slugs[] = self::LEGACY_ALIAS[$slug];
            }
        }
        $slugs = array_values(array_unique($slugs));
        $permIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permIds->isNotEmpty()) {
            foreach (DB::table('permiso_rol')->whereIn('permiso_id', $permIds)->pluck('rol_id') as $rolId) {
                $rolIds[] = (int) $rolId;
            }
        }

        $rolIds = array_values(array_unique(array_filter($rolIds, fn ($id) => $id > 0)));

        return DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $permisoIds
     * @param  list<int>  $rolIds
     */
    private function asignarRoles(int $menuId, int $padreMenuId, array $permisoIds, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $menus = [$menuId];
            if ($padreMenuId > 0) {
                $menus[] = $padreMenuId;
            }
            foreach ($menus as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget("Permiso.rolid.$rolId");
            } catch (\Throwable) {
            }
        }
    }
};
