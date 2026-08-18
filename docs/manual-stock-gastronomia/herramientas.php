<?php

/**
 * Herramientas del manual — Stock gastronómico, fórmulas e insumos.
 */
$barraListado = 'Barra superior de la tarjeta (card-header)';
$columnaAcciones = 'Columna Acciones de cada fila';
$sobreGrilla = 'Barra de exportación sobre la tabla';
$grillaHijos = 'Grilla de hijos de la fórmula';
$footerForm = 'Pie del formulario (card-footer)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Manual / Guía del módulo',
            'ubicacion' => 'Esquina superior derecha del listado (ícono libro)',
            'accion' => 'Abre este manual de usuario en una pestaña nueva.',
            'permiso' => 'Usuario autenticado',
        ],
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $barraListado,
            'accion' => 'Abre formulario de alta (fórmula o configuración según pantalla).',
            'permiso' => 'crear-formula-articulo / crear-configuracion-puntoventa-gastronomia',
        ],
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $barraListado . ' — botón Filtros',
            'accion' => 'Despliega panel con criterios por campo, condición y valor.',
            'permiso' => 'Permiso listar del módulo',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo filtro_valor + lupa',
            'accion' => 'Panel cerrado: busca en todos los campos. Panel abierto: aplica criterios del panel.',
            'permiso' => 'Permiso listar del módulo',
        ],
        [
            'herramienta' => 'Limpiar filtros',
            'ubicacion' => 'Junto al botón Filtros (si hay criterios activos)',
            'accion' => 'Recarga el listado sin parámetros.',
            'permiso' => 'Permiso listar del módulo',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta el listado con los filtros activos (no solo la página visible).',
            'permiso' => 'Permiso listar del módulo',
        ],
    ],
    'formula_listado' => [
        [
            'herramienta' => 'Editar / Consultar',
            'ubicacion' => $columnaAcciones . ' — lápiz',
            'accion' => 'Abre cabecera y grilla de hijos de la fórmula.',
            'permiso' => 'editar-formula-articulo',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones . ' — cruz roja',
            'accion' => 'Elimina la fórmula (verifique artículos vinculados).',
            'permiso' => 'borrar-formula-articulo',
        ],
        [
            'herramienta' => 'Sincronizar desde Anita',
            'ubicacion' => $barraListado,
            'accion' => 'Importa/actualiza fórmulas legacy; alternativa CLI en timeout.',
            'permiso' => 'actualizar-formula-articulo',
        ],
        [
            'herramienta' => 'Vincular artículos por código',
            'ubicacion' => $barraListado,
            'accion' => 'Empareja fórmulas con SKU V#### y actualiza articulo.formula.',
            'permiso' => 'actualizar-formula-articulo',
        ],
        [
            'herramienta' => 'Consulta modal (desde artículo)',
            'ubicacion' => 'Stock → Artículos — botón fórmula',
            'accion' => 'Muestra receta readonly si articulo.formula está cargado.',
            'permiso' => 'listar-formula-articulo / listar-articulos',
        ],
    ],
    'formula_form' => [
        [
            'herramienta' => 'Cantidad unidad',
            'ubicacion' => 'Cabecera — cantidadunidad',
            'accion' => 'Unidades de venta que representa la receta (habitual 1).',
            'permiso' => 'crear-formula-articulo / actualizar-formula-articulo',
        ],
        [
            'herramienta' => 'Agregar renglón hijo',
            'ubicacion' => 'Sobre la grilla de hijos',
            'accion' => 'Sumar insumo, subfórmula u opcional.',
            'permiso' => 'actualizar-formula-articulo',
        ],
        [
            'herramienta' => 'Consulta artículo / subfórmula',
            'ubicacion' => $grillaHijos . ' — lupa',
            'accion' => 'Modal artículo o búsqueda de fórmulas hijas.',
            'permiso' => 'editar-formula-articulo',
        ],
        [
            'herramienta' => 'Costos última compra',
            'ubicacion' => 'Cabecera o botón en grilla',
            'accion' => 'Refresca columna costo informativo por insumo.',
            'permiso' => 'editar-formula-articulo',
        ],
        [
            'herramienta' => 'Artículos compra por insumo',
            'ubicacion' => $grillaHijos . ' — icono modal',
            'accion' => 'Lista SKUs de compra cuyo alternativo apunta al insumo.',
            'permiso' => 'listar-formula-articulo / editar-formula-articulo',
        ],
        [
            'herramienta' => 'Opcional / Ord. opcional',
            'ubicacion' => $grillaHijos . ' — columnas Sí/No y orden',
            'accion' => 'Define variantes excluyentes para el POS.',
            'permiso' => 'actualizar-formula-articulo',
        ],
        [
            'herramienta' => 'Guardar / Actualizar',
            'ubicacion' => $footerForm,
            'accion' => 'Persiste cabecera e hijos; valida cantidades y referencias.',
            'permiso' => 'actualizar-formula-articulo',
        ],
    ],
    'config_pv_gastronomia' => [
        [
            'herramienta' => 'Depósito artículos facturados',
            'ubicacion' => 'Formulario — deposito_venta_id (modal depósito)',
            'accion' => 'Depósito donde se descuenta el SKU vendido al facturar.',
            'permiso' => 'editar-configuracion-puntoventa-gastronomia',
        ],
        [
            'herramienta' => 'Depósito descuento insumos',
            'ubicacion' => 'Formulario — deposito_insumos_id (modal depósito)',
            'accion' => 'Depósito donde se descuentan insumos de fórmula.',
            'permiso' => 'editar-configuracion-puntoventa-gastronomia',
        ],
        [
            'herramienta' => 'Identificador PC',
            'ubicacion' => 'Cabecera configuración',
            'accion' => 'Debe coincidir con la terminal que factura (IP/hostname).',
            'permiso' => 'editar-configuracion-puntoventa-gastronomia',
        ],
        [
            'herramienta' => 'Tipos de transacción',
            'ubicacion' => 'Formulario — tipos factura / NC',
            'accion' => 'Definen operacionstock de ventas (salida factura, entrada NC).',
            'permiso' => 'editar-configuracion-puntoventa-gastronomia',
        ],
        [
            'herramienta' => 'Guardar / Actualizar',
            'ubicacion' => $footerForm,
            'accion' => 'Graba cfg PV; afecta facturas nuevas de esa terminal.',
            'permiso' => 'actualizar-configuracion-puntoventa-gastronomia',
        ],
    ],
    'insumos_reporte' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Formulario filtros — botón Consultar',
            'accion' => 'Genera grilla de insumos por día según tipo artículo y fechas jornada.',
            'permiso' => 'listar-insumos-tipoarticulo-gastronomia',
        ],
        [
            'herramienta' => 'Export PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta reporte con filtros activos.',
            'permiso' => 'listar-insumos-tipoarticulo-gastronomia',
        ],
    ],
    'articulos_vendidos' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Formulario filtros — botón Consultar',
            'accion' => 'Lista unidades vendidas por SKU en rango jornada.',
            'permiso' => 'listar-articulos-vendidos-gastronomia',
        ],
        [
            'herramienta' => 'Detalle facturas',
            'ubicacion' => 'Columna acciones / modal por artículo',
            'accion' => 'API facturas del período para el SKU.',
            'permiso' => 'listar-articulos-vendidos-gastronomia',
        ],
        [
            'herramienta' => 'Detalle movimientos',
            'ubicacion' => 'Columna acciones / modal por artículo',
            'accion' => 'Movimientos stock vinculados (ítem + insumos por venta_emision_id).',
            'permiso' => 'listar-articulos-vendidos-gastronomia',
        ],
        [
            'herramienta' => 'Export PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta listado con filtros activos.',
            'permiso' => 'listar-articulos-vendidos-gastronomia',
        ],
    ],
];
