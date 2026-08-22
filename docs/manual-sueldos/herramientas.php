<?php

/**
 * Herramientas del manual — Sueldos / sanciones.
 */
$toolbarListado = 'Toolbar del listado (filtros y exportar)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Filtros',
            'ubicacion' => $toolbarListado,
            'accion' => 'Panel colapsable; Aplicar filtros o Limpiar filtros.',
            'permiso' => 'listar-* del recurso',
        ],
        [
            'herramienta' => 'Búsqueda rápida',
            'ubicacion' => 'Campo de la cabecera',
            'accion' => 'Enter o lupa busca en código y nombre si el panel está cerrado.',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Barra exportar sobre la grilla',
            'accion' => 'Exporta el listado completo según filtros (no solo la página visible).',
            'permiso' => 'listar-*',
        ],
    ],
    'tipo_listado' => [
        [
            'herramienta' => 'Nuevo',
            'ubicacion' => $toolbarListado,
            'accion' => 'Alta de un tipo de sanción.',
            'permiso' => 'crear-tipo-sancion-sueldos',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Abre el ABM: concepto, tildes y plantilla de carta.',
            'permiso' => 'editar-tipo-sancion-sueldos',
        ],
    ],
    'tipo_form' => [
        [
            'herramienta' => 'Concepto liquidación',
            'ubicacion' => 'Formulario del tipo',
            'accion' => 'Lupa / F1 / código + Enter. Vacío = no descuenta en el recibo.',
            'permiso' => 'actualizar-tipo-sancion-sueldos',
        ],
        [
            'herramienta' => 'Tildes',
            'ubicacion' => 'Bloque Opciones',
            'accion' => 'Requiere días, goza sueldo, genera novedad y activo. Cada uno tiene una línea de ayuda.',
            'permiso' => 'actualizar-tipo-sancion-sueldos',
        ],
        [
            'herramienta' => 'Plantilla de notificación',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Texto extra que se imprime en la carta PDF.',
            'permiso' => 'actualizar-tipo-sancion-sueldos',
        ],
    ],
    'motivo_listado' => [
        [
            'herramienta' => 'Nuevo / Editar',
            'ubicacion' => 'Toolbar y acciones',
            'accion' => 'Alta o cambio de un motivo. Destildar Activo oculta el motivo en cargas nuevas.',
            'permiso' => 'crear / editar motivo-sancion-sueldos',
        ],
    ],
    'empleado_sancion' => [
        [
            'herramienta' => 'Tipo y motivo',
            'ubicacion' => 'Formulario Nueva sanción',
            'accion' => 'Lupa o F1. Enter valida el código. El concepto lo trae el tipo, no se elige acá.',
            'permiso' => 'crear-sancion-empleado-sueldos',
        ],
        [
            'herramienta' => 'Importe no cobrado',
            'ubicacion' => 'Mismo formulario',
            'accion' => 'Pesos que no se pagan. 0 si no hay descuento. No es una multa.',
            'permiso' => 'crear / editar sancion-empleado-sueldos',
        ],
        [
            'herramienta' => 'Carta PDF',
            'ubicacion' => 'Grilla histórica',
            'accion' => 'Abre la notificación para imprimir o guardar.',
            'permiso' => 'listar-sancion-empleado-sueldos',
        ],
        [
            'herramienta' => 'Quitar',
            'ubicacion' => 'Grilla histórica',
            'accion' => 'Borra el expediente. Si había novedad, se anula.',
            'permiso' => 'borrar-sancion-empleado-sueldos',
        ],
    ],
    'reporte' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Formulario de filtros',
            'accion' => 'Arma el listado según empresa, fechas, estado, legajo, tipo y motivo.',
            'permiso' => 'listar-sancion-reporte-sueldos',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Barra exportar',
            'accion' => 'Exporta el filtro completo, no solo la página.',
            'permiso' => 'listar-sancion-reporte-sueldos',
        ],
    ],
];
