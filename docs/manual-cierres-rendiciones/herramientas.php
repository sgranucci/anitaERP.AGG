<?php

/**
 * Herramientas del manual — Contaduría: cierres de rendiciones.
 */
$filtros = 'Formulario superior de la pantalla';
$listadoGrupos = 'Tabla de grupos (empresa + fecha)';
$modalPreview = 'Modal de preview de asiento';
$barraAcciones = 'Barra de acciones del grupo / fila';
$conciliacion = 'Pantalla Conciliación Flash';

return [
    'maquina_listado' => [
        [
            'herramienta' => 'Empresa',
            'ubicacion' => $filtros,
            'accion' => 'Filtra rendiciones y grupos de cierre por empresa.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Fecha desde / hasta',
            'ubicacion' => $filtros,
            'accion' => 'Acota el período de consulta del listado agrupado.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Estado del grupo',
            'ubicacion' => $filtros,
            'accion' => 'Pendiente, cerrada o parcial según cuántas rendiciones turno C ya tienen asiento.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $filtros,
            'accion' => 'Recarga la grilla agrupada por día (solo turno C).',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Exportar PDF / Excel / CSV',
            'ubicacion' => 'Toolbar de exportación',
            'accion' => 'Baja el detalle de rendiciones con los filtros activos.',
            'permiso' => 'exportar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Conciliación Flash',
            'ubicacion' => 'Cabecera / enlace toolbar',
            'accion' => 'Abre la comparación diaria rendiciones vs Flash (win_ol_slot + win_ol_rul).',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Cabecera / Centro de ayuda',
            'accion' => 'Abre este manual en una pestaña nueva.',
            'permiso' => 'Usuario autenticado',
        ],
    ],
    'maquina_grupo_acciones' => [
        [
            'herramienta' => 'Preview asiento',
            'ubicacion' => $barraAcciones.' — ícono ojo / lupa',
            'accion' => 'Muestra el asiento propuesto (Venta máquinas, Pago diferido, Canones) sin grabar.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Ejecutar cierre',
            'ubicacion' => $barraAcciones.' — ícono play / candado',
            'accion' => 'Genera asiento(s) contable(s), marca rendiciones y envía FSL a Anita si corresponde.',
            'permiso' => 'ejecutar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Ejecutar rango',
            'ubicacion' => 'Barra superior del listado',
            'accion' => 'Cierra en lote varios días pendientes del filtro (preview previo recomendado).',
            'permiso' => 'ejecutar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Anular cierre',
            'ubicacion' => $barraAcciones.' — ícono ban (grupo cerrado)',
            'accion' => 'Revierte el cierre contable del día: borra asiento y libera rendiciones (si el período lo permite).',
            'permiso' => 'anular-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Ver rendiciones del grupo',
            'ubicacion' => $listadoGrupos.' — expandir / detalle',
            'accion' => 'Lista las rendiciones turno C incluidas en el grupo empresa + fecha.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Enlace al asiento',
            'ubicacion' => 'Columna Asiento (grupo cerrado)',
            'accion' => 'Abre el asiento contable generado en otra pestaña.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
    ],
    'maquina_preview' => [
        [
            'herramienta' => 'Totales del día',
            'ubicacion' => $modalPreview.' — cabecera',
            'accion' => 'Resume efectivo, tarjetas, online Flash, FSL y cantidad de rendiciones.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Bloques de asiento',
            'ubicacion' => $modalPreview.' — cuerpo',
            'accion' => 'Separa Venta máquinas, Pago diferido, Canon lotería/casinos y Canon hospital.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Advertencias de cuadre',
            'ubicacion' => $modalPreview.' — banda amarilla',
            'accion' => 'Avisa si debe ≠ haber (tolerancia 0,02) o faltan cuentas automáticas.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Confirmar ejecución',
            'ubicacion' => $modalPreview.' — botón Ejecutar',
            'accion' => 'Graba asiento(s) con la misma lógica del preview (requiere período abierto).',
            'permiso' => 'ejecutar-cierre-rendicion-maquina-contable',
        ],
    ],
    'conciliacion_flash_maquina' => [
        [
            'herramienta' => 'Empresa y rango de fechas',
            'ubicacion' => $conciliacion,
            'accion' => 'Define el tramo a conciliar contra flash_caja.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $conciliacion,
            'accion' => 'Calcula por día: total rendiciones turno C vs win_ol_slot + win_ol_rul.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Estado OK / DIF',
            'ubicacion' => 'Columna Estado de la grilla diaria',
            'accion' => 'OK dentro de tolerancia; DIF cuando hay desvío o faltan rendiciones/cierre.',
            'permiso' => 'listar-cierre-rendicion-maquina-contable',
        ],
        [
            'herramienta' => 'Exportar conciliación',
            'ubicacion' => 'Toolbar',
            'accion' => 'PDF / Excel / CSV del informe Flash con filtros activos.',
            'permiso' => 'exportar-cierre-rendicion-maquina-contable',
        ],
    ],
    'bingo_listado' => [
        [
            'herramienta' => 'Filtros y Consultar',
            'ubicacion' => $filtros,
            'accion' => 'Empresa, fechas y estado del grupo diario de rendiciones bingo.',
            'permiso' => 'listar-cierre-rendicion-bingo-contable',
        ],
        [
            'herramienta' => 'Preview asiento BIN',
            'ubicacion' => $barraAcciones,
            'accion' => 'Muestra Pago de premios, Dev. pozo acum. y canones antes de ejecutar.',
            'permiso' => 'listar-cierre-rendicion-bingo-contable',
        ],
        [
            'herramienta' => 'Ejecutar / Anular cierre',
            'ubicacion' => $barraAcciones,
            'accion' => 'Genera asiento(s) y marca rendiciones; anular revierte si el período lo permite.',
            'permiso' => 'ejecutar-cierre-rendicion-bingo-contable / anular-cierre-rendicion-bingo-contable',
        ],
        [
            'herramienta' => 'Conciliación Flash bingo',
            'ubicacion' => 'Enlace toolbar',
            'accion' => 'Compara recaudación bingo vs Flash del día (si la empresa lo usa).',
            'permiso' => 'listar-cierre-rendicion-bingo-contable',
        ],
        [
            'herramienta' => 'Exportar listado',
            'ubicacion' => 'Toolbar',
            'accion' => 'PDF / Excel / CSV de rendiciones bingo filtradas.',
            'permiso' => 'exportar-cierre-rendicion-bingo-contable',
        ],
    ],
    'estacionamiento_resumen' => [
        [
            'herramienta' => 'Listado agrupado',
            'ubicacion' => 'contable/cierre-rendiciones-estacionamiento',
            'accion' => 'Grupos por empresa + jornada; preview, ejecutar y anular cierre contable.',
            'permiso' => 'listar-cierre-rendicion-estacionamiento-contable',
        ],
        [
            'herramienta' => 'Ejecutar cierre jornada',
            'ubicacion' => 'Barra de acciones',
            'accion' => 'Cierra todas las rendiciones pendientes de una jornada en un solo paso.',
            'permiso' => 'ejecutar-cierre-rendicion-estacionamiento-contable',
        ],
        [
            'herramienta' => 'Diario por punto de venta',
            'ubicacion' => 'contable/cierre-rendiciones-estacionamiento/diario-puntoventa',
            'accion' => 'Informe analítico por PV para control previo al cierre.',
            'permiso' => 'listar-cierre-rendicion-estacionamiento-contable',
        ],
        [
            'herramienta' => 'Conciliación Flash',
            'ubicacion' => 'contable/cierre-rendiciones-estacionamiento/conciliacion-flash',
            'accion' => 'Compara totales de rendición vs Flash estacionamiento.',
            'permiso' => 'listar-cierre-rendicion-estacionamiento-contable',
        ],
    ],
    'maquinavending_resumen' => [
        [
            'herramienta' => 'Listado agrupado',
            'ubicacion' => 'contable/cierre-rendiciones-maquinavending',
            'accion' => 'Misma lógica que estacionamiento adaptada a vending (jornada + medios).',
            'permiso' => 'listar-cierre-rendicion-maquinavending-contable',
        ],
        [
            'herramienta' => 'Diario punto de venta',
            'ubicacion' => 'contable/cierre-rendiciones-maquinavending/diario-puntoventa',
            'accion' => 'Detalle por PV antes del cierre contable.',
            'permiso' => 'listar-cierre-rendicion-maquinavending-contable',
        ],
        [
            'herramienta' => 'Conciliación Flash',
            'ubicacion' => 'contable/cierre-rendiciones-maquinavending/conciliacion-flash',
            'accion' => 'Control rendición vs Flash vending.',
            'permiso' => 'listar-cierre-rendicion-maquinavending-contable',
        ],
    ],
    'gastronomia_contable' => [
        [
            'herramienta' => 'Listado cierres turno',
            'ubicacion' => 'contable/cierres-turno-gastronomia',
            'accion' => 'Consulta cierres de turno ya rendidos en Caja; enlace a comprobante PDF.',
            'permiso' => 'listar-cierres-turno-gastronomia-contable',
        ],
        [
            'herramienta' => 'Conciliación',
            'ubicacion' => 'contable/cierres-turno-gastronomia/conciliacion',
            'accion' => 'Compara medios del cierre vs rendición gastronomía del período.',
            'permiso' => 'listar-cierres-turno-gastronomia-contable',
        ],
        [
            'herramienta' => 'Diario por PV',
            'ubicacion' => 'contable/cierres-turno-gastronomia/diario-puntoventa',
            'accion' => 'Totales por punto de venta / terminal para control contable.',
            'permiso' => 'listar-cierres-turno-gastronomia-contable',
        ],
        [
            'herramienta' => 'Comprobante cierre / parcial',
            'ubicacion' => 'Columna acciones',
            'accion' => 'PDF del cierre de turno o cierre parcial operativo (solo consulta contable).',
            'permiso' => 'listar-cierres-turno-gastronomia-contable',
        ],
    ],
];
