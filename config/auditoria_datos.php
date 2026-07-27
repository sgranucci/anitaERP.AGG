<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel de auditoría de datos (tabla audits / owen-it)
    |--------------------------------------------------------------------------
    |
    | Lectura del historial de cambios Eloquent. La grabación la controla
    | AUDITING_ENABLED (config/audit.php). Este flag solo apaga el panel.
    |
    */

    'panel_habilitado' => filter_var(env('AUDITORIA_DATOS_PANEL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    /** Segundos de cache del catálogo de modelos (distinct auditable_type). */
    'catalogo_cache_segundos' => max(60, (int) env('AUDITORIA_DATOS_CATALOGO_CACHE', 3600)),

    /**
     * Link al ABM en modo consulta (vista=consulta, sin menú).
     * clave = FQCN; ruta = name de route Laravel; permisos = cualquiera alcanza.
     *
     * @var array<string, array{ruta: string, param?: string, permisos?: list<string>}>
     */
    'abm_consulta' => [
        'App\\Models\\Compras\\Proveedor' => [
            'ruta' => 'editar_proveedor',
            'permisos' => ['editar-proveedor', 'listar-proveedor'],
        ],
        'App\\Models\\Ventas\\Cliente' => [
            'ruta' => 'editar_cliente',
            'permisos' => ['editar-clientes', 'listar-clientes'],
        ],
        'App\\Models\\Stock\\Articulo' => [
            'ruta' => 'editar_articulo',
            'permisos' => ['editar-articulos', 'listar-articulos'],
        ],
        'App\\Models\\Compras\\Ordencompra' => [
            'ruta' => 'editar_ordencompra',
            'permisos' => ['editar-ordencompra', 'listar-ordencompra'],
        ],
        'App\\Models\\Compras\\Requisicion' => [
            'ruta' => 'editar_requisicion',
            'permisos' => ['editar-requisicion', 'listar-requisicion'],
        ],
        'App\\Models\\Compras\\Comprobante_Proveedor' => [
            'ruta' => 'editar_comprobante_proveedor',
            'permisos' => ['editar-comprobante-proveedor', 'listar-comprobante-proveedor'],
        ],
        'App\\Models\\Stock\\MovimientoStock' => [
            'ruta' => 'editar_movimientostock',
            'permisos' => ['editar-movimientos-de-stock', 'listar-movimientos-de-stock'],
        ],
        'App\\Models\\Caja\\Cuentacaja' => [
            'ruta' => 'editar_cuentacaja',
            'permisos' => ['editar-cuentacaja', 'listar-cuentacaja'],
        ],
        'App\\Models\\Seguridad\\Usuario' => [
            'ruta' => 'editar_usuario',
            'permisos' => ['editar-usuario', 'listar-usuario'],
        ],
        'App\\Models\\Contable\\Asiento' => [
            'ruta' => 'editar_asiento',
            'permisos' => ['editar-asiento', 'listar-asiento'],
        ],
        'App\\Models\\Presupuesto\\Capex' => [
            'ruta' => 'editar_capex',
            'permisos' => ['editar-capex', 'listar-capex'],
        ],
    ],

    /**
     * Columnas para buscar un registro sin saber el id interno
     * (código, nombre, fantasia, etc.). Si el modelo no está listado,
     * se usan columnas genéricas existentes en la tabla.
     *
     * @var array<string, list<string>>
     */
    'busqueda_registro' => [
        'App\\Models\\Compras\\Proveedor' => ['codigo', 'nombre', 'fantasia', 'nroinscripcion', 'email'],
        'App\\Models\\Ventas\\Cliente' => ['codigo', 'nombre', 'fantasia', 'email'],
        'App\\Models\\Stock\\Articulo' => ['sku', 'descripcion'],
        'App\\Models\\Seguridad\\Usuario' => ['usuario', 'nombre', 'email'],
        'App\\Models\\Caja\\Cuentacaja' => ['codigo', 'nombre'],
        'App\\Models\\Compras\\Ordencompra' => ['id'],
        'App\\Models\\Compras\\Requisicion' => ['id'],
        'App\\Models\\Ventas\\Venta' => ['id'],
        'App\\Models\\Contable\\Asiento' => ['id'],
        'App\\Models\\Presupuesto\\Capex' => ['id', 'nombre', 'codigo'],
    ],

    /**
     * Favoritos iniciales (semilla). La primera vez que un usuario abre el panel
     * se copian a usuario_auditoria_favorito; después se gestionan con la chincheta
     * en anitaERP (como la barra de tareas).
     *
     * @var array<string, array{etiqueta: string, tabla?: string, modulo?: string}>
     */
    'favoritos' => [
        'App\\Models\\Compras\\Proveedor' => [
            'etiqueta' => 'Proveedor',
            'tabla' => 'proveedor',
            'modulo' => 'Compras',
        ],
        'App\\Models\\Ventas\\Cliente' => [
            'etiqueta' => 'Cliente',
            'tabla' => 'cliente',
            'modulo' => 'Ventas',
        ],
        'App\\Models\\Stock\\Articulo' => [
            'etiqueta' => 'Artículo',
            'tabla' => 'articulo',
            'modulo' => 'Stock',
        ],
        'App\\Models\\Compras\\Ordencompra' => [
            'etiqueta' => 'Orden de compra',
            'tabla' => 'ordencompra',
            'modulo' => 'Compras',
        ],
        'App\\Models\\Compras\\Requisicion' => [
            'etiqueta' => 'Requisición',
            'tabla' => 'requisicion',
            'modulo' => 'Compras',
        ],
        'App\\Models\\Compras\\Comprobante_Proveedor' => [
            'etiqueta' => 'Comprobante proveedor',
            'tabla' => 'comprobante_proveedor',
            'modulo' => 'Compras',
        ],
        'App\\Models\\Ventas\\Venta' => [
            'etiqueta' => 'Venta / factura',
            'tabla' => 'venta',
            'modulo' => 'Ventas',
        ],
        'App\\Models\\Stock\\MovimientoStock' => [
            'etiqueta' => 'Movimiento de stock',
            'tabla' => 'movimientostock',
            'modulo' => 'Stock',
        ],
        'App\\Models\\Caja\\Cuentacaja' => [
            'etiqueta' => 'Cuenta de caja',
            'tabla' => 'cuentacaja',
            'modulo' => 'Caja',
        ],
        'App\\Models\\Seguridad\\Usuario' => [
            'etiqueta' => 'Usuario',
            'tabla' => 'usuario',
            'modulo' => 'Seguridad',
        ],
        'App\\Models\\Contable\\Asiento' => [
            'etiqueta' => 'Asiento contable',
            'tabla' => 'asiento',
            'modulo' => 'Contable',
        ],
        'App\\Models\\Presupuesto\\Capex' => [
            'etiqueta' => 'CAPEX',
            'tabla' => 'capex',
            'modulo' => 'Presupuesto',
        ],
    ],

];
