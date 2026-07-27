<?php

/**
 * Herramientas del manual — Contable (cierres / aperturas).
 */
$barraAgenda = 'Barra de herramientas de la tarjeta Agenda de cierres';
$filaAgenda = 'Fila del módulo en la tabla de agenda';
$columnaAcciones = 'Columna Acciones de la fila';
$filtros = 'Formulario superior de la pantalla';
$cardInmediato = 'Tarjeta “Cierre inmediato (un módulo)”';
$historico = 'Tabla Histórico de cierres';
$modal = 'Ventana modal';

return [
    'cierre_cabecera' => [
        [
            'herramienta' => 'Empresa',
            'ubicacion' => $filtros,
            'accion' => 'Selecciona la empresa del cierre / agenda. Con una sola empresa asignada viene fija.',
            'permiso' => 'listar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Mes / Año agenda',
            'ubicacion' => $filtros,
            'accion' => 'Define el mes de programación (ej. agosto). Consultar recarga la grilla.',
            'permiso' => 'listar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $filtros,
            'accion' => 'Aplica empresa y mes, muestra agenda e histórico.',
            'permiso' => 'listar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Cabecera de la pantalla / Centro de ayuda',
            'accion' => 'Abre este manual en una pestaña nueva.',
            'permiso' => 'Usuario autenticado',
        ],
        [
            'herramienta' => 'Cierre general vigente',
            'ubicacion' => 'Banda informativa bajo los filtros',
            'accion' => 'Muestra hasta qué fecha está el cierre general. Permite borrar el último general si tiene permiso.',
            'permiso' => 'listar-cierre-periodo-contable / borrar-ultimo-cierre-periodo-contable',
        ],
    ],
    'cierre_barra_agenda' => [
        [
            'herramienta' => 'Programar todos',
            'ubicacion' => $barraAgenda.' — ícono calendario',
            'accion' => 'Abre modal para cargar la misma fecha de ejecución, hora y fecha de cierre a todos los módulos del mes.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Cerrar todos ahora',
            'ubicacion' => $barraAgenda.' — ícono candado',
            'accion' => 'Cierre general inmediato: bloquea todos los módulos hasta la fecha indicada y congela saldos contables.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Aplicar pendientes',
            'ubicacion' => $barraAgenda.' — ícono play',
            'accion' => 'Ejecuta ahora todos los módulos pendientes/error del mes cuya fecha de ejecución sea hoy o anterior.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
    ],
    'cierre_fila' => [
        [
            'herramienta' => 'Fecha ejecución',
            'ubicacion' => $filaAgenda,
            'accion' => 'Día en que el job o usted aplicará el cierre de ese módulo.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Hora',
            'ubicacion' => $filaAgenda,
            'accion' => 'HH:MM o 24:00 (fin de día). Vacío = 24:00. El job espera ese momento; “Aplicar ahora” no.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Fecha cierre',
            'ubicacion' => $filaAgenda,
            'accion' => 'Tope contable inclusive (editable). Por defecto, último día del mes anterior a la agenda.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Observación',
            'ubicacion' => $filaAgenda,
            'accion' => 'Texto libre que se guarda en el cierre al ejecutarse.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Guardar',
            'ubicacion' => $columnaAcciones.' — disquete',
            'accion' => 'Guarda o actualiza la programación (estado pendiente).',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Aplicar ahora',
            'ubicacion' => $columnaAcciones.' — play',
            'accion' => 'Ejecuta el cierre de ese módulo de inmediato si la fecha de ejecución ya llegó.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Cancelar',
            'ubicacion' => $columnaAcciones.' — ban',
            'accion' => 'Cancela una programación pendiente o con error.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Estado (badge)',
            'ubicacion' => $filaAgenda,
            'accion' => 'pendiente / ejecutado / cancelado / error. En error muestra el mensaje.',
            'permiso' => 'listar-cierre-periodo-contable',
        ],
    ],
    'cierre_inmediato_historico' => [
        [
            'herramienta' => 'Cierre inmediato — Módulo',
            'ubicacion' => $cardInmediato,
            'accion' => 'Selector de alcance (incluye General) para cerrar ya sin agenda.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Ejecutar cierre',
            'ubicacion' => $cardInmediato,
            'accion' => 'Registra el cierre del módulo elegido hasta la fecha indicada.',
            'permiso' => 'ejecutar-cierre-periodo-contable',
        ],
        [
            'herramienta' => 'Borrar último',
            'ubicacion' => $historico.' / banda de cierre vigente',
            'accion' => 'Elimina el último cierre de ese alcance (empresa + módulo). El snapshot de saldos del general se borra en cascada.',
            'permiso' => 'borrar-ultimo-cierre-periodo-contable',
        ],
    ],
    'apertura' => [
        [
            'herramienta' => 'Solicitar apertura',
            'ubicacion' => 'Botón / modal de la pantalla de aperturas',
            'accion' => 'Crea solicitud: fechas, alcance, duración, usuario habilitado y motivo.',
            'permiso' => 'solicitar-apertura-periodo-contable',
        ],
        [
            'herramienta' => 'Habilitar / Aprobar',
            'ubicacion' => 'Columna Acciones o enlace del correo',
            'accion' => 'Activa la ventana de tiempo del permiso temporal.',
            'permiso' => 'habilitar-apertura-periodo-contable / aprobar-apertura-periodo-contable',
        ],
        [
            'herramienta' => 'Rechazar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Rechaza una solicitud pendiente.',
            'permiso' => 'aprobar-apertura-periodo-contable',
        ],
        [
            'herramienta' => 'Revocar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Cierra anticipadamente una apertura activa.',
            'permiso' => 'revocar-apertura-periodo-contable',
        ],
    ],
];
