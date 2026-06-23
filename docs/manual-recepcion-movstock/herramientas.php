<?php

/**
 * Herramientas del manual — Recepción proveedores + Movimientos stock + Transferencias.
 */
$barraListado = 'Barra superior de la tarjeta (card-header)';
$columnaAcciones = 'Columna Acciones de cada fila';
$sobreGrilla = 'Barra de exportación sobre la tabla';
$grillaItems = 'Grilla de ítems de la recepción';
$footerForm = 'Pie del formulario (card-footer)';
$solapas = 'Solapas superiores del formulario';
$barraTransferencia = 'Barra fija inferior (Transferir)';

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
            'accion' => 'Abre formulario de alta (recepción o movimiento según pantalla).',
            'permiso' => 'crear-recepcion-proveedor / crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $barraListado . ' — botón Filtros',
            'accion' => 'Despliega panel con criterios por campo, condición y valor.',
            'permiso' => 'listar-recepcion-proveedor / listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo filtro_valor + lupa',
            'accion' => 'Panel cerrado: busca en todos los campos (tolera tipeo). Panel abierto: aplica criterios del panel.',
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
    'recepcion_listado' => [
        [
            'herramienta' => 'Emitir recepción (PDF)',
            'ubicacion' => $columnaAcciones . ' — icono PDF rojo',
            'accion' => 'Abre comprobante COM en nueva pestaña.',
            'permiso' => 'listar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Editar / Consultar',
            'ubicacion' => $columnaAcciones . ' — lápiz',
            'accion' => 'Borrador editable; confirmada/anulada en solo lectura.',
            'permiso' => 'editar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Cambiar OC',
            'ubicacion' => $columnaAcciones . ' — flechas (solo BORRADOR)',
            'accion' => 'Reasigna otra orden de compra al borrador.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Confirmar recepción',
            'ubicacion' => $columnaAcciones . ' — check verde',
            'accion' => 'Confirma ingreso: stock + asiento + Anita COM.',
            'permiso' => 'confirmar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Eliminar borrador',
            'ubicacion' => $columnaAcciones . ' — papelera roja',
            'accion' => 'Elimina borrador sin impacto en stock.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
    ],
    'recepcion_form_cabecera' => [
        [
            'herramienta' => 'Nº OC',
            'ubicacion' => 'Cabecera — campo numérico + lupa',
            'accion' => 'Enter/Tab precarga ítems; lupa abre modal OC pendientes.',
            'permiso' => 'crear-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Ver OC',
            'ubicacion' => 'Junto a Nº OC',
            'accion' => 'Abre orden de compra en nueva pestaña.',
            'permiso' => 'listar-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Fecha / Nº factura remito',
            'ubicacion' => 'Cabecera',
            'accion' => 'Fecha obligatoria (no futura); remito/factura opcional con formatos flexibles.',
            'permiso' => 'crear-recepcion-proveedor / actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Depósito general entrada',
            'ubicacion' => 'Cabecera — modal depósito',
            'accion' => 'Código + lupa F1; precarga depósito por línea si se indica.',
            'permiso' => 'crear-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Solapas',
            'ubicacion' => $solapas,
            'accion' => 'Recepción, Historia estados, Asiento contable, Archivos asociados.',
            'permiso' => 'Según permiso de edición/consulta',
        ],
        [
            'herramienta' => 'Guardar / Confirmar / Eliminar',
            'ubicacion' => $footerForm,
            'accion' => 'Guardar borrador; Confirmar impacta stock; Eliminar solo borrador.',
            'permiso' => 'actualizar-recepcion-proveedor / confirmar-recepcion-proveedor',
        ],
    ],
    'recepcion_form_items' => [
        [
            'herramienta' => 'Agregar artículo extra',
            'ubicacion' => 'Sobre la grilla',
            'accion' => 'Línea EXTRA no pedida en OC.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Consulta artículo',
            'ubicacion' => $grillaItems . ' — lupa',
            'accion' => 'Modal búsqueda; Elegir carga SKU y descripción.',
            'permiso' => 'crear-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Detalle de precios',
            'ubicacion' => $grillaItems . ' — botón info',
            'accion' => 'Modal precio OC vs recepción y motivo diferencia.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Cant. recibida / Rechaz.',
            'ubicacion' => $grillaItems,
            'accion' => 'Al menos una > 0; motivo rechazo obligatorio si hay rechazo.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Depósito por línea',
            'ubicacion' => $grillaItems . ' — columna Dep.',
            'accion' => 'Opcional; sobreescribe depósito general de cabecera.',
            'permiso' => 'actualizar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'OCR remito/factura',
            'ubicacion' => 'Solapa Archivos / cabecera en edición',
            'accion' => 'Sube PDF/imagen para precargar cantidades.',
            'permiso' => 'ocr-recepcion-proveedor',
        ],
    ],
    'recepcion_devolucion' => [
        [
            'herramienta' => 'Registrar devolución',
            'ubicacion' => $footerForm,
            'accion' => 'Confirma salida automática contra recepción origen.',
            'permiso' => 'devolver-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Anular recepción',
            'ubicacion' => $footerForm . ' (recepción CONFIRMADA)',
            'accion' => 'Revierte stock, asiento y registros Anita.',
            'permiso' => 'anular-recepcion-proveedor',
        ],
    ],
    'movimientos_listado' => [
        [
            'herramienta' => 'Editar movimiento',
            'ubicacion' => $columnaAcciones . ' — lápiz',
            'accion' => 'Formulario completo de entrada/salida.',
            'permiso' => 'editar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $columnaAcciones . ' — ojo',
            'accion' => 'Solo lectura en nueva pestaña.',
            'permiso' => 'listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Comprobante PDF',
            'ubicacion' => $columnaAcciones . ' — PDF verde',
            'accion' => 'Impresión movimiento o transferencia según fila.',
            'permiso' => 'listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones . ' — cruz roja',
            'accion' => 'Solo movimientos sueltos, no transferencias completas.',
            'permiso' => 'borrar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Editar egreso / ingreso transferencia',
            'ubicacion' => $columnaAcciones . ' — flechas amarilla/verde',
            'accion' => 'Accede a movimientos vinculados de la transferencia.',
            'permiso' => 'editar-movimientos-de-stock',
        ],
    ],
    'movimientos_form' => [
        [
            'herramienta' => 'Tipo de transacción',
            'ubicacion' => 'Cabecera',
            'accion' => 'Entrada, Salida o Transferencia; define campos visibles.',
            'permiso' => 'crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Depósito (modal)',
            'ubicacion' => 'Cabecera — código + lupa F1',
            'accion' => 'Elegir depósito autorizado; Enter resuelve por código.',
            'permiso' => 'crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Origen / Destino',
            'ubicacion' => 'Cabecera (tipos Transferencia)',
            'accion' => 'Depósitos o bienes de uso según variante.',
            'permiso' => 'crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Agregar artículo',
            'ubicacion' => 'Grilla ítems',
            'accion' => 'Modal artículo; cantidad y conversión si aplica.',
            'permiso' => 'crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Kardex línea',
            'ubicacion' => 'Grilla ítems — icono historial',
            'accion' => 'Movimientos del artículo en el depósito.',
            'permiso' => 'listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Asiento contable',
            'ubicacion' => 'Solapa (tipos con contabilidad)',
            'accion' => 'Vista previa antes de guardar.',
            'permiso' => 'crear-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Guardar / Comprobante PDF',
            'ubicacion' => $footerForm,
            'accion' => 'Registra movimiento; PDF tras guardar en edición.',
            'permiso' => 'actualizar-movimientos-de-stock',
        ],
    ],
    'transferencia_pantalla' => [
        [
            'herramienta' => 'Depósito salida / entrada',
            'ubicacion' => 'Cabecera sticky',
            'accion' => 'Modal depósito; filtra por empresa del formulario.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
        [
            'herramienta' => 'Tipo transferencia',
            'ubicacion' => 'Cabecera',
            'accion' => 'Entre depósitos, a/desde bien de uso, con/sin aprobación.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
        [
            'herramienta' => 'Cargar stock',
            'ubicacion' => 'Cabecera — botón',
            'accion' => 'Lista artículos con saldo en depósito salida.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
        [
            'herramienta' => 'Agregar artículo',
            'ubicacion' => 'Cabecera — modal',
            'accion' => 'Sumar ítems sin cargar inventario completo.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
        [
            'herramienta' => 'Transferir (N)',
            'ubicacion' => $barraTransferencia,
            'accion' => 'Envía transferencia con cantidades > 0.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
        [
            'herramienta' => 'Pendientes',
            'ubicacion' => 'Cabecera — botón con contador',
            'accion' => 'Bandeja de transferencias por aprobar.',
            'permiso' => 'listar-transferencias-pendientes',
        ],
    ],
    'transferencia_pendientes' => [
        [
            'herramienta' => 'Aprobar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Confirma ingreso en destino; estado Confirmada.',
            'permiso' => 'aprobar-transferencia-mercaderia',
        ],
        [
            'herramienta' => 'Rechazar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Revierte origen; puede pedir motivo.',
            'permiso' => 'aprobar-transferencia-mercaderia',
        ],
        [
            'herramienta' => 'Nueva transferencia',
            'ubicacion' => 'Cabecera card',
            'accion' => 'Vuelve a pantalla de alta transferencia.',
            'permiso' => 'transferir-mercaderia-entre-depositos',
        ],
    ],
    'exportaciones' => [
        [
            'herramienta' => 'COM recepción PDF',
            'ubicacion' => 'Listado o edición recepción',
            'accion' => 'Comprobante recepción proveedor.',
            'permiso' => 'listar-recepcion-proveedor',
        ],
        [
            'herramienta' => 'Comprobante movimiento PDF',
            'ubicacion' => 'Listado/edición movimientos',
            'accion' => 'Documento del movimiento suelto.',
            'permiso' => 'listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Comprobante transferencia PDF',
            'ubicacion' => 'Listado movimientos (fila transferencia)',
            'accion' => 'Documento origen → destino con ítems.',
            'permiso' => 'listar-movimientos-de-stock',
        ],
        [
            'herramienta' => 'Export listados',
            'ubicacion' => $sobreGrilla,
            'accion' => 'PDF / Excel / CSV con filtros activos.',
            'permiso' => 'Permiso listar del módulo',
        ],
    ],
];
