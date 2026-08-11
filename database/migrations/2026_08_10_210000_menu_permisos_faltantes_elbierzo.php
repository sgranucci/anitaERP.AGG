<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Barrido menú → ruta → controller: crea/vincula permisos CRUD faltantes.
 *
 * Solo corre en EL BIERZO (config app.empresa). En AGG y otros no tiene efecto.
 *
 * - Crea slugs que el controller exige y no existen (p.ej. listar-caja vs legacy lista-caja).
 * - Vincula a menu_id los permisos existentes con menu_id null.
 * - Asigna a rol administrador + roles con menu_rol + roles del slug legacy homólogo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_NOMBRE = [
        'administrador',
    ];

    /** Mapa slug moderno → legacy para copiar roles al crear alias. */
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
        'listar-movil' => 'lista-movil',
        'crear-movil' => 'crea-movil',
        'editar-movil' => 'edita-movil',
        'actualizar-movil' => 'actualiza-movil',
        'borrar-movil' => 'borra-movil',
        'listar-estado-cheque-banco' => 'lista-estado-cheque-banco',
        'crear-estado-cheque-banco' => 'crea-estado-cheque-banco',
        'editar-estado-cheque-banco' => 'edita-estado-cheque-banco',
        'actualizar-estado-cheque-banco' => 'actualiza-estado-cheque-banco',
        'borrar-estado-cheque-banco' => 'borra-estado-cheque-banco',
        'listar-rendicion-receptivo' => 'lista-rendicion-receptivo',
        'crear-rendicion-receptivo' => 'crea-rendicion-receptivo',
        'editar-rendicion-receptivo' => 'edita-rendicion-receptivo',
        'actualizar-rendicion-receptivo' => 'actualiza-rendicion-receptivo',
        'borrar-rendicion-receptivo' => 'borra-rendicion-receptivo',
        'listar-serigrafias' => 'listar-serigrafrias',
        'crear-serigrafias' => 'crear-serigrafrias',
        'editar-serigrafias' => 'editar-serigrafrias',
        'actualizar-serigrafias' => 'actualizar-serigrafrias',
        'borrar-serigrafias' => 'borrar-serigrafrias',
    ];

    private const PERMISOS_POR_MENU = [
        'stock/precio' => [
            ['nombre' => 'Listar precios', 'slug' => 'listar-precios'],
            ['nombre' => 'Crear precios', 'slug' => 'crear-precios'],
            ['nombre' => 'Editar precios', 'slug' => 'editar-precios'],
            ['nombre' => 'Actualizar precios', 'slug' => 'actualizar-precios'],
            ['nombre' => 'Borrar precios', 'slug' => 'borrar-precios'],
        ],
        'configuracion/moneda' => [
            ['nombre' => 'Listar monedas', 'slug' => 'listar-monedas'],
            ['nombre' => 'Crear monedas', 'slug' => 'crear-monedas'],
            ['nombre' => 'Editar monedas', 'slug' => 'editar-monedas'],
            ['nombre' => 'Actualizar monedas', 'slug' => 'actualizar-monedas'],
            ['nombre' => 'Borrar monedas', 'slug' => 'borrar-monedas'],
        ],
        'configuracion/impuesto' => [
            ['nombre' => 'Listar impuestos', 'slug' => 'listar-impuestos'],
            ['nombre' => 'Crear impuestos', 'slug' => 'crear-impuestos'],
            ['nombre' => 'Editar impuestos', 'slug' => 'editar-impuestos'],
            ['nombre' => 'Actualizar impuestos', 'slug' => 'actualizar-impuestos'],
            ['nombre' => 'Borrar impuestos', 'slug' => 'borrar-impuestos'],
        ],
        'configuracion/empresa' => [
            ['nombre' => 'Listar empresas', 'slug' => 'listar-empresas'],
            ['nombre' => 'Crear empresas', 'slug' => 'crear-empresas'],
            ['nombre' => 'Editar empresas', 'slug' => 'editar-empresas'],
            ['nombre' => 'Actualizar empresas', 'slug' => 'actualizar-empresas'],
            ['nombre' => 'Borrar empresas', 'slug' => 'borrar-empresas'],
        ],
        'contable/rubrocontable' => [
            ['nombre' => 'Listar rubros contables', 'slug' => 'listar-rubros-contables'],
            ['nombre' => 'Crear rubros contables', 'slug' => 'crear-rubros-contables'],
            ['nombre' => 'Editar rubros contables', 'slug' => 'editar-rubros-contables'],
            ['nombre' => 'Actualizar rubros contables', 'slug' => 'actualizar-rubros-contables'],
            ['nombre' => 'Borrar rubros contables', 'slug' => 'borrar-rubros-contables'],
        ],
        'configuracion/pais' => [
            ['nombre' => 'Listar paises', 'slug' => 'listar-paises'],
            ['nombre' => 'Crear paises', 'slug' => 'crear-paises'],
            ['nombre' => 'Editar paises', 'slug' => 'editar-paises'],
            ['nombre' => 'Actualizar paises', 'slug' => 'actualizar-paises'],
            ['nombre' => 'Borrar paises', 'slug' => 'borrar-paises'],
        ],
        'configuracion/provincia' => [
            ['nombre' => 'Listar provincias', 'slug' => 'listar-provincias'],
            ['nombre' => 'Crear provincias', 'slug' => 'crear-provincias'],
            ['nombre' => 'Editar provincias', 'slug' => 'editar-provincias'],
            ['nombre' => 'Actualizar provincias', 'slug' => 'actualizar-provincias'],
            ['nombre' => 'Borrar provincias', 'slug' => 'borrar-provincias'],
        ],
        'configuracion/localidad' => [
            ['nombre' => 'Listar localidades', 'slug' => 'listar-localidades'],
            ['nombre' => 'Crear localidades', 'slug' => 'crear-localidades'],
            ['nombre' => 'Editar localidades', 'slug' => 'editar-localidades'],
            ['nombre' => 'Actualizar localidades', 'slug' => 'actualizar-localidades'],
            ['nombre' => 'Borrar localidades', 'slug' => 'borrar-localidades'],
        ],
        'ventas/vendedor' => [
            ['nombre' => 'Listar vendedores', 'slug' => 'listar-vendedores'],
            ['nombre' => 'Crear vendedores', 'slug' => 'crear-vendedores'],
            ['nombre' => 'Editar vendedores', 'slug' => 'editar-vendedores'],
            ['nombre' => 'Actualizar vendedores', 'slug' => 'actualizar-vendedores'],
            ['nombre' => 'Borrar vendedores', 'slug' => 'borrar-vendedores'],
        ],
        'ventas/zonavta' => [
            ['nombre' => 'Listar zonas de venta', 'slug' => 'listar-zonas-de-venta'],
            ['nombre' => 'Crear zonas de venta', 'slug' => 'crear-zonas-de-venta'],
            ['nombre' => 'Editar zonas de venta', 'slug' => 'editar-zonas-de-venta'],
            ['nombre' => 'Actualizar zonas de venta', 'slug' => 'actualizar-zonas-de-venta'],
            ['nombre' => 'Borrar zonas de venta', 'slug' => 'borrar-zonas-de-venta'],
        ],
        'ventas/subzonavta' => [
            ['nombre' => 'Listar subzonas de venta', 'slug' => 'listar-subzonas-de-venta'],
            ['nombre' => 'Crear subzonas de venta', 'slug' => 'crear-subzonas-de-venta'],
            ['nombre' => 'Editar subzonas de venta', 'slug' => 'editar-subzonas-de-venta'],
            ['nombre' => 'Actualizar subzonas de venta', 'slug' => 'actualizar-subzonas-de-venta'],
            ['nombre' => 'Borrar subzonas de venta', 'slug' => 'borrar-subzonas-de-venta'],
        ],
        'ventas/condicionventa' => [
            ['nombre' => 'Listar condiciones de venta', 'slug' => 'listar-condiciones-de-venta'],
            ['nombre' => 'Crear condiciones de venta', 'slug' => 'crear-condiciones-de-venta'],
            ['nombre' => 'Editar condiciones de venta', 'slug' => 'editar-condiciones-de-venta'],
            ['nombre' => 'Actualizar condiciones de venta', 'slug' => 'actualizar-condiciones-de-venta'],
            ['nombre' => 'Borrar condiciones de venta', 'slug' => 'borrar-condiciones-de-venta'],
        ],
        'configuracion/condicioniva' => [
            ['nombre' => 'Listar condiciones de iva', 'slug' => 'listar-condiciones-de-iva'],
            ['nombre' => 'Crear condiciones de iva', 'slug' => 'crear-condiciones-de-iva'],
            ['nombre' => 'Editar condiciones de iva', 'slug' => 'editar-condiciones-de-iva'],
            ['nombre' => 'Actualizar condiciones de iva', 'slug' => 'actualizar-condiciones-de-iva'],
            ['nombre' => 'Borrar condiciones de iva', 'slug' => 'borrar-condiciones-de-iva'],
        ],
        'ventas/transporte' => [
            ['nombre' => 'Listar transportes', 'slug' => 'listar-transportes'],
            ['nombre' => 'Crear transportes', 'slug' => 'crear-transportes'],
            ['nombre' => 'Editar transportes', 'slug' => 'editar-transportes'],
            ['nombre' => 'Actualizar transportes', 'slug' => 'actualizar-transportes'],
            ['nombre' => 'Borrar transportes', 'slug' => 'borrar-transportes'],
        ],
        'caja/cuentacaja' => [
            ['nombre' => 'Listar cuentas de caja', 'slug' => 'listar-cuentas-de-caja'],
            ['nombre' => 'Ingresar cuentas de caja', 'slug' => 'crear-cuentas-de-caja'],
            ['nombre' => 'Editar cuentas de caja', 'slug' => 'editar-cuentas-de-caja'],
            ['nombre' => 'Actualizar cuentas de caja', 'slug' => 'actualizar-cuentas-de-caja'],
            ['nombre' => 'Borrar cuentas de caja', 'slug' => 'borrar-cuentas-de-caja'],
        ],
        'caja/conceptogasto' => [
            ['nombre' => 'Listar conceptos de gastos', 'slug' => 'listar-conceptos-de-gastos'],
            ['nombre' => 'Ingresar conceptos de gastos', 'slug' => 'crear-conceptos-de-gastos'],
            ['nombre' => 'Editar conceptos de gastos', 'slug' => 'editar-conceptos-de-gastos'],
            ['nombre' => 'Actualizar conceptos de gastos', 'slug' => 'actualizar-conceptos-de-gastos'],
            ['nombre' => 'Borrar conceptos de gastos', 'slug' => 'borrar-conceptos-de-gastos'],
        ],
        'compras/condicionpago' => [
            ['nombre' => 'Listar condicion de pago', 'slug' => 'listar-condicion-de-pago'],
            ['nombre' => 'Ingresar condicion de pago', 'slug' => 'crear-condicion-de-pago'],
            ['nombre' => 'Editar condicion de pago', 'slug' => 'editar-condicion-de-pago'],
            ['nombre' => 'Actualizar condicion de pago', 'slug' => 'actualizar-condicion-de-pago'],
            ['nombre' => 'Borrar condicion de pago', 'slug' => 'borrar-condicion-de-pago'],
        ],
        'compras/condicioncompra' => [
            ['nombre' => 'Listar condicion de compra', 'slug' => 'listar-condicion-de-compra'],
            ['nombre' => 'Ingresar condicion de compra', 'slug' => 'crear-condicion-de-compra'],
            ['nombre' => 'Editar condicion de compra', 'slug' => 'editar-condicion-de-compra'],
            ['nombre' => 'Actualizar condicion de compra', 'slug' => 'actualizar-condicion-de-compra'],
            ['nombre' => 'Borrar condicion de compra', 'slug' => 'borrar-condicion-de-compra'],
        ],
        'compras/condicionentrega' => [
            ['nombre' => 'Listar condicion de entrega', 'slug' => 'listar-condicion-de-entrega'],
            ['nombre' => 'Ingresar condicion de entrega', 'slug' => 'crear-condicion-de-entrega'],
            ['nombre' => 'Editar condicion de entrega', 'slug' => 'editar-condicion-de-entrega'],
            ['nombre' => 'Actualizar condicion de entrega', 'slug' => 'actualizar-condicion-de-entrega'],
            ['nombre' => 'Borrar condicion de entrega', 'slug' => 'borrar-condicion-de-entrega'],
        ],
        'compras/retencionganancia' => [
            ['nombre' => 'Listar retencion de ganancias', 'slug' => 'listar-retencion-de-ganancias'],
            ['nombre' => 'Ingresar retencion de ganancias', 'slug' => 'crear-retencion-de-ganancias'],
            ['nombre' => 'Editar retencion de ganancias', 'slug' => 'editar-retencion-de-ganancias'],
            ['nombre' => 'Actualizar retencion de ganancias', 'slug' => 'actualizar-retencion-de-ganancias'],
            ['nombre' => 'Borrar retencion de ganancias', 'slug' => 'borrar-retencion-de-ganancias'],
        ],
        'compras/retencionsuss' => [
            ['nombre' => 'Listar retencion de suss', 'slug' => 'listar-retencion-de-suss'],
            ['nombre' => 'Ingresar retencion de suss', 'slug' => 'crear-retencion-de-suss'],
            ['nombre' => 'Editar retencion de suss', 'slug' => 'editar-retencion-de-suss'],
            ['nombre' => 'Actualizar retencion de suss', 'slug' => 'actualizar-retencion-de-suss'],
            ['nombre' => 'Borrar retencion de suss', 'slug' => 'borrar-retencion-de-suss'],
        ],
        'compras/retencionIIBB' => [
            ['nombre' => 'Listar retencion de IIBB', 'slug' => 'listar-retencion-de-IIBB'],
            ['nombre' => 'Ingresar retencion de IIBB', 'slug' => 'crear-retencion-de-IIBB'],
            ['nombre' => 'Editar retencion de IIBB', 'slug' => 'editar-retencion-de-IIBB'],
            ['nombre' => 'Actualizar ingreso bruto', 'slug' => 'actualizar-retencion-de-IIBB'],
            ['nombre' => 'Borrar retencion de IIBB', 'slug' => 'borrar-retencion-de-IIBB'],
        ],
        'compras/tiposuspensionproveedor' => [
            ['nombre' => 'Listar tipos suspension proveedor', 'slug' => 'listar-tipos-suspension-proveedor'],
            ['nombre' => 'Ingresar tipos suspension proveedor', 'slug' => 'crear-tipos-suspension-proveedor'],
            ['nombre' => 'Editar tipos suspension proveedor', 'slug' => 'editar-tipos-suspension-proveedor'],
            ['nombre' => 'Actualizar tipos suspension proveedor', 'slug' => 'actualizar-tipos-suspension-proveedor'],
            ['nombre' => 'Borrar tipos suspension proveedor', 'slug' => 'borrar-tipos-suspension-proveedor'],
        ],
        'caja/banco' => [
            ['nombre' => 'Listar banco', 'slug' => 'listar-banco'],
            ['nombre' => 'Ingresar banco', 'slug' => 'crear-banco'],
            ['nombre' => 'Editar banco', 'slug' => 'editar-banco'],
            ['nombre' => 'Actualizar banco', 'slug' => 'actualizar-banco'],
            ['nombre' => 'Borrar banco', 'slug' => 'borrar-banco'],
        ],
        'ventas/formapago' => [
            ['nombre' => 'Listar formas de pago', 'slug' => 'listar-formas-de-pago'],
            ['nombre' => 'Crear formas de pago', 'slug' => 'crear-formas-de-pago'],
            ['nombre' => 'Editar formas de pago', 'slug' => 'editar-formas-de-pago'],
            ['nombre' => 'Actualizar formas de pago', 'slug' => 'actualizar-formas-de-pago'],
            ['nombre' => 'Borrar formas de pago', 'slug' => 'borrar-formas-de-pago'],
        ],
        'receptivo/idioma' => [
            ['nombre' => 'Listar idioma', 'slug' => 'listar-idioma'],
            ['nombre' => 'Ingresar idioma', 'slug' => 'crear-idioma'],
            ['nombre' => 'Editar idioma', 'slug' => 'editar-idioma'],
            ['nombre' => 'Actualizar idioma', 'slug' => 'actualizar-idioma'],
            ['nombre' => 'Borrar idioma', 'slug' => 'borrar-idioma'],
        ],
        'receptivo/guia' => [
            ['nombre' => 'Listar guia', 'slug' => 'listar-guia'],
            ['nombre' => 'Ingresar guia', 'slug' => 'crear-guia'],
            ['nombre' => 'Editar guia', 'slug' => 'editar-guia'],
            ['nombre' => 'Actualizar guia', 'slug' => 'actualizar-guia'],
            ['nombre' => 'Borrar guia', 'slug' => 'borrar-guia'],
        ],
        'compras/columna_ivacompra' => [
            ['nombre' => 'Listar columnas iva compras', 'slug' => 'listar-columna-iva-compra'],
            ['nombre' => 'Editar columnas iva compras', 'slug' => 'editar-columna-iva-compra'],
            ['nombre' => 'Actualizar columnas iva compras', 'slug' => 'actualizar-columna-iva-compra'],
            ['nombre' => 'Borrar columnas iva compras', 'slug' => 'borrar-columna-iva-compra'],
        ],
        'compras/concepto_ivacompra' => [
            ['nombre' => 'Listar conceptos iva compras', 'slug' => 'listar-concepto-iva-compra'],
            ['nombre' => 'Editar conceptos iva compras', 'slug' => 'editar-concepto-iva-compra'],
            ['nombre' => 'Actualizar conceptos iva compras', 'slug' => 'actualizar-concepto-iva-compra'],
            ['nombre' => 'Borrar conceptos iva compras', 'slug' => 'borrar-concepto-iva-compra'],
        ],
        'ventas/tipotransaccion' => [
            ['nombre' => 'Listar tipos de transacciones', 'slug' => 'listar-tipos-transacciones'],
            ['nombre' => 'Crear tipos de transacciones', 'slug' => 'crear-tipos-transacciones'],
            ['nombre' => 'Editar tipos de transacciones', 'slug' => 'editar-tipos-transacciones'],
            ['nombre' => 'Actualizar tipos de transacciones', 'slug' => 'actualizar-tipos-transacciones'],
            ['nombre' => 'Borrar tipos de transacciones', 'slug' => 'borrar-tipos-transacciones'],
        ],
        'contable/tipoasiento' => [
            ['nombre' => 'Listar tipos de asiento', 'slug' => 'listar-tipo-asiento'],
            ['nombre' => 'Ingresar tipos de asiento', 'slug' => 'crear-tipo-asiento'],
            ['nombre' => 'Editar tipos de asiento', 'slug' => 'editar-tipo-asiento'],
            ['nombre' => 'Actualizar tipos de asiento', 'slug' => 'actualizar-tipo-asiento'],
            ['nombre' => 'Borrar tipos de asiento', 'slug' => 'borrar-tipo-asiento'],
        ],
        'contable/asiento' => [
            ['nombre' => 'Listar asientos', 'slug' => 'listar-asiento'],
            ['nombre' => 'Editar asientos', 'slug' => 'editar-asiento'],
            ['nombre' => 'Actualizar asientos', 'slug' => 'actualizar-asiento'],
            ['nombre' => 'Borrar asientos', 'slug' => 'borrar-asiento'],
        ],
        'configuracion/cotizacion' => [
            ['nombre' => 'Listar cotizacion', 'slug' => 'listar-cotizacion'],
            ['nombre' => 'Ingresar cotizacion', 'slug' => 'crear-cotizacion'],
            ['nombre' => 'Editar cotizacion', 'slug' => 'editar-cotizacion'],
            ['nombre' => 'Actualizar cotizacion', 'slug' => 'actualizar-cotizacion'],
            ['nombre' => 'Borrar cotizacion', 'slug' => 'borrar-cotizacion'],
        ],
        'caja/caja' => [
            ['nombre' => 'Listar caja', 'slug' => 'listar-caja'],
            ['nombre' => 'Ingresar caja', 'slug' => 'crear-caja'],
            ['nombre' => 'Editar caja', 'slug' => 'editar-caja'],
            ['nombre' => 'Actualizar caja', 'slug' => 'actualizar-caja'],
            ['nombre' => 'Borrar caja', 'slug' => 'borrar-caja'],
        ],
        'caja/movimientocaja' => [
            ['nombre' => 'Listar movimientos caja', 'slug' => 'listar-movimientos-caja'],
            ['nombre' => 'Ingresar movimientos caja', 'slug' => 'crear-movimientos-caja'],
            ['nombre' => 'Editar movimientos caja', 'slug' => 'editar-movimientos-caja'],
            ['nombre' => 'Actualizar movimientos caja', 'slug' => 'actualizar-movimientos-caja'],
            ['nombre' => 'Borrar movimientos caja', 'slug' => 'borrar-movimientos-caja'],
        ],
        'receptivo/movil' => [
            ['nombre' => 'Listar movil', 'slug' => 'listar-movil'],
            ['nombre' => 'Ingresar movil', 'slug' => 'crear-movil'],
            ['nombre' => 'Editar movil', 'slug' => 'editar-movil'],
            ['nombre' => 'Actualizar movil', 'slug' => 'actualizar-movil'],
            ['nombre' => 'Borrar movil', 'slug' => 'borrar-movil'],
        ],
        'caja/chequera' => [
            ['nombre' => 'Listar chequera', 'slug' => 'listar-chequera'],
            ['nombre' => 'Ingresar chequera', 'slug' => 'crear-chequera'],
            ['nombre' => 'Editar chequera', 'slug' => 'editar-chequera'],
            ['nombre' => 'Actualizar chequera', 'slug' => 'actualizar-chequera'],
            ['nombre' => 'Borrar chequera', 'slug' => 'borrar-chequera'],
        ],
        'caja/cheque' => [
            ['nombre' => 'Listar cheque', 'slug' => 'listar-cheque'],
            ['nombre' => 'Ingresar cheque', 'slug' => 'crear-cheque'],
            ['nombre' => 'Editar cheque', 'slug' => 'editar-cheque'],
            ['nombre' => 'Actualizar cheque', 'slug' => 'actualizar-cheque'],
            ['nombre' => 'Borrar cheque', 'slug' => 'borrar-cheque'],
        ],
        'configuracion/tipodocumento' => [
            ['nombre' => 'Listar tipo de documento', 'slug' => 'listar-tipo-de-documento'],
            ['nombre' => 'Ingresar tipo de documento', 'slug' => 'crear-tipo-de-documento'],
            ['nombre' => 'Editar tipo de documento', 'slug' => 'editar-tipo-de-documento'],
            ['nombre' => 'Actualizar tipo de documento', 'slug' => 'actualizar-tipo-de-documento'],
            ['nombre' => 'Borrar tipo de documento', 'slug' => 'borrar-tipo-de-documento'],
        ],
        'caja/estadocheque_banco' => [
            ['nombre' => 'Listar estado cheque banco', 'slug' => 'listar-estado-cheque-banco'],
            ['nombre' => 'Ingresar estado cheque banco', 'slug' => 'crear-estado-cheque-banco'],
            ['nombre' => 'Editar estado cheque banco', 'slug' => 'editar-estado-cheque-banco'],
            ['nombre' => 'Actualizar estado cheque banco', 'slug' => 'actualizar-estado-cheque-banco'],
            ['nombre' => 'Borrar estado cheque banco', 'slug' => 'borrar-estado-cheque-banco'],
        ],
        'caja/rendicionreceptivo' => [
            ['nombre' => 'Listar rendicion receptivo', 'slug' => 'listar-rendicion-receptivo'],
            ['nombre' => 'Ingresar rendicion receptivo', 'slug' => 'crear-rendicion-receptivo'],
            ['nombre' => 'Editar rendicion receptivo', 'slug' => 'editar-rendicion-receptivo'],
            ['nombre' => 'Actualizar rendicion receptivo', 'slug' => 'actualizar-rendicion-receptivo'],
            ['nombre' => 'Borrar rendicion receptivo', 'slug' => 'borrar-rendicion-receptivo'],
        ],
        'ventas/abasto' => [
            ['nombre' => 'Lista abasto ventas', 'slug' => 'listar-abasto'],
            ['nombre' => 'Ingresa abasto ventas', 'slug' => 'crear-abasto'],
            ['nombre' => 'Edita abasto ventas', 'slug' => 'editar-abasto'],
            ['nombre' => 'Actualiza abasto ventas', 'slug' => 'actualizar-abasto'],
            ['nombre' => 'Borra abasto ventas', 'slug' => 'borrar-abasto'],
        ],
        'ventas/coeficiente' => [
            ['nombre' => 'Listar coeficiente venta', 'slug' => 'listar-coeficiente-venta'],
            ['nombre' => 'Ingresar coeficiente venta', 'slug' => 'crear-coeficiente-venta'],
            ['nombre' => 'Editar coeficiente venta', 'slug' => 'editar-coeficiente-venta'],
            ['nombre' => 'Actualiza coeficiente ventas', 'slug' => 'actualizar-coeficiente-ventas'],
            ['nombre' => 'Borrar coeficiente venta', 'slug' => 'borrar-coeficiente-venta'],
        ],
        'stock/categoria' => [
            ['nombre' => 'Listar categorias', 'slug' => 'listar-categorias'],
            ['nombre' => 'Crear categorias', 'slug' => 'crear-categorias'],
            ['nombre' => 'Editar categorias', 'slug' => 'editar-categorias'],
            ['nombre' => 'Actualizar categorias', 'slug' => 'actualizar-categorias'],
            ['nombre' => 'Borrar categorias', 'slug' => 'borrar-categorias'],
        ],
        'stock/subcategoria' => [
            ['nombre' => 'Listar subcategorias', 'slug' => 'listar-subcategorias'],
            ['nombre' => 'Crear subcategorias', 'slug' => 'crear-subcategorias'],
            ['nombre' => 'Editar subcategorias', 'slug' => 'editar-subcategorias'],
            ['nombre' => 'Actualizar subcategorias', 'slug' => 'actualizar-subcategorias'],
            ['nombre' => 'Borrar categorias', 'slug' => 'borrar-subcategorias'],
        ],
        'stock/depmae' => [
            ['nombre' => 'Listar depositos', 'slug' => 'listar-depositos'],
            ['nombre' => 'Crear depositos', 'slug' => 'crear-depositos'],
            ['nombre' => 'Editar depositos', 'slug' => 'editar-depositos'],
            ['nombre' => 'Actualizar depositos', 'slug' => 'actualizar-depositos'],
            ['nombre' => 'Borrar depositos', 'slug' => 'borrar-depositos'],
        ],
        'stock/linea' => [
            ['nombre' => 'Listar lineas', 'slug' => 'listar-lineas'],
            ['nombre' => 'Crear lineas', 'slug' => 'crear-lineas'],
            ['nombre' => 'Editar lineas', 'slug' => 'editar-lineas'],
            ['nombre' => 'Actualizar lineas', 'slug' => 'actualizar-lineas'],
            ['nombre' => 'Borrar lineas', 'slug' => 'borrar-lineas'],
        ],
        'stock/listaprecio' => [
            ['nombre' => 'Listar listas de precio', 'slug' => 'listar-listaprecio'],
            ['nombre' => 'Crear listas de precio', 'slug' => 'crear-listaprecio'],
            ['nombre' => 'Editar listas de precio', 'slug' => 'editar-listaprecio'],
            ['nombre' => 'Actualizar listas de precio', 'slug' => 'actualizar-listaprecio'],
            ['nombre' => 'Borrar listas de precio', 'slug' => 'borrar-listaprecio'],
        ],
        'stock/mventa' => [
            ['nombre' => 'Listar marcas de venta', 'slug' => 'listar-marcas-de-venta'],
            ['nombre' => 'Crear marcas de venta', 'slug' => 'crear-marcas-de-venta'],
            ['nombre' => 'Editar marcas de venta', 'slug' => 'editar-marcas-de-venta'],
            ['nombre' => 'Actualizar marcas de venta', 'slug' => 'actualizar-marcas-de-venta'],
            ['nombre' => 'Borrar marcas de venta', 'slug' => 'borrar-marcas-de-venta'],
        ],
        'stock/tipoarticulo' => [
            ['nombre' => 'Listar tipos de articulo', 'slug' => 'listar-tipo-articulo'],
            ['nombre' => 'Crear tipos de articulo', 'slug' => 'crear-tipo-articulo'],
            ['nombre' => 'Editar tipos de articulo', 'slug' => 'editar-tipo-articulo'],
            ['nombre' => 'Actualizar tipos de articulo', 'slug' => 'actualizar-tipo-articulo'],
            ['nombre' => 'Borrar tipos de articulo', 'slug' => 'borrar-tipo-articulo'],
        ],
        'stock/unidadmedida' => [
            ['nombre' => 'Listar unidades de medida', 'slug' => 'listar-unidades-de-medida'],
            ['nombre' => 'Crear unidades de medida', 'slug' => 'crear-unidades-de-medida'],
            ['nombre' => 'Editar unidades de medida', 'slug' => 'editar-unidades-de-medida'],
            ['nombre' => 'Actualizar unidades de medida', 'slug' => 'actualizar-unidades-de-medida'],
            ['nombre' => 'Borrar unidades de medida', 'slug' => 'borrar-unidades-de-medida'],
        ],
        'ventas/descuentoventa' => [
            ['nombre' => 'Lista descuento ventas', 'slug' => 'listar-descuento-ventas'],
            ['nombre' => 'Ingresa descuento ventas', 'slug' => 'crear-descuento-ventas'],
            ['nombre' => 'Edita descuento ventas', 'slug' => 'editar-descuento-ventas'],
            ['nombre' => 'Actualiza descuento ventas', 'slug' => 'actualizar-descuento-ventas'],
            ['nombre' => 'Borra descuento ventas', 'slug' => 'borrar-descuento-ventas'],
        ],
        'stock/envasesenasa' => [
            ['nombre' => 'Lista envase senasa stock', 'slug' => 'listar-envase-senasa-stock'],
            ['nombre' => 'Ingresa envase senasa stock', 'slug' => 'crear-envase-senasa-stock'],
            ['nombre' => 'Edita envase senasa stock', 'slug' => 'editar-envase-senasa-stock'],
            ['nombre' => 'Actualiza envase senasa stock', 'slug' => 'actualizar-envase-senasa-stock'],
            ['nombre' => 'Borra envase senasa stock', 'slug' => 'borrar-envase-senasa-stock'],
        ],
        'stock/codigosenasa' => [
            ['nombre' => 'Lista codigo senasa stock', 'slug' => 'listar-codigo-senasa-stock'],
            ['nombre' => 'Ingresa codigo senasa stock', 'slug' => 'crear-codigo-senasa-stock'],
            ['nombre' => 'Edita codigo senasa stock', 'slug' => 'editar-codigo-senasa-stock'],
            ['nombre' => 'Actualiza codigo senasa stock', 'slug' => 'actualizar-codigo-senasa-stock'],
            ['nombre' => 'Borra codigo senasa stock', 'slug' => 'borrar-codigo-senasa-stock'],
        ],
        'produccion/tipoproduccion' => [
            ['nombre' => 'Lista tipo produccion', 'slug' => 'listar-tipo-produccion'],
            ['nombre' => 'Ingresa tipo produccion', 'slug' => 'crear-tipo-produccion'],
            ['nombre' => 'Edita tipo produccion', 'slug' => 'editar-tipo-produccion'],
            ['nombre' => 'Actualiza tipo produccion', 'slug' => 'actualizar-tipo-produccion'],
            ['nombre' => 'Borra tipo produccion', 'slug' => 'borrar-tipo-produccion'],
        ],
        'produccion/sectorsellado' => [
            ['nombre' => 'Lista sector sellado', 'slug' => 'listar-sector-sellado'],
            ['nombre' => 'Ingresa sector sellado', 'slug' => 'crear-sector-sellado'],
            ['nombre' => 'Edita sector sellado', 'slug' => 'editar-sector-sellado'],
            ['nombre' => 'Actualiza sector sellado', 'slug' => 'actualizar-sector-sellado'],
            ['nombre' => 'Borra sector sellado', 'slug' => 'borrar-sector-sellado'],
        ],
        'produccion/salaproduccion' => [
            ['nombre' => 'Lista sala produccion', 'slug' => 'listar-sala-produccion'],
            ['nombre' => 'Ingresa sala produccion', 'slug' => 'crear-sala-produccion'],
            ['nombre' => 'Edita sala produccion', 'slug' => 'editar-sala-produccion'],
            ['nombre' => 'Actualiza sala produccion', 'slug' => 'actualizar-sala-produccion'],
            ['nombre' => 'Borra sala produccion', 'slug' => 'borrar-sala-produccion'],
        ],
        'stock/usoarticulo' => [
            ['nombre' => 'Listar uso de articulos', 'slug' => 'listar-uso-de-articulos'],
            ['nombre' => 'Crear uso de articulos', 'slug' => 'crear-uso-de-articulos'],
            ['nombre' => 'Editar uso de articulos', 'slug' => 'editar-uso-de-articulos'],
            ['nombre' => 'Actualizar uso de articulos', 'slug' => 'actualizar-uso-de-articulos'],
            ['nombre' => 'Borrar uso de articulos', 'slug' => 'borrar-uso-de-articulos'],
        ],
        'configuracion/padron_iibb' => [
            ['nombre' => 'Lista padron iibb', 'slug' => 'listar-padron-iibb'],
            ['nombre' => 'Ingresa padron iibb', 'slug' => 'crear-padron-iibb'],
            ['nombre' => 'Edita padron iibb', 'slug' => 'editar-padron-iibb'],
            ['nombre' => 'Actualiza padron iibb', 'slug' => 'actualizar-padron-iibb'],
            ['nombre' => 'Borra padron iibb', 'slug' => 'borrar-padron-iibb'],
        ],
        'ventas/puntoventa' => [
            ['nombre' => 'Listar puntos de venta', 'slug' => 'listar-puntos-de-venta'],
            ['nombre' => 'Crear puntos de venta', 'slug' => 'crear-puntos-de-venta'],
            ['nombre' => 'Editar puntos de venta', 'slug' => 'editar-puntos-de-venta'],
            ['nombre' => 'Actualizar puntos de venta', 'slug' => 'actualizar-puntos-de-venta'],
        ],
        'configuracion/salida' => [
            ['nombre' => 'Listar salidas', 'slug' => 'listar-salidas'],
            ['nombre' => 'Crear salidas', 'slug' => 'crear-salidas'],
            ['nombre' => 'Editar salidas', 'slug' => 'editar-salidas'],
            ['nombre' => 'Actualizar salidas', 'slug' => 'actualizar-salidas'],
            ['nombre' => 'Borrar salidas', 'slug' => 'borrar-salidas'],
        ],
        'ventas/factura' => [
            ['nombre' => 'Lista factura', 'slug' => 'listar-factura'],
            ['nombre' => 'Ingresa factura', 'slug' => 'crear-factura'],
            ['nombre' => 'Edita factura', 'slug' => 'editar-factura'],
            ['nombre' => 'Actualiza factura', 'slug' => 'actualizar-factura'],
        ],
        'configuracion/modeloetiqueta' => [
            ['nombre' => 'Listar modelos de etiquetas', 'slug' => 'listar-modeloetiqueta'],
            ['nombre' => 'Ingresar modelos de etiquetas', 'slug' => 'crear-modeloetiqueta'],
            ['nombre' => 'Editar modelos de etiquetas', 'slug' => 'editar-modeloetiqueta'],
            ['nombre' => 'Actualizar modelos de etiquetas', 'slug' => 'actualizar-modeloetiqueta'],
            ['nombre' => 'Borrar modelos de etiquetas', 'slug' => 'borrar-modeloetiqueta'],
        ],
        'configuracion/oficinacompra' => [
            ['nombre' => 'Listar oficina de compras', 'slug' => 'listar-oficina-de-compras'],
            ['nombre' => 'Ingresar oficina de compras', 'slug' => 'crear-oficina-de-compras'],
            ['nombre' => 'Editar oficina de compras', 'slug' => 'editar-oficina-de-compras'],
            ['nombre' => 'Actualizar oficina de compras', 'slug' => 'actualizar-oficina-de-compras'],
            ['nombre' => 'Borrar oficina de compras', 'slug' => 'borrar-oficina-de-compras'],
        ],
        'caja/interbanking' => [
            ['nombre' => 'Listar saldo cuenta interbanking', 'slug' => 'listar-saldo-cuenta-interbanking'],
            ['nombre' => 'Ver movimientos cuenta interbanking', 'slug' => 'ver-movimientos-cuenta-interbanking'],
        ],
        'caja/estacionamiento/configuracion-puntoventa' => [
            ['nombre' => 'Actualizar configuracion puntoventa estacionamiento', 'slug' => 'actualizar-configuracion-puntoventa-estacionamiento'],
        ],
        'contable/mayor-plano-cuenta' => [
            ['nombre' => 'Listar mayor plano por cuenta contable', 'slug' => 'listar-mayor-plano-cuenta'],
        ],
        'stock/serigrafia' => [
            ['nombre' => 'Listar serigrafias', 'slug' => 'listar-serigrafias'],
            ['nombre' => 'Ingresar serigrafias', 'slug' => 'crear-serigrafias'],
            ['nombre' => 'Editar serigrafias', 'slug' => 'editar-serigrafias'],
            ['nombre' => 'Actualizar serigrafias', 'slug' => 'actualizar-serigrafias'],
            ['nombre' => 'Borrar serigrafias', 'slug' => 'borrar-serigrafias'],
        ],
        'contable/sumas-saldos' => [
            ['nombre' => 'Listar balance de sumas y saldos', 'slug' => 'listar-sumas-saldos'],
        ],
        'configuracion/ai-decisiones' => [
            ['nombre' => 'Listar gobernanza IA (decisiones)', 'slug' => 'listar-ai-decisiones'],
        ],
        'ticket/ticket' => [
            ['nombre' => 'Borrar ticket', 'slug' => 'borrar-ticket'],
        ],
        'compras/comprobante-proveedor' => [
            ['nombre' => 'Listar precarga proveedores', 'slug' => 'listar-precarga-proveedores'],
            ['nombre' => 'Ingresar precarga proveedores', 'slug' => 'crear-precarga-proveedores'],
            ['nombre' => 'Editar precarga proveedores', 'slug' => 'editar-precarga-proveedores'],
            ['nombre' => 'Actualizar precarga proveedores', 'slug' => 'actualizar-precarga-proveedores'],
            ['nombre' => 'Borrar precarga proveedores', 'slug' => 'borrar-precarga-proveedores'],
        ],
        'ventas/gastronomia/saneamiento-turno' => [
            ['nombre' => 'Gestionar saneamiento turno gastronomia', 'slug' => 'gestionar-saneamiento-turno-gastronomia'],
            ['nombre' => 'Ejecutar saneamiento turno gastronomia', 'slug' => 'ejecutar-saneamiento-turno-gastronomia'],
        ],
        'compras/kpi' => [
            ['nombre' => 'Tablero KPIs de Compras', 'slug' => 'listar-kpi-compras'],
        ],
    ];

    /** @var list<string> */
    private array $slugsCreados = [];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
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

        // Permisos de saneamiento quedaron colgados del menú «Jornada»; reubicar.
        $this->reubicarPermisosMenu(
            'ventas/gastronomia/saneamiento-turno',
            ['gestionar-saneamiento-turno-gastronomia', 'ejecutar-saneamiento-turno-gastronomia']
        );

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache(array_values(array_unique($rolIdsGlobal)));
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        // Solo elimina slugs que esta migración habría creado (no existían antes).
        // No revierte vínculos menu_id de permisos históricos.
        $slugsNuevos = [
            'listar-tipos-suspension-proveedor', 'crear-tipos-suspension-proveedor', 'editar-tipos-suspension-proveedor',
            'actualizar-tipos-suspension-proveedor', 'borrar-tipos-suspension-proveedor',
            'listar-caja', 'crear-caja', 'editar-caja', 'actualizar-caja', 'borrar-caja',
            'listar-movimientos-caja', 'crear-movimientos-caja', 'editar-movimientos-caja',
            'actualizar-movimientos-caja', 'borrar-movimientos-caja',
            'listar-movil', 'crear-movil', 'editar-movil', 'actualizar-movil', 'borrar-movil',
            'listar-chequera', 'crear-chequera', 'editar-chequera', 'actualizar-chequera', 'borrar-chequera',
            'listar-cheque', 'crear-cheque', 'editar-cheque', 'actualizar-cheque', 'borrar-cheque',
            'listar-tipo-de-documento', 'crear-tipo-de-documento', 'editar-tipo-de-documento',
            'actualizar-tipo-de-documento', 'borrar-tipo-de-documento',
            'listar-estado-cheque-banco', 'crear-estado-cheque-banco', 'editar-estado-cheque-banco',
            'actualizar-estado-cheque-banco', 'borrar-estado-cheque-banco',
            'listar-rendicion-receptivo', 'crear-rendicion-receptivo', 'editar-rendicion-receptivo',
            'actualizar-rendicion-receptivo', 'borrar-rendicion-receptivo',
            'listar-coeficiente-venta', 'crear-coeficiente-venta', 'editar-coeficiente-venta', 'borrar-coeficiente-venta',
            'listar-oficina-de-compras', 'crear-oficina-de-compras', 'editar-oficina-de-compras',
            'actualizar-oficina-de-compras', 'borrar-oficina-de-compras',
            'listar-saldo-cuenta-interbanking', 'ver-movimientos-cuenta-interbanking',
            'actualizar-configuracion-puntoventa-estacionamiento',
            'listar-serigrafias', 'crear-serigrafias', 'editar-serigrafias', 'actualizar-serigrafias', 'borrar-serigrafias',
            'borrar-ticket',
            'listar-precarga-proveedores', 'crear-precarga-proveedores', 'editar-precarga-proveedores',
            'actualizar-precarga-proveedores', 'borrar-precarga-proveedores',
            'listar-kpi-compras',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugsNuevos)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
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
                'nombre' => $nombre,
                'updated_at' => now(),
            ];
            // Solo pisa menu_id si estaba vacío o el destino es el menú canónico del slug.
            if ($currMenu === 0 || $currMenu === $menuId) {
                $payload['menu_id'] = $menuId;
            }
            DB::table('permiso')->where('id', $permisoId)->update($payload);
        }

        return $permisoId;
    }

    /**
     * @param  list<string>  $slugs
     */
    private function reubicarPermisosMenu(string $menuUrl, array $slugs): void
    {
        $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
        if ($menuId <= 0 || $slugs === []) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', $slugs)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $padreMenuId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
        $rolIds = $this->resolverRolIds($menuId, array_map(
            fn ($slug) => ['nombre' => $slug, 'slug' => $slug],
            $slugs
        ));
        $this->asignarRoles($menuId, $padreMenuId, $permisoIds, $rolIds);
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

        // Roles que ya tenían algún permiso de la familia (o legacy)
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
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
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
