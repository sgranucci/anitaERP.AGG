<?php

/**
 * Herramientas del manual — Tesorería Crown.
 */
$filtros = 'Formulario / chips de sala';
$listado = 'Tabla del listado';
$escritorio = 'Escritorio de la jornada';
$comprobante = 'Pantalla del comprobante';
$cierre = 'Pantalla cierre de caja';
$boveda = 'Pantalla bóveda';
$barra = 'Barra de sala (export compacto)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Pdf / Excel / Csv',
            'ubicacion' => $barra,
            'accion' => 'Exporta el listado activo con los filtros de la sala/fecha.',
            'permiso' => 'listar-tesoreria-crown-jornada (u homólogo de la pantalla)',
        ],
        [
            'herramienta' => 'Chips de sala',
            'ubicacion' => $barra,
            'accion' => 'Cambia la sala activa sin mezclar jornadas.',
            'permiso' => 'listar-tesoreria-crown-jornada',
        ],
    ],
    'jornada_listado' => [
        [
            'herramienta' => 'Abrir jornada',
            'ubicacion' => $filtros,
            'accion' => 'Crea la jornada de la sala y fecha si no existe.',
            'permiso' => 'crear-tesoreria-crown-jornada',
        ],
        [
            'herramienta' => 'Ver / Escritorio',
            'ubicacion' => $listado,
            'accion' => 'Entra al día: asignaciones, solapas y anexos.',
            'permiso' => 'listar-tesoreria-crown-jornada',
        ],
        [
            'herramienta' => 'Cerrar jornada',
            'ubicacion' => $escritorio,
            'accion' => 'Cierra el día si todos los cierres de caja con movimiento están terminales.',
            'permiso' => 'cerrar-tesoreria-crown-jornada',
        ],
    ],
    'jornada_escritorio' => [
        [
            'herramienta' => 'Asignar caja',
            'ubicacion' => $escritorio,
            'accion' => 'Elige depósito, cajero, turno y tarea (T/G/H/K/E/L).',
            'permiso' => 'asignar-tesoreria-crown-jornada',
        ],
        [
            'herramienta' => 'Solapas Cajeros / Mesas / Bóveda / Slots / Bingo / Fichas / Anexos',
            'ubicacion' => $escritorio,
            'accion' => 'Navega el circuito del día sin salir de la jornada.',
            'permiso' => 'listar-tesoreria-crown-jornada',
        ],
        [
            'herramienta' => 'Columna Cierre',
            'ubicacion' => 'Grilla de asignaciones',
            'accion' => 'Abre el cierre parcial de esa caja.',
            'permiso' => 'listar-tesoreria-crown-cierre',
        ],
    ],
    'comprobante_firma' => [
        [
            'herramienta' => 'Pedir firmas',
            'ubicacion' => $comprobante,
            'accion' => 'Graba cantidades y deja el comprobante pendiente (congelado).',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Firmar tesorería',
            'ubicacion' => $comprobante,
            'accion' => 'Usuario + clave del lado tesorería (entrega en H / recibe en F).',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Firmar cajero',
            'ubicacion' => $comprobante,
            'accion' => 'Número de cajero + PIN del lado cajero.',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Volver a borrador / Modificar cantidades',
            'ubicacion' => $comprobante,
            'accion' => 'Descongela para corregir; borra firmas si ya había alguna.',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'No conforme',
            'ubicacion' => $comprobante,
            'accion' => 'Pide motivo y manda el papel a auditoría (deja de sumar).',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Revertir',
            'ubicacion' => $comprobante.' (conforme)',
            'accion' => 'Crea papel inverso pendiente de las dos firmas (una sola vez).',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
    ],
    'rendicion' => [
        [
            'herramienta' => 'Aplicar a la rendición',
            'ubicacion' => 'Bloque cierres fiscales (G/H)',
            'accion' => 'Completa X, Z, NC y último ticket desde cierres de ventas marcados.',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Traer de PXSol',
            'ubicacion' => 'Bloque hotel (H)',
            'accion' => 'Trae facturado/crédito/IVA desde PXSol entre 1ª habilitación y ahora.',
            'permiso' => 'editar-tesoreria-crown-movimiento',
        ],
        [
            'herramienta' => 'Imprimir PDF',
            'ubicacion' => $comprobante.' (conforme)',
            'accion' => 'Comprobante firmado para archivo.',
            'permiso' => 'listar-tesoreria-crown-movimiento',
        ],
    ],
    'cierre_caja' => [
        [
            'herramienta' => 'Cerrar caja',
            'ubicacion' => $cierre,
            'accion' => 'Calcula diferencia, aplica tolerancia y pide firmas tesorería + cajero.',
            'permiso' => 'cerrar-tesoreria-crown-cierre',
        ],
        [
            'herramienta' => 'Marcar revisada / Auditoría / Cerrar auditoría',
            'ubicacion' => $cierre,
            'accion' => 'Avanza el caso hasta un estado terminal.',
            'permiso' => 'editar-tesoreria-crown-cierre',
        ],
        [
            'herramienta' => 'Ajuste de caja',
            'ubicacion' => $cierre,
            'accion' => 'Corrige importe con doble firma sistema sin reabrir el día.',
            'permiso' => 'ajustar-tesoreria-crown-caja',
        ],
        [
            'herramienta' => 'Reabrir caja',
            'ubicacion' => $cierre,
            'accion' => 'Vuelve la caja a operativa (y la jornada si hace falta) para revertir o cargar.',
            'permiso' => 'reabrir-tesoreria-crown-caja',
        ],
    ],
    'boveda' => [
        [
            'herramienta' => 'Nuevo movimiento',
            'ubicacion' => $boveda,
            'accion' => 'Alta borrador INGR/OEGR/PDEP u otro tipo del catálogo.',
            'permiso' => 'crear-tesoreria-crown-boveda',
        ],
        [
            'herramienta' => 'Confirmar / Anular',
            'ubicacion' => $boveda,
            'accion' => 'Confirmar mueve saldo; anular (jornada abierta) lo revierte.',
            'permiso' => 'editar-tesoreria-crown-boveda',
        ],
        [
            'herramienta' => 'Arqueo de turno',
            'ubicacion' => 'Menú Bóveda › Arqueo',
            'accion' => 'Arqueo con firmas propias del turno.',
            'permiso' => 'listar-tesoreria-crown-boveda-arqueo',
        ],
    ],
    'flash_crown' => [
        [
            'herramienta' => 'Recalcular',
            'ubicacion' => 'Listado Flash Crown',
            'accion' => 'Vuelve a armar el día desde Anexo I/II + resultado online.',
            'permiso' => 'editar-flash-crown',
        ],
        [
            'herramienta' => 'Validar / Quitar validación',
            'ubicacion' => 'Listado Flash Crown',
            'accion' => 'Congela o libera el flash del día para reportes.',
            'permiso' => 'validar-flash-crown',
        ],
        [
            'herramienta' => 'Reportes / suscripciones',
            'ubicacion' => 'caja/flash-crown/reporte',
            'accion' => 'Exporta período y programa envíos.',
            'permiso' => 'listar-flash-crown',
        ],
    ],
];
