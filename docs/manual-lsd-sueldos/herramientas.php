<?php

/**
 * Herramientas del manual — Libro de Sueldos Digital.
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
            'accion' => 'Enter o lupa busca si el panel está cerrado.',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => 'Barra exportar sobre la grilla',
            'accion' => 'Exporta el listado completo según filtros (no solo la página visible).',
            'permiso' => 'listar-*',
        ],
    ],
    'concepto_listado' => [
        [
            'herramienta' => 'Nuevo',
            'ubicacion' => $toolbarListado,
            'accion' => 'Alta de un concepto de liquidación.',
            'permiso' => 'crear-concepto-sueldos',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Abre el ABM: tipo, fórmula, código AFIP y bases 04.',
            'permiso' => 'editar-concepto-sueldos',
        ],
    ],
    'concepto_form' => [
        [
            'herramienta' => 'Concepto AFIP (LSD)',
            'ubicacion' => 'Bloque LSD del formulario',
            'accion' => 'Elige el código de 6 dígitos del catálogo ARCA. Precarga los tildes de subsistemas.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Código AFIP libre',
            'ubicacion' => 'Debajo del combo',
            'accion' => 'Para rangos no catalogados (111001, 121000, etc.).',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Subsistemas LSD',
            'ubicacion' => 'Tildes del bloque LSD',
            'accion' => 'Remunerativo: todos en 1. Descuento: todos en 0. No inventar combinaciones.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Bases registro 04',
            'ubicacion' => 'Grilla +1 / −1',
            'accion' => 'Solo informativos de tope/detracción (1000, 3630, 1002). Un haber normal no lleva bases.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
    ],
    'lsd_workbench' => [
        [
            'herramienta' => 'Ver cobertura',
            'ubicacion' => 'Bloque Parametrización de conceptos',
            'accion' => 'Lista conceptos exportables sin código AFIP, con enlace al ABM.',
            'permiso' => 'listar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Exportar TXT conceptos',
            'ubicacion' => 'Mismo bloque',
            'accion' => 'Baja el archivo para importar en ARCA → Conceptos. No incluye contribuciones ni informativos.',
            'permiso' => 'exportar-conceptos-lsd-sueldos',
        ],
        [
            'herramienta' => 'Generar y previsualizar',
            'ubicacion' => 'Formulario Generar liquidación',
            'accion' => 'Arma el TXT 01–06 de una liquidación cerrada.',
            'permiso' => 'generar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Cabecera de la pantalla',
            'accion' => 'Abre este documento.',
            'permiso' => '(sesión iniciada)',
        ],
    ],
    'lsd_ver' => [
        [
            'herramienta' => 'Descargar TXT',
            'ubicacion' => 'Detalle de la presentación',
            'accion' => 'Baja LSD_AAAAMM_NNNNN.txt para importar en ARCA. No abrir con Excel.',
            'permiso' => 'ver-lsd-sueldos',
        ],
        [
            'herramienta' => 'Marcar presentada en ARCA',
            'ubicacion' => 'Mismo detalle',
            'accion' => 'Confirma que ARCA aceptó el envío. No se regenera.',
            'permiso' => 'presentar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Marcar rechazada',
            'ubicacion' => 'Mismo detalle',
            'accion' => 'Deja constancia de rechazo para corregir y volver a generar.',
            'permiso' => 'presentar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Generar rectificativa RE',
            'ubicacion' => 'Mismo detalle (solo envío SJ)',
            'accion' => 'Crea un TXT RE (omite 02 y 03) para corregir el F.931.',
            'permiso' => 'rectificar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => 'Mismo detalle',
            'accion' => 'Borra una presentación no presentada (o la que autorice el permiso).',
            'permiso' => 'borrar-lsd-sueldos',
        ],
    ],
];
