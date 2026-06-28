<?php

/**
 * Herramientas del manual — Módulo Vending (Gastronomía + Caja).
 */
$toolbarListado = 'Toolbar del listado (filtros, exportar, Nuevo registro)';
$columnaAcciones = 'Columna Acciones de la grilla';
$formCabecera = 'Tarjeta cabecera del formulario';
$formDetalle = 'Tarjeta detalle / medios de pago';
$formFooter = 'Pie del formulario (Guardar, Cancelar, Imprimir)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $toolbarListado,
            'accion' => 'Panel colapsable con criterios; botón Aplicar filtros / Limpiar filtros.',
            'permiso' => 'Permiso listar-* del recurso',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo filtro_valor en cabecera',
            'accion' => 'Enter o lupa busca en todos los campos cuando el panel de filtros está cerrado.',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Barra exportar sobre la grilla',
            'accion' => 'Exporta el listado completo según filtros activos (no solo la página visible).',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'Paginación',
            'ubicacion' => 'Pie de la tarjeta',
            'accion' => 'Navega entre páginas conservando filtros en la URL.',
            'permiso' => 'listar-*',
        ],
    ],
    'maquinas_listado' => [
        [
            'herramienta' => 'Sincronizar desde Anita',
            'ubicacion' => 'Cabecera del listado (si ANITA_SYNC activo)',
            'accion' => 'Importa máquinas y rulos/artículos desde Anita (Biyemas, Kandiko, Rebisco). No reemplaza rendiciones.',
            'permiso' => 'sincronizar-maquinavending-gastronomia-anita',
        ],
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => 'Toolbar',
            'accion' => 'Alta manual de máquina vending en el ERP (empresa, PV, depósito, rulos).',
            'permiso' => 'crear-maquinavending-gastronomia',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modifica datos de la máquina, punto de venta, depósito, ubicación y grilla de rulos/artículos.',
            'permiso' => 'editar-maquinavending-gastronomia',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Baja lógica de la máquina si no tiene rendiciones asociadas.',
            'permiso' => 'borrar-maquinavending-gastronomia',
        ],
    ],
    'maquinas_form' => [
        [
            'herramienta' => 'Selector empresa',
            'ubicacion' => $formCabecera,
            'accion' => 'Filtra PV, ubicación, depósito y listas de precio disponibles para la empresa.',
            'permiso' => 'crear-* / actualizar-maquinavending-gastronomia',
        ],
        [
            'herramienta' => 'Consulta depósito (modal)',
            'ubicacion' => $formCabecera,
            'accion' => 'Elige depósito de stock autorizado para la empresa; código editable + botón lupa.',
            'permiso' => 'crear-* / actualizar-maquinavending-gastronomia',
        ],
        [
            'herramienta' => 'Grilla rulos / artículos',
            'ubicacion' => 'Tarjeta Rulos',
            'accion' => 'Asocia número de rulo con artículo de stock y precio de lista. Botón agregar fila y consulta artículo por modal.',
            'permiso' => 'crear-* / actualizar-maquinavending-gastronomia',
        ],
        [
            'herramienta' => 'Guardar / Actualizar',
            'ubicacion' => $formFooter,
            'accion' => 'Valida PV, depósito y al menos un rulo; persiste en maquinavending y maquinavending_articulo.',
            'permiso' => 'guardar-maquinavending-gastronomia / actualizar-maquinavending-gastronomia',
        ],
    ],
    'rendicion_ventas_listado' => [
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $toolbarListado,
            'accion' => 'Alta de rendición de cierre (informe X) para una máquina y jornada.',
            'permiso' => 'crear-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Badge Caja (Pendiente / Presentada)',
            'ubicacion' => 'Columna Caja',
            'accion' => 'Indica si tesorería ya registró la presentación. Presentada bloquea editar/eliminar en Ventas.',
            'permiso' => 'listar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Badge Anita (OK)',
            'ubicacion' => 'Columna Anita',
            'accion' => 'Confirma replicación a Anita (rendgastro) con fecha/hora de sync.',
            'permiso' => 'listar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modifica rendición solo si estado Caja = Pendiente y permiso de edición.',
            'permiso' => 'editar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Imprimir comprobante',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Abre PDF de rendición Ventas (detalle rulos, medios, totales).',
            'permiso' => 'ver-comprobante-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Elimina rendición y revierte sync Anita si no está presentada en caja.',
            'permiso' => 'borrar-maquinavending-rendicion-gastronomia',
        ],
    ],
    'rendicion_ventas_form' => [
        [
            'herramienta' => 'Empresa / máquina / jornada',
            'ubicacion' => $formCabecera,
            'accion' => 'Define contexto; al elegir máquina carga PV, depósito y artículos por rulo vía API.',
            'permiso' => 'crear-* / actualizar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Nº cierre',
            'ubicacion' => $formCabecera,
            'accion' => 'Correlativo por empresa; se asigna automáticamente en alta.',
            'permiso' => 'crear-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Detalle por rulo',
            'ubicacion' => 'Tarjeta Ventas por rulo',
            'accion' => 'Cantidades vendidas por rulo; calcula importes según precio de lista de la máquina.',
            'permiso' => 'crear-* / actualizar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Medios de pago',
            'ubicacion' => $formDetalle,
            'accion' => 'Distribuye total cobrado por cuenta de caja (efectivo, QR, tarjeta, etc.). Debe cuadrar con total cobrado.',
            'permiso' => 'crear-* / actualizar-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Guardar e imprimir',
            'ubicacion' => $formFooter,
            'accion' => 'Graba rendición, sincroniza Anita (total X, Z=0) y abre comprobante PDF en pestaña nueva.',
            'permiso' => 'crear-* / actualizar-maquinavending-rendicion-gastronomia',
        ],
    ],
    'caja_listado' => [
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $toolbarListado,
            'accion' => 'Presenta en tesorería una rendición Ventas pendiente (modal consulta).',
            'permiso' => 'crear-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modifica montos/medios de la presentación si la fecha lo permite (hoy o encargado).',
            'permiso' => 'editar-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Imprimir comprobante caja',
            'ubicacion' => $columnaAcciones,
            'accion' => 'PDF de presentación en caja con logo, medios y vínculo a rendición Ventas.',
            'permiso' => 'listar-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Comprobante rendición Ventas',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Icono PDF rojo: reimprime comprobante original de Ventas.',
            'permiso' => 'ver-comprobante-maquinavending-rendicion-gastronomia',
        ],
        [
            'herramienta' => 'Eliminar presentación',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Anula presentación en caja, resetea total Z en Anita y deja rendición Ventas pendiente otra vez.',
            'permiso' => 'borrar-rendicion-maquinavending-caja',
        ],
    ],
    'caja_form' => [
        [
            'herramienta' => 'Consultar rendición Ventas',
            'ubicacion' => 'Botón en cabecera del formulario',
            'accion' => 'Modal con rendiciones pendientes de presentar; filtra por empresa/caja/fecha.',
            'permiso' => 'crear-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Verificación cajero',
            'ubicacion' => 'Tarjeta verificación',
            'accion' => 'Usuario y contraseña del cajero que recibe la rendición (patrón caja gastronomía/estacionamiento).',
            'permiso' => 'crear-* / actualizar-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Medios de pago / movimientos caja',
            'ubicacion' => $formDetalle,
            'accion' => 'Registra ingreso por cuenta de caja; puede diferir del detalle Ventas al arquear físicamente.',
            'permiso' => 'crear-* / actualizar-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Guardar / Actualizar',
            'ubicacion' => $formFooter,
            'accion' => 'Valida período contable abierto, fecha permitida y sync Anita (actualiza total Z).',
            'permiso' => 'guardar-rendicion-maquinavending-caja / actualizar-rendicion-maquinavending-caja',
        ],
        [
            'herramienta' => 'Imprimir comprobante',
            'ubicacion' => $formFooter,
            'accion' => 'Tras guardar, abre PDF de presentación caja.',
            'permiso' => 'listar-rendicion-maquinavending-caja',
        ],
    ],
];
