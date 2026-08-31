<?php

/**
 * Herramientas del manual — Pedidos y Facturación (Ventas).
 */
$barraSuperior = 'Barra superior del listado';
$toolbarListado = 'Toolbar del listado (filtros y exportar)';
$barraEdicion = 'Barra de herramientas en edición del pedido';
$columnaAcciones = 'Columna Acciones de la grilla';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Búsqueda',
            'ubicacion' => $barraSuperior,
            'accion' => 'Filtra por texto en campos del pedido.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Filtros avanzados',
            'ubicacion' => 'Botón Filtros',
            'accion' => 'Estado (Pendiente/Facturado/Suspendido), reparto, fecha de entrega.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $toolbarListado,
            'accion' => 'Abre pantalla de alta de pedido.',
            'permiso' => 'crear-pedidos',
        ],
        [
            'herramienta' => 'Cierre de pedidos',
            'ubicacion' => $toolbarListado,
            'accion' => 'Cierre masivo por fecha y motivo.',
            'permiso' => 'cierre-de-pedidos',
        ],
        [
            'herramienta' => 'Configura salida',
            'ubicacion' => $toolbarListado,
            'accion' => 'Define impresora/comando para listado físico.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Exportar listado',
            'ubicacion' => 'Sobre la grilla',
            'accion' => 'PDF / Excel / CSV de todos los pedidos filtrados.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Abre el pedido para pesada o facturación.',
            'permiso' => 'editar-pedidos',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Solo pedidos en estado Pendiente.',
            'permiso' => 'borrar-pedidos',
        ],
    ],
    'listado_pedidos' => [
        [
            'herramienta' => 'Listar el pedido (impresora)',
            'ubicacion' => 'Icono impresora en acciones',
            'accion' => 'Genera PDF y lo envía a la impresora configurada del usuario.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Listar el pedido en PDF',
            'ubicacion' => 'Icono PDF rojo en acciones',
            'accion' => 'Descarga o abre el PDF en pantalla.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Facturar reparto',
            'ubicacion' => 'Corte amarillo de reparto',
            'accion' => 'Factura todos los pedidos pesados del reparto e imprime el lote.',
            'permiso' => 'facturar-reparto-pedidos',
        ],
    ],
    'edicion_pedido' => [
        [
            'herramienta' => 'Pesada',
            'ubicacion' => $barraEdicion,
            'accion' => 'Abre modal de pesada con lectura QR.',
            'permiso' => 'editar-pedidos',
        ],
        [
            'herramienta' => 'Factura',
            'ubicacion' => $barraEdicion,
            'accion' => 'Abre modal de facturación (solo si no está Facturado ni Suspendido).',
            'permiso' => 'editar-pedidos',
        ],
        [
            'herramienta' => 'Suspender / Activar pedido',
            'ubicacion' => $barraEdicion,
            'accion' => 'Alterna entre Pendiente y Suspendido sin borrar datos.',
            'permiso' => 'editar-pedidos',
        ],
        [
            'herramienta' => 'Listar Pedido (PDF)',
            'ubicacion' => $barraEdicion,
            'accion' => 'Genera PDF del pedido.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Listar Factura',
            'ubicacion' => $barraEdicion,
            'accion' => 'Visible tras facturar; reimprime comprobante.',
            'permiso' => 'listar-factura',
        ],
        [
            'herramienta' => 'Cuenta Corriente',
            'ubicacion' => $barraEdicion,
            'accion' => 'Consulta CC del cliente en pestaña nueva.',
            'permiso' => 'listar-pedidos',
        ],
        [
            'herramienta' => 'Guardar',
            'ubicacion' => $barraEdicion,
            'accion' => 'Graba cambios (no disponible si ya está Facturado).',
            'permiso' => 'editar-pedidos',
        ],
    ],
    'conceptos_venta' => [
        [
            'herramienta' => 'Nuevo concepto',
            'ubicacion' => 'Toolbar del listado',
            'accion' => 'Alta de concepto con precio, IVA, cuenta y plantilla.',
            'permiso' => 'crear-conceptos-venta',
        ],
        [
            'herramienta' => 'Solapa Tags',
            'ubicacion' => 'Formulario del concepto',
            'accion' => 'Define claves @tag@, tipo, origen y opciones de lista.',
            'permiso' => 'editar-conceptos-venta',
        ],
        [
            'herramienta' => 'Detectar tags',
            'ubicacion' => 'Solapa Tags',
            'accion' => 'Genera filas a partir de @clave@ encontrados en la plantilla.',
            'permiso' => 'editar-conceptos-venta',
        ],
        [
            'herramienta' => 'Exportar listado',
            'ubicacion' => 'Sobre la grilla',
            'accion' => 'PDF / Excel / CSV de conceptos filtrados.',
            'permiso' => 'listar-conceptos-venta',
        ],
    ],
    'contratos_venta' => [
        [
            'herramienta' => 'Nuevo abono',
            'ubicacion' => 'Toolbar del listado',
            'accion' => 'Alta de contrato cliente + concepto + vigencia.',
            'permiso' => 'crear-contratos-venta',
        ],
        [
            'herramienta' => 'Datos fijos (tags)',
            'ubicacion' => 'Formulario del abono',
            'accion' => 'Completa valores que no cambian cada período (dominio, patente…).',
            'permiso' => 'editar-contratos-venta',
        ],
        [
            'herramienta' => 'Prefill / Facturar',
            'ubicacion' => 'Acciones del abono',
            'accion' => 'Prepara el facturador con cliente, concepto y tags.',
            'permiso' => 'editar-contratos-venta',
        ],
        [
            'herramienta' => 'Histórico de períodos',
            'ubicacion' => 'Detalle del abono',
            'accion' => 'Consulta qué períodos ya fueron facturados.',
            'permiso' => 'listar-contratos-venta',
        ],
    ],
    'cola_contratos_venta' => [
        [
            'herramienta' => 'Consultar cola',
            'ubicacion' => 'Filtros de la pantalla',
            'accion' => 'Lista períodos pendientes según vigencia y periodicidad.',
            'permiso' => 'listar-contrato-venta-cola',
        ],
        [
            'herramienta' => 'Facturar selección',
            'ubicacion' => 'Toolbar / acciones',
            'accion' => 'Envía abonos seleccionados al facturador con prefill.',
            'permiso' => 'facturar-contrato-venta-cola',
        ],
    ],
];
