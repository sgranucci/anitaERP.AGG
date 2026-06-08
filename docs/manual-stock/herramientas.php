<?php

/**
 * Herramientas del manual — Recuento de inventario (Stock).
 */
$barraListado = 'Barra superior de la tarjeta (card-header)';
$columnaAcciones = 'Columna derecha de cada fila en la grilla';
$sobreGrilla = 'Barra de botones sobre la grilla (btn-app)';
$grillaLineas = 'Grilla Líneas de conteo';
$panelCierre = 'Panel «Cierre de inventario — modo de ajuste» (pantalla Ver)';

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
            'accion' => 'Abre el formulario de alta de recuento (stock/recuento/crear).',
            'permiso' => 'crear-recuento',
        ],
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $barraListado . ' — botón Filtros',
            'accion' => 'Despliega panel con criterios por campo (código, fecha, depósito, estado, etc.).',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo filtro_valor + lupa',
            'accion' => 'Con panel cerrado: busca en todos los campos (tolera errores de tipeo). Con panel abierto: aplica criterios del panel.',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'Limpiar filtros',
            'ubicacion' => 'Junto al botón Filtros (cuando hay criterios activos)',
            'accion' => 'Recarga el listado sin parámetros de filtro.',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta el listado respetando filtros activos.',
            'permiso' => 'listar-recuento',
        ],
    ],
    'recuento_listado' => [
        [
            'herramienta' => 'Ver detalle',
            'ubicacion' => $columnaAcciones . ' (ícono ojo)',
            'accion' => 'Abre ficha de consulta con historial, archivos y opciones de cierre.',
            'permiso' => 'ver-recuento',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones . ' (ícono lápiz)',
            'accion' => 'Formulario de edición si el estado es PENDIENTE o SUSPENDIDO.',
            'permiso' => 'editar-recuento',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones . ' (ícono cruz roja)',
            'accion' => 'Borrado lógico; solo en estado PENDIENTE.',
            'permiso' => 'borrar-recuento',
        ],
    ],
    'recuento_form_cabecera' => [
        [
            'herramienta' => 'Empresa',
            'ubicacion' => 'Cabecera izquierda',
            'accion' => 'Empresa de la sesión (solo lectura en edición).',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Depósito',
            'ubicacion' => 'Cabecera — consulta modal',
            'accion' => 'Define el depósito a inventariar. Obligatorio; valida autorización del usuario.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Fecha del recuento',
            'ubicacion' => 'Cabecera derecha',
            'accion' => 'Fecha de negocio del conteo; influye en el modo de cierre a fecha del recuento.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Comentario',
            'ubicacion' => 'Cabecera derecha',
            'accion' => 'Texto libre (motivo del conteo, referencia interna).',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Guardar',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Valida cabecera y al menos una línea; crea o actualiza el recuento.',
            'permiso' => 'crear-recuento / actualizar-recuento',
        ],
    ],
    'recuento_form_items' => [
        [
            'herramienta' => 'Recuento aleatorio',
            'ubicacion' => 'Sobre la grilla — botón con ícono random',
            'accion' => 'Sortea N artículos del depósito y reemplaza/agrega líneas.',
            'permiso' => 'recuento-aleatorio',
        ],
        [
            'herramienta' => 'Importar Excel',
            'ubicacion' => 'Sobre la grilla — botón Excel',
            'accion' => 'Abre modal para preview/importación rápida desde planilla.',
            'permiso' => 'importar-recuento',
        ],
        [
            'herramienta' => 'Validación artículo único',
            'ubicacion' => $grillaLineas . ' — al cargar SKU, modal o importación',
            'accion' => 'Impide repetir el mismo artículo: aviso inmediato, resalta la línea existente y enfoca cantidad contada.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Consulta artículo',
            'ubicacion' => $grillaLineas . ' — lupa por fila',
            'accion' => 'Modal de búsqueda; al seleccionar carga SKU, descripción, UM y saldo dep.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Movimientos',
            'ubicacion' => $grillaLineas . ' — ícono lista',
            'accion' => 'Abre listado paginado de movimientos del artículo en el depósito del recuento.',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'Cantidad contada',
            'ubicacion' => $grillaLineas . ' — columna Contado',
            'accion' => 'Ingreso numérico de lo contado físicamente; recalcula Diferencia en pantalla.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
        [
            'herramienta' => 'Agregar fila / Eliminar fila',
            'ubicacion' => 'Pie de grilla / columna acciones',
            'accion' => 'Agrega línea vacía o quita una línea del conteo.',
            'permiso' => 'crear-recuento / editar-recuento',
        ],
    ],
    'recuento_ver' => [
        [
            'herramienta' => 'PDF',
            'ubicacion' => 'Barra superior derecha',
            'accion' => 'Imprime el documento de recuento.',
            'permiso' => 'imprimir-recuento',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => 'Barra superior derecha',
            'accion' => 'Vuelve al formulario si el recuento es editable.',
            'permiso' => 'editar-recuento',
        ],
        [
            'herramienta' => 'Modo de cierre (radios)',
            'ubicacion' => $panelCierre,
            'accion' => 'Elige A fecha del recuento o Al saldo actual antes de cerrar.',
            'permiso' => 'cerrar-recuento-parcial / cerrar-recuento-total',
        ],
        [
            'herramienta' => 'Cerrar parcial / Cerrar total',
            'ubicacion' => $panelCierre,
            'accion' => 'Genera movimientos de ajuste según modo y tipo de cierre.',
            'permiso' => 'cerrar-recuento-parcial / cerrar-recuento-total',
        ],
        [
            'herramienta' => 'Suspender / Reactivar',
            'ubicacion' => 'Pie de la ficha',
            'accion' => 'Pausa o reanuda el trabajo de conteo.',
            'permiso' => 'suspender-recuento / reactivar-recuento',
        ],
        [
            'herramienta' => 'Anular / Anular cierre',
            'ubicacion' => 'Pie de la ficha',
            'accion' => 'Anular cancela el documento; Anular cierre revierte movimientos y reabre.',
            'permiso' => 'anular-recuento / anular-cierre-recuento',
        ],
    ],
    'recuento_aleatorio' => [
        [
            'herramienta' => 'Cantidad a sortear',
            'ubicacion' => 'Diálogo del botón Recuento aleatorio',
            'accion' => 'Indica cuántos artículos incluir en la muestra.',
            'permiso' => 'recuento-aleatorio',
        ],
    ],
    'recuento_importar' => [
        [
            'herramienta' => 'Archivo Excel/CSV',
            'ubicacion' => 'Modal o pantalla importar',
            'accion' => 'Selecciona planilla con encabezados configurables.',
            'permiso' => 'importar-recuento',
        ],
        [
            'herramienta' => 'Mapeo de columnas',
            'ubicacion' => 'Formulario importación',
            'accion' => 'Indica nombres de columnas SKU, cantidad contada y detalle opcional.',
            'permiso' => 'importar-recuento',
        ],
    ],
    'recuento_movimientos' => [
        [
            'herramienta' => 'Filtros y paginación',
            'ubicacion' => 'Listado movimientos-articulo',
            'accion' => 'Navega historial de entradas/salidas del artículo en el depósito.',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'Exportar movimientos',
            'ubicacion' => 'Barra sobre grilla',
            'accion' => 'PDF, Excel o CSV del listado de movimientos.',
            'permiso' => 'listar-recuento',
        ],
    ],
    'recuento_export' => [
        [
            'herramienta' => 'PDF recuento',
            'ubicacion' => 'Pantalla Ver / Editar',
            'accion' => 'Documento imprimible del recuento individual.',
            'permiso' => 'imprimir-recuento',
        ],
        [
            'herramienta' => 'Export listado',
            'ubicacion' => 'Index stock/recuento',
            'accion' => 'Exportación masiva de recuentos con filtros.',
            'permiso' => 'listar-recuento',
        ],
        [
            'herramienta' => 'Archivos adjuntos',
            'ubicacion' => 'Solapa archivos en edición',
            'accion' => 'Sube evidencia del conteo; descarga desde Ver.',
            'permiso' => 'editar-recuento / ver-recuento',
        ],
    ],
];
