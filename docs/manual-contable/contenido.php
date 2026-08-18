<?php

/**
 * Manual de usuario — Módulo Contable (cierres y aperturas de período).
 * Audiencia: contaduría / administración sin experiencia técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo Contable · Cierres y aperturas de período',
    'version' => '1.2',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual describe las pantallas de Cierre de período y Aperturas programadas del Módulo Contable en Anita ERP. Están pensadas para que Contaduría bloquee, por empresa y por módulo de negocio, las fechas contables que ya no deben modificarse, y para habilitar excepciones temporales cuando haga falta.',
                'Menú: Módulo Contable → Aprobaciones y períodos → Cierre de período / Aperturas programadas. Las acciones visibles dependen de los permisos de su usuario.',
                'Desde el Centro de ayuda o el botón Manual de la pantalla de cierre puede volver a este documento en cualquier momento.',
                'Este documento cubre solo cierres y aperturas de período. Los asientos de cierre de rendiciones (máquinas, bingo, estacionamiento, vending) tienen manual propio: Contaduría — Cierres de rendiciones. Los estados financieros definibles tienen el Manual de Reportes contables definibles.',
            ],
            'items' => [
                'El cierre puede ser general (todos los módulos) o por módulo (cobranzas, caja, stock, facturación, etc.).',
                'La agenda mensual permite programar con anticipación: fecha de ejecución, hora y fecha de cierre (tope contable).',
                'Si necesita operar en un período ya cerrado, solicite una apertura programada al encargado.',
            ],
        ],
        [
            'titulo' => '2. Conceptos básicos',
            'captura_id' => 'flujo_cierre',
            'parrafos' => [
                'Antes de operar conviene tener claros estos términos. Aparecen en la agenda, el histórico y los mensajes de bloqueo.',
            ],
            'tabla' => [
                'caption' => 'Glosario rápido',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['Cierre de período', 'Registro que bloquea operaciones con fecha anterior o igual a la “fecha hasta”.'],
                    ['Fecha de cierre (fecha hasta)', 'Última fecha incluida en el bloqueo (inclusive). Es editable al programar.'],
                    ['Fecha de ejecución', 'Día en que el sistema (o usted) aplica el cierre programado.'],
                    ['Hora de ejecución', 'Hora del día. En pantalla 24:00 significa «fin de día»; el job la traduce a CONTABLE_CIERRE_HORA_FIN_DIA (default 23:50). Vacío = mismo fin de día.'],
                    ['Alcance / módulo', 'Ámbito del cierre: general o un proceso (cobranza, caja, stock, facturación…).'],
                    ['Cierre general', 'Bloquea todos los módulos y congela el snapshot de saldos contables.'],
                    ['Agenda del mes', 'Grilla para programar cierres del mes en curso (o del mes elegido).'],
                    ['Apertura programada', 'Permiso temporal para operar fechas ya cerradas, limitado a un módulo y un usuario.'],
                    ['Fecha de jornada', 'En facturación/gastronomía, la fecha operativa de la jornada; el cierre de ventas la usa para validar.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Cómo funciona el bloqueo',
            'captura_id' => 'circuito_bloqueo',
            'parrafos' => [
                'Cuando intenta grabar una cobranza, un movimiento de stock, un asiento o una factura en una fecha ya cerrada para ese módulo, el sistema lo impide e indica hasta qué fecha está cerrado el período.',
                'Excepciones: (1) apertura programada activa para su usuario y ese alcance; (2) permiso especial de operar en período cerrado; (3) facturación electrónica WSFE/CAE (la fecha la valida AFIP/ARCA). Facturación manual o CAEA sí aplica el cierre.',
                'Si hay un cierre general y otro por módulo, prevalece el más restrictivo (la fecha hasta más reciente entre ambos).',
            ],
            'tabla' => [
                'caption' => 'Ejemplo práctico (agenda de agosto)',
                'headers' => ['Módulo', 'Ejecución', 'Fecha cierre', 'Efecto'],
                'rows' => [
                    ['Recepción de proveedores', '05/08 a las 24:00', '31/07', 'Desde el fin del 05/08 no se graban recepciones con fecha ≤ 31/07.'],
                    ['Facturación', '01/08 a las 24:00', '31/07', 'Bloquea por fecha de jornada ≤ 31/07 (no solo por fecha del comprobante).'],
                    ['General (todos)', 'Cuando Contaduría lo indique', '31/07', 'Bloquea todos los módulos y congela saldos.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Pantalla de cierre de período',
            'captura_id' => 'cierre_agenda',
            'herramientas_grupos' => [
                ['titulo' => 'Filtros y cabecera', 'clave' => 'cierre_cabecera'],
            ],
            'parrafos' => [
                'Pantalla: Contable → Aprobaciones y períodos → Cierre de período.',
                'Elija la empresa y el mes/año de la agenda, luego pulse Consultar. Verá el cierre general vigente (si existe), la agenda de módulos del mes, el formulario de cierre inmediato y el histórico.',
                'La fecha de cierre por defecto al abrir un mes nuevo es el último día del mes anterior (ej. agenda agosto → 31/07). Puede cambiarla en cada fila.',
            ],
            'items' => [
                'Sin empresa seleccionada (si tiene más de una) no se muestra la agenda editable.',
                'El botón Manual (libro) abre esta guía en otra pestaña.',
            ],
        ],
        [
            'titulo' => '5. Herramientas de la agenda (detalle)',
            'captura_id' => 'cierre_herramientas',
            'herramientas_grupos' => [
                ['titulo' => 'Barra de la agenda', 'clave' => 'cierre_barra_agenda'],
                ['titulo' => 'Por cada módulo (fila)', 'clave' => 'cierre_fila'],
                ['titulo' => 'Cierre inmediato e histórico', 'clave' => 'cierre_inmediato_historico'],
            ],
            'parrafos' => [
                'Este capítulo es el detalle operativo de cada herramienta de la pantalla de cierre. Use la tabla de abajo como referencia rápida mientras trabaja.',
            ],
            'tabla' => [
                'caption' => 'Resumen de la agenda por módulo',
                'headers' => ['Campo / acción', 'Qué hace'],
                'rows' => [
                    ['Fecha ejecución', 'Día en que se aplica el cierre (automático o manual).'],
                    ['Hora', 'HH:MM o 24:00 (fin de día → CONTABLE_CIERRE_HORA_FIN_DIA, default 23:50). Vacío = fin de día.'],
                    ['Fecha cierre', 'Tope contable inclusive (editable).'],
                    ['Observación', 'Texto libre que queda en el cierre al ejecutarse.'],
                    ['Guardar (disquete)', 'Deja la fila programada en estado pendiente.'],
                    ['Aplicar ahora (play)', 'Ejecuta ya ese módulo si la fecha de ejecución ya llegó (no espera la hora).'],
                    ['Cancelar (ban)', 'Anula una programación pendiente o con error.'],
                    ['Programar todos', 'Misma fecha/hora/cierre para todos los módulos del mes.'],
                    ['Cerrar todos ahora', 'Cierre general inmediato + snapshot de saldos.'],
                    ['Aplicar pendientes', 'Ejecuta todos los pendientes del mes con fecha ≤ hoy.'],
                ],
            ],
            'parrafos2' => [
                'El job automático del servidor revisa periódicamente las programaciones pendientes y las aplica cuando ya pasó la fecha y la hora configuradas. Si indicó 24:00 (o dejó vacío), usa la hora de fin de día configurada en el servidor (CONTABLE_CIERRE_HORA_FIN_DIA, típicamente 23:50).',
            ],
        ],
        [
            'titulo' => '6. Paso a paso: programar el mes',
            'captura_id' => 'cierre_programar_todos',
            'parrafos' => [
                '1) Entre a Cierre de período y seleccione empresa + mes (ej. agosto).',
                '2) En cada módulo complete fecha de ejecución, hora (o deje 24:00) y fecha de cierre. Pulse Guardar.',
                '3) Alternativa: use Programar todos, cargue fechas comunes y confirme.',
                '4) Si la fecha de ejecución ya es hoy o pasó, puede pulsar Aplicar ahora en la fila, o Aplicar pendientes para varias a la vez.',
                '5) Si Contaduría necesita cerrar todo de inmediato, use Cerrar todos ahora (cierre general).',
            ],
            'items' => [
                'Los módulos ya ejecutados no se reprograman desde Programar todos.',
                'La fecha de cierre no puede ser futura.',
                'Borrar el último cierre del histórico deshace solo ese alcance (general o módulo).',
            ],
        ],
        [
            'titulo' => '7. Aperturas programadas',
            'captura_id' => 'apertura_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Solicitud y gestión', 'clave' => 'apertura'],
            ],
            'parrafos' => [
                'Pantalla: Contable → Aprobaciones y períodos → Aperturas programadas.',
                'Sirve para pedir permiso temporal de operar fechas ya cerradas. Indique empresa, rango de fechas de operación, módulo (alcance), duración en horas o días, usuario a habilitar y motivo.',
                'El encargado aprueba o habilita desde el aviso por correo (enlace firmado) o desde la pantalla. Al vencer la ventana de tiempo, el permiso se cierra solo.',
            ],
            'items' => [
                'Una apertura “General” habilita todos los módulos; una apertura de un módulo solo ese alcance.',
                'Puede rechazar solicitudes pendientes o revocar aperturas activas.',
            ],
        ],
        [
            'titulo' => '8. Permisos y buenas prácticas',
            'parrafos' => [
                'Los permisos típicos son: listar cierre, ejecutar cierre (incluye programar y aplicar), borrar último cierre, listar/solicitar/aprobar/habilitar/revocar aperturas, y el permiso excepcional de operar en período cerrado.',
                'Buenas prácticas: programe el mes al inicio; use cierres por módulo cuando las áreas cierran en fechas distintas; reserve el cierre general para el cierre formal de Contaduría; documente observaciones; use aperturas solo con motivo claro y duración acotada.',
            ],
            'tabla' => [
                'caption' => 'Checklist rápido',
                'headers' => ['Antes de cerrar', 'Control'],
                'rows' => [
                    ['¿La fecha de cierre es la correcta?', 'Revise el tope (no solo la fecha de ejecución).'],
                    ['¿Hora 24:00 es lo deseado?', 'Si necesita cerrar a media tarde, indique HH:MM.'],
                    ['¿Hay operaciones pendientes del mes?', 'Coordine con cada área antes de Aplicar ahora.'],
                    ['¿Necesita excepción puntual?', 'Apertura programada, no borrar el cierre.'],
                ],
            ],
        ],
        [
            'titulo' => '9. Preguntas frecuentes',
            'parrafos' => [
                '¿Por qué no puedo grabar una factura de julio el 2 de agosto? Porque el módulo Facturación está cerrado hasta 31/07 y la fecha de jornada cae en julio.',
                '¿Puedo cambiar la fecha de cierre de 31/07 a 30/07? Sí, al programar o reprogramar (si aún no está ejecutado).',
                '¿Qué pasa si programo ejecución el 5/08 a las 24:00? El job aplica el cierre al fin de ese día (hora efectiva CONTABLE_CIERRE_HORA_FIN_DIA, default 23:50). Antes puede usar Aplicar ahora si la fecha ya es ≤ hoy.',
                '¿Cerrar todos ahora cierra cada fila de la agenda? Registra un cierre general que bloquea todos los módulos; las filas de agenda por módulo son independientes.',
            ],
        ],
    ],
];
