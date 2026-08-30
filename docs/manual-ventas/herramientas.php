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
];
