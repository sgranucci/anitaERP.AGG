<?php

/**
 * Herramientas del manual — Sueldos y jornales.
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
    'empleado_listado' => [
        [
            'herramienta' => 'Chips de estado y empresa',
            'ubicacion' => 'Encima de la consulta avanzada',
            'accion' => 'Activos, provisorios, bajas o todos, y una empresa o todas. Se combinan con AND y se conservan al exportar.',
            'permiso' => 'listar-empleado-sueldos',
        ],
        [
            'herramienta' => 'Diseñar vista / QBE',
            'ubicacion' => 'Toolbar del workbench',
            'accion' => 'Columnas, orden y consulta avanzada. El PDF y el Excel salen con ese orden.',
            'permiso' => 'listar-empleado-sueldos',
        ],
        [
            'herramienta' => 'Nuevo empleado',
            'ubicacion' => 'Barra propia',
            'accion' => 'Alta de legajo.',
            'permiso' => 'crear-empleado-sueldos',
        ],
    ],
    'empleado_ficha' => [
        [
            'herramienta' => 'Actualizar',
            'ubicacion' => 'Barra fija, en todas las solapas',
            'accion' => 'Graba la cabecera del legajo. Las solapas con alta propia tienen su botón.',
            'permiso' => 'actualizar-empleado-sueldos',
        ],
        [
            'herramienta' => 'Solapa Liquidación',
            'ubicacion' => 'Ficha',
            'accion' => 'Grupos, tilde sin grupo, explícitos +/−, set efectivo y por qué no entraron.',
            'permiso' => 'editar-empleado-sueldos',
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
            'accion' => 'Abre tipo, momento, fórmulas, elegibilidad y bloque LSD.',
            'permiso' => 'editar-concepto-sueldos',
        ],
    ],
    'concepto_form' => [
        [
            'herramienta' => 'Debugger de fórmulas',
            'ubicacion' => 'Edición, debajo del formulario',
            'accion' => 'Prueba importe, cantidad y valor contra un legajo y un tipo de corrida, sin grabar.',
            'permiso' => 'editar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Concepto AFIP (LSD)',
            'ubicacion' => 'Bloque LSD',
            'accion' => 'Código de 6 dígitos. Precarga los tildes de subsistemas. Código libre si no está en la lista.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Bases registro 04',
            'ubicacion' => 'Mismo bloque',
            'accion' => 'Solo informativos de tope o detracción. Un haber normal no lleva bases.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
    ],
    'grupo_form' => [
        [
            'herramienta' => 'Conceptos del grupo',
            'ubicacion' => 'Formulario del grupo',
            'accion' => 'Lista múltiple. Es la base del legajo; después aplican elegibilidad y explícitos.',
            'permiso' => 'actualizar-grupo-concepto-sueldos',
        ],
        [
            'herramienta' => 'Empresa',
            'ubicacion' => 'Misma pantalla',
            'accion' => 'Vacío = el grupo vale para todas las empresas.',
            'permiso' => 'actualizar-grupo-concepto-sueldos',
        ],
    ],
    'set_legajo' => [
        [
            'herramienta' => 'Agregar grupo',
            'ubicacion' => 'Solapa Liquidación',
            'accion' => 'Suma un grupo. Con uno o más, el modo pasa a grupos (unión de conceptos).',
            'permiso' => 'actualizar-empleado-sueldos',
        ],
        [
            'herramienta' => 'Quitar grupo',
            'ubicacion' => 'Grilla de grupos',
            'accion' => 'Deja de aportar esos conceptos. Si no queda ninguno, manda el tilde sin grupo.',
            'permiso' => 'actualizar-empleado-sueldos',
        ],
        [
            'herramienta' => 'Por qué no entraron',
            'ubicacion' => 'Pie de la solapa',
            'accion' => 'Motivo de cada candidato excluido (elegibilidad, explícito, momento).',
            'permiso' => 'editar-empleado-sueldos',
        ],
    ],
    'elegibilidad' => [
        [
            'herramienta' => 'Grupo OR',
            'ubicacion' => 'Reglas de elegibilidad del concepto',
            'accion' => 'Mismo número = cualquiera alcanza. Número distinto = AND con las anteriores.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Campo / operador / valor',
            'ubicacion' => 'Mismo formulario',
            'accion' => 'Sindicato, obra social, categoría, agrupamiento o empresa. Igual, distinto, lista, vacío o con valor.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
        [
            'herramienta' => 'Vigencia',
            'ubicacion' => 'Desde / hasta',
            'accion' => 'Opcional. Se compara con la fecha de la liquidación.',
            'permiso' => 'actualizar-concepto-sueldos',
        ],
    ],
    'novedad_listado' => [
        [
            'herramienta' => 'Nueva novedad',
            'ubicacion' => $toolbarListado,
            'accion' => 'Concepto, período o fechas, cantidad e importe.',
            'permiso' => 'crear-novedad-sueldos',
        ],
        [
            'herramienta' => 'Importar',
            'ubicacion' => 'Pantalla de novedades',
            'accion' => 'Carga un lote. Una novedad manual manda sobre el espejo de Anita.',
            'permiso' => 'crear-novedad-sueldos',
        ],
    ],
    'liquidacion_listado' => [
        [
            'herramienta' => 'Calcular / recalcular',
            'ubicacion' => 'Acciones de la corrida',
            'accion' => 'Arma los recibos con el set efectivo de cada legajo.',
            'permiso' => 'editar-liquidacion-sueldos',
        ],
        [
            'herramienta' => 'Ver recibos',
            'ubicacion' => 'Misma grilla',
            'accion' => 'Resultado y rastro de fórmula por línea.',
            'permiso' => 'listar-liquidacion-sueldos',
        ],
        [
            'herramienta' => 'Cerrar / reabrir',
            'ubicacion' => 'Candado de la fila',
            'accion' => 'Cerrar habilita LSD y asiento. Reabrir vuelve a borrador calculable.',
            'permiso' => 'cerrar-liquidacion-sueldos',
        ],
        [
            'herramienta' => 'Calidad / asiento',
            'ubicacion' => 'Acciones de la corrida cerrada',
            'accion' => 'Revisa el devengamiento y abre el asiento.',
            'permiso' => 'listar-liquidacion-sueldos',
        ],
    ],
    'lsd_workbench' => [
        [
            'herramienta' => 'Ver cobertura',
            'ubicacion' => 'Parametrización de conceptos',
            'accion' => 'Lista conceptos exportables sin código AFIP, con enlace al ABM.',
            'permiso' => 'listar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Exportar TXT conceptos',
            'ubicacion' => 'Mismo bloque',
            'accion' => 'Archivo para ARCA → Conceptos. No incluye contribuciones ni informativos.',
            'permiso' => 'exportar-conceptos-lsd-sueldos',
        ],
        [
            'herramienta' => 'Generar y previsualizar',
            'ubicacion' => 'Generar liquidación',
            'accion' => 'Arma el TXT 01–06 de una liquidación cerrada.',
            'permiso' => 'generar-lsd-sueldos',
        ],
    ],
    'lsd_ver' => [
        [
            'herramienta' => 'Descargar TXT',
            'ubicacion' => 'Detalle de la presentación',
            'accion' => 'Baja LSD_AAAAMM_NNNNN.txt. No abrir con Excel.',
            'permiso' => 'ver-lsd-sueldos',
        ],
        [
            'herramienta' => 'Marcar presentada / rechazada',
            'ubicacion' => 'Mismo detalle',
            'accion' => 'Presentada no se regenera. Rechazada deja constancia para corregir.',
            'permiso' => 'presentar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Generar rectificativa RE',
            'ubicacion' => 'Mismo detalle (envío SJ)',
            'accion' => 'TXT RE que omite los registros 02 y 03.',
            'permiso' => 'rectificar-lsd-sueldos',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => 'Mismo detalle',
            'accion' => 'Borra una presentación que todavía se puede descartar.',
            'permiso' => 'borrar-lsd-sueldos',
        ],
    ],
    'reporte_definible' => [
        [
            'herramienta' => 'Ejecutar',
            'ubicacion' => 'Acciones del listado',
            'accion' => 'Corre la definición sobre una liquidación o el padrón. Exporta PDF, Excel o CSV.',
            'permiso' => 'listar-reporte-sueldos-definible',
        ],
        [
            'herramienta' => 'Desde plantilla / copiar',
            'ubicacion' => 'Toolbar y acciones',
            'accion' => 'Parte de una plantilla o duplica un listado para no pisar el publicado.',
            'permiso' => 'crear-reporte-sueldos-definible',
        ],
        [
            'herramienta' => 'Manual de esta pantalla',
            'ubicacion' => 'Cabecera',
            'accion' => 'Resumen operativo. El capítulo completo está en este manual.',
            'permiso' => '(sesión iniciada)',
        ],
    ],
    'reporte_premium' => [
        [
            'herramienta' => 'Fórmula / drill / Δ',
            'ubicacion' => 'Columnas y grilla de ejecución',
            'accion' => 'C1+C2 o si(), clic al recibo, y diferencia contra otra liquidación.',
            'permiso' => 'editar-reporte-sueldos-definible',
        ],
        [
            'herramienta' => 'Versión y ACL',
            'ubicacion' => 'Edición del listado',
            'accion' => 'Publicar, restaurar, y limitar quién ve o edita. Incluye nómina confidencial.',
            'permiso' => 'actualizar-reporte-sueldos-definible',
        ],
        [
            'herramienta' => 'Paridad y certificar',
            'ubicacion' => 'Pantalla de paridad',
            'accion' => 'Compara con Anita y emite el acta. Hace falta para publicar ciertos datasets.',
            'permiso' => 'actualizar-reporte-sueldos-definible',
        ],
        [
            'herramienta' => 'Suscripción',
            'ubicacion' => 'Panel de distribución',
            'accion' => 'Email por centro de costo, lugar, agrupamiento o legajo. Probar envío antes del cron.',
            'permiso' => 'editar-reporte-sueldos-definible',
        ],
    ],
];
