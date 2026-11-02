<?php

/**
 * Manual de usuario — Módulo de tickets.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de tickets',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve',
            'parrafos' => [
                'El módulo de tickets es el pedido de atención a un área. Quien tiene un problema carga el ticket. El área lo recibe, lo asigna o lo toma, lo trabaja y lo cierra. El solicitante sigue el estado, comenta y, si el trabajo ya se cerró y hace falta seguir, pide que se reabra.',
                'Hay un solo documento, el ticket. Lo que cambia entre áreas es quién se hace cargo. Sistemas y Tecnología son la misma área destino: se llama Tecnología y trabaja por asignación. Un responsable elige al técnico. Mantenimiento trabaja por cola: el ticket entra sin técnico y alguien del área lo toma.',
                'Logística y Laboratorio figuran como áreas destino, suspendidas. No tienen circuito propio en este manual.',
            ],
        ],
        [
            'titulo' => '2. Dónde está',
            'parrafos' => [
                'El menú es Módulo de Tickets. La carga la usa quien pide el trabajo. La bandeja y la administración las usa el área que atiende. Las tablas y la configuración las usa quien parametriza.',
            ],
            'tabla' => [
                'caption' => 'Pantallas',
                'headers' => ['Pantalla', 'Menú', 'Para qué'],
                'rows' => [
                    ['Carga de Tickets', 'Módulo de Tickets → Carga de Tickets', 'Alta, consulta de los propios y comentario del solicitante.'],
                    ['Bandeja de tickets', 'Módulo de Tickets → Bandeja de tickets', 'Cola de Mantenimiento, tickets tomados y, para el encargado, toda el área.'],
                    ['Adm. de Tickets', 'Módulo de Tickets → Adm. de Tickets', 'Listado de Tecnología, asignación de técnico, novedades y cierre.'],
                    ['Informe estadístico', 'Módulo de Tickets → Informe estadístico', 'Cantidad y tiempo insumido por sala, técnico y estado.'],
                    ['Configuración de tickets', 'Módulo de Tickets, o Configuración → Configuración por módulo → Tickets', 'Modo de cada área, aviso al centro de costo y exclusiones de copia.'],
                    ['Tablas de tickets', 'Módulo de Tickets → Tablas de tickets', 'Turnos, áreas de destino, tareas, sectores, técnicos y categorías.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Datos del ticket',
            'parrafos' => [
                'En la carga se completan fecha, empresa, sala, sector, área de destino, categoría, subcategoría, título y comentario. El título es el resumen. El comentario es el detalle. Se pueden adjuntar archivos.',
                'El ticket guarda quién lo generó y la empresa. En Administración se puede abrir a nombre de otra persona. En Carga de Tickets el dueño es siempre quien está logueado. También puede indicarse el bien de uso intervenido.',
                'La categoría y la subcategoría salen de las tablas del módulo. El sector es el lugar de origen del pedido, no el área que lo va a atender. El área de destino es quien lo atiende: Tecnología o Mantenimiento.',
            ],
        ],
        [
            'titulo' => '4. Estados',
            'parrafos' => [
                'El ticket y cada novedad de la tarea tienen su propio estado. El de la grilla es el del ticket. La historia queda en la ficha, con fecha, usuario y observación.',
            ],
            'tabla' => [
                'caption' => 'Estado del ticket',
                'headers' => ['Estado', 'Cuándo se usa'],
                'rows' => [
                    ['Sin asignar', 'Recién cargado, o devuelto a la cola. Todavía no tiene técnico.'],
                    ['Pendiente', 'Ya tiene técnico y todavía no se está ejecutando.'],
                    ['En ejecución', 'El técnico lo está trabajando.'],
                    ['Finalizado', 'Cerrado. Sale de la bandeja. Guarda fecha, hora y tiempo insumido.'],
                    ['Suspendido', 'Frenado, sin cerrar.'],
                    ['Reasignar', 'Hay que pasarlo a otro técnico.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Estado de la novedad de la tarea',
                'headers' => ['Estado', 'Cuándo se usa'],
                'rows' => [
                    ['Pendiente', 'La tarea está cargada y no empezó.'],
                    ['Asignada', 'Se registró la asignación del técnico.'],
                    ['En ejecución', 'Se está haciendo.'],
                    ['En espera', 'Depende de un tercero o de un repuesto.'],
                    ['Suspendida', 'La tarea está frenada.'],
                    ['Reasignar', 'Hay que cambiar el técnico.'],
                    ['Finalizada', 'La tarea terminó. Se carga fecha y tiempo insumido, en minutos.'],
                ],
            ],
        ],
        [
            'titulo' => '5. Carga de un ticket',
            'parrafos' => [
                'Cualquier usuario con el permiso de carga entra a Carga de Tickets y usa el alta. Elige sala, sector, área de destino, categoría y subcategoría, escribe título y comentario, y puede adjuntar archivos.',
                'El ticket nace en Sin asignar. Si el área es Tecnología, además se avisa al correo de soporte de Tecnología. Si el área es Mantenimiento, no se elige técnico en esta pantalla: el pedido queda en la cola.',
            ],
            'tabla' => [
                'caption' => 'Alta según el área',
                'headers' => ['Área de destino', 'Qué queda al guardar', 'Quién se entera'],
                'rows' => [
                    ['Tecnología (Sistemas)', 'Sin asignar, sin técnico, hasta que Administración lo asigne.', 'El solicitante ve el ticket. Soporte de Tecnología recibe el aviso de alta.'],
                    ['Mantenimiento', 'Sin asignar, con una tarea vacía y sin técnico, lista para tomar.', 'Entra a la cola de la bandeja. No sale el aviso de alta de Tecnología.'],
                ],
            ],
            'parrafos2' => [
                'Quien tiene el permiso de ver el sector (mismo centro de costo) consulta en Carga los tickets de su centro, no solo los propios. El resto ve los que generó.',
            ],
        ],
        [
            'titulo' => '6. Sistemas y Tecnología',
            'parrafos' => [
                'Sistemas y Tecnología son la misma área destino. En las tablas se llama Tecnología. La atienden los roles Enc-sistemas, Técnico de Tecnología y Gerencia de Tecnología. El centro de costo de referencia es el 92.',
                'El modo es asignación por administrador. El ticket entra en Sin asignar. Nadie lo toma de una cola. En Adm. de Tickets un responsable le carga la tarea y el técnico. Ahí pasa a Pendiente.',
                'Al crearse, sale el aviso Alta de ticket de Tecnología. El destinatario inicial es soporte@grupoagg.com. El asunto trae el número y el título. El texto trae sala, sector, categoría, quién lo generó, quién lo cargó, estado y comentario. Los destinatarios se editan en la configuración de avisos del módulo.',
            ],
            'tabla' => [
                'caption' => 'Cómo se trabaja un ticket de Tecnología',
                'headers' => ['Paso', 'Quién', 'Pantalla', 'Qué pasa'],
                'rows' => [
                    ['1. Pedir', 'Cualquier usuario', 'Carga de Tickets', 'Elige área Tecnología, completa título y comentario, y guarda. Queda Sin asignar.'],
                    ['2. Ver el listado', 'Tecnología', 'Adm. de Tickets', 'Abre en curso: Pendiente y En ejecución. Se puede filtrar por estado, fechas, sala, técnico, título y el resto de columnas.'],
                    ['3. Alcance', 'Técnico del área', 'Tilte Ver todos', 'Con la tilde se ven los tickets del área. Sin la tilde, solo los asignados al usuario que está logueado.'],
                    ['4. Asignar', 'Quien administra', 'Ficha del ticket', 'Carga la tarea, el técnico y, si corresponde, el turno. El estado pasa a Pendiente.'],
                    ['5. Avisar', 'Sistema', 'Mail y aviso interno', 'El solicitante recibe que hay una tarea asignada. El técnico recibe el aviso de asignación, si tiene correo en su usuario.'],
                    ['6. Trabajar', 'Técnico', 'Ficha, novedades', 'Cambia el estado de la tarea, escribe la novedad y, al terminar, carga fecha y minutos.'],
                    ['7. Cerrar', 'Técnico o administrador', 'Ficha', 'El ticket pasa a Finalizado. Quedan fecha, hora y tiempo insumido total.'],
                ],
            ],
            'parrafos2' => [
                'El listado de Administración tiene la misma consulta del resto de Anita: búsqueda, QBE, diseño de columnas, vistas guardadas, cortes, gráfico y envío programado del listado por mail. Pdf, Excel y Csv salen con el filtro activo.',
                'La bandeja también está en el menú de Tecnología. Como el área no es de cola, no aparece la solapa Cola ni el botón Tomar. El número del menú cuenta los tickets asignados al usuario. El trabajo se hace en Adm. de Tickets.',
            ],
        ],
        [
            'titulo' => '7. Mantenimiento',
            'parrafos' => [
                'Mantenimiento es un área destino propia y está en modo cola. El pedido entra sin técnico. El técnico lo toma desde la bandeja, o el encargado se lo asigna. Los roles son Enc-mantenimiento y op-Obras y Mantenimiento.',
                'Para tomar, el usuario tiene que tener una ficha de técnico de Mantenimiento vinculada a su usuario del ERP. Si no la tiene, la bandeja lo dice y hay que asociarla en Tablas de tickets, Técnicos.',
            ],
            'tabla' => [
                'caption' => 'Bandeja de Mantenimiento',
                'headers' => ['Solapa', 'Qué lista', 'Quién la ve'],
                'rows' => [
                    ['Cola', 'Tickets del área sin técnico, en Sin asignar.', 'Quien opera el área en modo cola.'],
                    ['Míos', 'Los que tiene tomados quien está logueado.', 'El técnico, por su ficha.'],
                    ['Todos', 'Toda el área, abiertos.', 'Enc-mantenimiento, supervisor y administrador.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Acciones de la cola',
                'headers' => ['Acción', 'Quién', 'Qué pasa'],
                'rows' => [
                    ['Tomar', 'Técnico con ficha en el área', 'Se asigna el ticket. Pasa a Pendiente. Si otro lo tomó en el mismo momento, el segundo recibe que ya fue tomado. Se avisa al solicitante.'],
                    ['Asignar', 'Encargado', 'Elige un técnico del área sin esperar a que lo tomen. También pasa a Pendiente y se avisa al solicitante.'],
                    ['Liberar', 'El técnico asignado, o el encargado', 'Vuelve a la cola, sin técnico, en Sin asignar.'],
                ],
            ],
            'parrafos2' => [
                'Después de tomarlo se abre la ficha para seguir: estados, novedades, artículos usados, archivos y comentarios. El técnico comenta recién cuando el ticket es suyo. El solicitante, el encargado y el supervisor pueden comentar sin esa condición.',
                'La grilla marca la antigüedad: normal, atención desde los 2 días y urgente desde los 5. Muestra hasta 200 abiertos. Los finalizados no aparecen. El número del menú, para quien opera la cola, cuenta los que siguen sin técnico.',
                'Si el encargado abre el ticket desde Administración y ya carga un técnico, nace directo en Pendiente y no pasa por la cola. Enc-mantenimiento sin ficha propia ve todas las áreas que estén en modo cola.',
            ],
        ],
        [
            'titulo' => '8. Comentarios, avisos y reapertura',
            'parrafos' => [
                'En la ficha, el comentario de la tarea queda en la historia. Si lo escribe el técnico, el correo le llega a quien generó el ticket. Si lo escribe el solicitante, el correo le llega al técnico asignado.',
                'Cuando el técnico comenta, se puede copiar al centro de costo. Eso se prende por centro en Configuración de tickets. Hay una lista de personas que no entran en esa copia. El tope de la copia es de 100 direcciones.',
                'Un ticket finalizado se puede reabrir desde la carga, con el botón de pedir más ayuda. Hace falta un comentario. Lo pide quien generó el ticket.',
            ],
            'tabla' => [
                'caption' => 'Avisos',
                'headers' => ['Cuándo', 'A quién', 'Área'],
                'rows' => [
                    ['Alta del ticket', 'Soporte de Tecnología (aviso Alta de ticket de Tecnología)', 'Solo Tecnología'],
                    ['Asignación o toma', 'Mail y aviso interno al solicitante. Aviso al técnico si tiene correo.', 'Tecnología y Mantenimiento'],
                    ['Comentario del técnico', 'Quien generó el ticket, y la copia del centro si está activa', 'Las dos'],
                    ['Comentario del solicitante', 'El técnico asignado', 'Las dos'],
                ],
            ],
        ],
        [
            'titulo' => '9. Informe y configuración',
            'parrafos' => [
                'Informe estadístico consulta por sala, técnico y estado, y separa el tiempo del ticket del tiempo del técnico. Se exporta en el formato del listado. Hace falta el permiso del informe.',
                'Configuración de tickets tiene tres bloques. El modo de cada área: Asignación por administrador, o Cola / el técnico toma el ticket. Si un área no tiene modo cargado, queda en asignación. Hoy Tecnología está en asignación y Mantenimiento en cola. El segundo bloque prende el aviso de comentario al centro de costo. El tercero excluye usuarios de esa copia.',
            ],
        ],
        [
            'titulo' => '10. Tablas',
            'parrafos' => [
                'Las tablas están en Módulo de Tickets, Tablas de tickets. Sin ellas la carga no tiene sector, categoría ni técnico para asignar.',
            ],
            'tabla' => [
                'caption' => 'Maestros',
                'headers' => ['Tabla', 'Para qué'],
                'rows' => [
                    ['Turnos', 'Turno en el que se programa la tarea.'],
                    ['Áreas de destino', 'Quién atiende. Tecnología y Mantenimiento están activas. Logística y Laboratorio están suspendidas.'],
                    ['Tareas', 'Tipo de trabajo que se carga en la ficha. En la cola de Mantenimiento la primera tarea nace vacía, con el título del ticket, hasta que se toma.'],
                    ['Sectores', 'Sector de origen del pedido.'],
                    ['Técnicos', 'Ficha del técnico, su área y el usuario del ERP. En Mantenimiento ese vínculo es el que habilita Tomar.'],
                    ['Categorías', 'Categoría y subcategoría del ticket. La subcategoría es la que se guarda en el pedido.'],
                ],
            ],
        ],
    ],
];
