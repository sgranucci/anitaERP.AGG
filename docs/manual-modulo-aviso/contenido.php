<?php

/**
 * Manual de usuario — Módulo de avisos (catálogo, canales, WhatsApp, user task, eventos, SLA).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Avisos por módulo (premium)',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Qué es este módulo',
            'parrafos' => [
                'El módulo de avisos es el centro de notificaciones de negocio del ERP: define qué se avisa, a quién, por qué canal, con qué frecuencia y con qué condiciones. Reemplaza crons sueltos y mails hardcodeados por un catálogo configurable en pantalla.',
                'Convive con Mis aprobaciones (bandeja de trabajo) y con la campanita in-app. El mail empuja; la bandeja y la campanita son el hogar operativo; WhatsApp es un canal opt-in por empresa.',
            ],
            'items' => [
                'Configuración → Avisos por módulo: catálogo de tipos (activo, plantilla, destinatarios, programación, WhatsApp, SLA) + checklist operativo.',
                'Mis aprobaciones: pendientes de árbol, préstamos, asientos, TM, ingresos, etc.',
                'Campanita del header: avisos in-app (anita_notificacion) + Web Push opcional.',
                'WhatsApp: solo si la empresa tiene cuenta activa asociada.',
                'Reglas WHEN/IF/THEN y diseñador gráfico del árbol: complementan el circuito sin reemplazarlo.',
            ],
        ],
        [
            'titulo' => '2. Mapa de pantallas',
            'parrafos' => [
                'Todas las pantallas operativas viven bajo Configuración (salvo Mis aprobaciones y preferencias personales).',
            ],
            'tabla' => [
                'caption' => 'Pantallas del módulo',
                'headers' => ['Pantalla', 'URL / menú', 'Para qué'],
                'rows' => [
                    ['Avisos por módulo', 'configuracion/modulo-aviso', 'Activar tipos, plantillas, destinatarios, programación, canales.'],
                    ['Programador', 'mismo index con filtro solo_programables', 'Solo avisos con cron (día/hora/frecuencia).'],
                    ['Suscripciones de eventos', 'configuracion/modulo-aviso-evento', 'Evento de dominio → tipo de aviso extra.'],
                    ['Reglas de aviso', 'configuracion/aviso-regla', 'Motor WHEN/IF/THEN (activable). Casos fuera del árbol.'],
                    ['SLA y escalamiento', 'configuracion/user-task-sla', 'Horas SLA global, SLA por fuente, escaladores, bandeja desde tabla.'],
                    ['KPI SLA', 'configuracion/user-task-sla-kpi', 'Cumplimiento, tendencia semanal, vencidas por empresa/usuario.'],
                    ['Reasignación masiva', 'configuracion/user-task-reasignacion-masiva', 'Mover pendientes por usuario/rol con motivo.'],
                    ['WhatsApp por empresa', 'configuracion/empresa-whatsapp', 'Cuenta Meta + prueba de envío.'],
                    ['Auditoría WhatsApp', 'configuracion/whatsapp-envio-log', 'Histórico ok / error / omitido.'],
                    ['Celulares masivo', 'configuracion/usuarios-celular-masivo', 'Carga login;celular o email;celular.'],
                    ['Mis preferencias de avisos', 'configuracion/mis-preferencias-aviso', 'Opt-out mail/campanita/WA/digest + Web Push.'],
                    ['Mis aprobaciones', 'mis-aprobaciones', 'Bandeja de pendientes (user task) + onboarding push.'],
                    ['Árbol de aprobación', 'configuracion/arbolaprobacion', 'ABM del circuito + Diseñador gráfico.'],
                    ['Diseñador gráfico del árbol', 'configuracion/arbolaprobacion/{id}/disenador', 'Carriles CC/rama, nodos, flechas, edición de firmante.'],
                    ['Reemplazo destinatario', 'configuracion/reemplazo-destinatario-aviso', 'Suplencia con vencimiento.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Configurar un aviso (operador)',
            'parrafos' => [
                'En Avisos por módulo abrís el tipo (por ejemplo cheques_aging, prestamo_solicitud, recepcion_proveedor_ingresada).',
            ],
            'items' => [
                'Activo: si está apagado no envía nada.',
                'Asunto y texto: admiten placeholders del handler ({numero}, {proveedor}, etc.).',
                'Destinatarios: usuario del ERP y/o email fijo; se puede filtrar por empresa o centro de costo.',
                'Campanita: “También avisar in-app” (solo destinatarios con usuario_id).',
                'WhatsApp: “También WhatsApp” (requiere cuenta empresa + celular del usuario + preferencia del destinatario).',
                'Programación: día(s), hora, cada N semanas, hasta fecha (avisos programables).',
                'SLA (horas): opcional; informativo y base para futuros cortes ligados al tipo.',
                'Ejecutar ahora: dispara una corrida de prueba del programado (si aplica).',
            ],
        ],
        [
            'titulo' => '4. Canales: mail, campanita y WhatsApp',
            'parrafos' => [
                'Los tres canales se combinan. Un aviso puede usar uno, dos o los tres.',
            ],
            'tabla' => [
                'caption' => 'Condiciones para que salga cada canal',
                'headers' => ['Canal', 'Condiciones'],
                'rows' => [
                    ['Mail', 'Tipo activo + destinatarios con email válido + preferencia mail del usuario (si tiene usuario_id).'],
                    ['Campanita', 'Flag notificar_in_app + destinatarios con usuario_id + preferencia in_app.'],
                    ['WhatsApp', 'Flag notificar_whatsapp + cuenta empresa activa + celular válido + preferencia whatsapp + WHATSAPP_HABILITADO.'],
                ],
            ],
            'items' => [
                'Sin cuenta WhatsApp de la empresa el envío se omite (queda en auditoría como omitido).',
                'Driver actual por defecto: null (solo log). Para Meta Cloud: WHATSAPP_DRIVER=meta_cloud.',
                'Fuera de ventana 24h de Meta: WHATSAPP_USAR_PLANTILLA=true con plantilla aprobada.',
            ],
        ],
        [
            'titulo' => '5. WhatsApp paso a paso',
            'parrafos' => [
                'Tres capas tienen que alinearse. Si falla una, no hay mensaje.',
            ],
            'tabla' => [
                'caption' => 'Checklist WhatsApp',
                'headers' => ['Paso', 'Dónde', 'Qué cargar'],
                'rows' => [
                    ['1. Celular del destinatario', 'ABM Usuario o Celulares masivo', 'Formato 54911… (con país).'],
                    ['2. Cuenta de la empresa', 'WhatsApp por empresa', 'Phone number ID, access token, marcar Activo.'],
                    ['3. El aviso pide WA', 'Editar tipo de aviso', 'Checkbox También WhatsApp.'],
                    ['4. Preferencia del usuario', 'Mis preferencias de avisos', 'WhatsApp tildado (global y/o por tipo).'],
                    ['5. Driver de producción', '.env', 'WHATSAPP_DRIVER=meta_cloud (hoy null = dry-run).'],
                ],
            ],
            'items' => [
                'Carga masiva: una línea por usuario, login;54911… o email;54911… (# comenta la línea).',
                'Auditoría: cada intento queda en whatsapp-envio-log (ok, error, omitido).',
            ],
        ],
        [
            'titulo' => '6. Preferencias del destinatario',
            'parrafos' => [
                'Cada usuario puede optar por no recibir un canal. Prioridad: preferencia por tipo de aviso → preferencia global de canal → default recibir todo.',
            ],
            'items' => [
                'Global: desmarcar Correo / Campanita / WhatsApp apaga ese canal para todos los avisos.',
                'Por tipo: la grilla del mismo formulario permite silenciar un aviso concreto sin apagar el canal entero.',
                'Sin fila guardada = habilitado (modelo premium: opt-out, no opt-in obligatorio).',
            ],
        ],
        [
            'titulo' => '7. Programación y despacho',
            'parrafos' => [
                'El cron avisos:despachar-programados corre cada 15 minutos. Es el reloj unificado de tareas de negocio: avisos batch, listados workbench (08:05), Consulta Inteligente (08:10), Flash/definibles Contable-Sueldos (poll cada pasada; cada suscripción define su hora), digests de árbol, avisos Crown y AI digest/alerta si están habilitados.',
                'Respeta día/hora, cada N, fecha hasta, día del mes, feriados, pausa, dependencia y tope de reintentos. Los batch diarios no reenvían el mismo día si ya corrieron OK (programacion_ultima_en). Flash/definibles ignoran ese tope diario: el comando mira suscripciones vencidas.',
                'Cockpit: próximas corridas, calendario, fallos, alerta de job fallido, listados/CI, suscripciones Flash/definibles (alta, pausar, editar), jobs de plataforma (solo lectura), historial, impacto y dry-run. Rango: ventana horaria (hora hasta), dependencia con modo (OK hoy / última OK / sin fallo hoy). Al cambiar schedule hay que confirmar (audits). Ejecutar ahora: permiso ejecutar-modulo-aviso-programado (o actualizar).',
            ],
            'items' => [
                'Avisos clásicos: aging de cheques, OC abiertas, préstamo, libreta sanitaria, certificado ARCA, etc.',
                'Flash y definibles: alta y edición de horario/mails desde el Programador; el poll 15′ dispara el envío.',
                'Listados/CI: destinos en listado_envio_programado (también pausa/frecuencia desde el cockpit).',
                'Eventos de dominio (EventBus): recepción, préstamo, TM, ticket, OC estado, comprobante contabilizado, árbol, digests programados. Alimentan suscripciones y reglas.',
                'Motor de reglas: piloto + plantillas inactivas (TM, ticket, OC, factura, webhooks). Acción webhook_allowlist solo con URL en .env (ops_aprobaciones / ops_stock / ops_general). Kill-switch: Reglas o AVISO_REGLAS_HABILITADO.',
                'Jobs de plataforma (purges/syncs Kernel): visibles en el cockpit, no se ejecutan desde avisos.',
            ],
        ],
        [
            'titulo' => '8. Motor de eventos',
            'parrafos' => [
                'Algunos procesos emiten un evento de dominio (stock.recepcion.confirmada, stock.prestamo.solicitud, seguridad.ingreso_proveedor.creado, …). En Suscripciones de eventos se puede asociar ese evento a uno o más tipos de aviso adicionales.',
                'No reemplaza los envíos directos ya cableados en el proceso: suma avisos extra con condiciones JSON opcionales (por ejemplo {"empresa_id":1}).',
            ],
            'items' => [
                'Catálogo de eventos: config/modulo_aviso_eventos.php.',
                'Emit en código: ModuloAvisoEventBus::emit(evento, contexto).',
            ],
        ],
        [
            'titulo' => '9. User task, SLA y escalamiento',
            'parrafos' => [
                'user_task es el índice persistido de Mis aprobaciones. El sync (user-task:sincronizar, cada 10 min) materializa pendientes vivos y cierra los que ya no están.',
                'Al abrir una tarea se calcula vence_en = abierta_en + horas SLA (pantalla SLA o default 48 h). El cron horario user-task:escalar-vencidos notifica al titular y a los escaladores, marca la tarea y puede copiarla al primer escalador.',
            ],
            'items' => [
                'Escaladores: se cargan por ID de usuario en SLA y escalamiento (también fallback .env USER_TASK_ESCALAMIENTO_USUARIO_IDS).',
                'Reasignar: POST mis-aprobaciones/user-task/{id}/reasignar con usuario_id destino.',
                'Bandeja desde tabla: flag en SLA y escalamiento. Apagado = bandeja viva (recomendado hasta validar sync). Prendido = lista desde user_task (más rápido).',
            ],
        ],
        [
            'titulo' => '10. Roles típicos',
            'tabla' => [
                'caption' => 'Quién hace qué',
                'headers' => ['Rol', 'Acciones'],
                'rows' => [
                    ['Administrador / Enc. sistemas', 'Catálogo de avisos, WhatsApp empresa, SLA, eventos, celulares masivo, auditoría.'],
                    ['Usuario operativo', 'Mis aprobaciones, preferencias de canal, celular en su ficha si lo editan.'],
                    ['Firmante / aprobador', 'Bandeja + campanita + mail/enlace según el circuito.'],
                ],
            ],
        ],
        [
            'titulo' => '11. Checklist de puesta en marcha',
            'parrafos' => [
                'En Avisos por módulo hay un panel Estado operativo del pack (WA, celulares, reglas, push). Usalo como termómetro antes de dar por viva la operación.',
            ],
            'items' => [
                '1) Revisar tipos activos y destinatarios (sin destinatarios no envían).',
                '2) Cargar celulares (masivo o ficha) si van a usar WhatsApp.',
                '3) Asociar y activar cuenta WhatsApp por empresa; Enviar prueba a un usuario.',
                '4) Tildar También WhatsApp / in-app en los tipos que correspondan.',
                '5) Definir SLA global, SLA por fuente y escaladores.',
                '6) Revisar Reglas (motor + piloto) y Programador (batches 08:05/08:10, Flash/definibles). Suscribir eventos extra si hace falta.',
                '7) Pasar WHATSAPP_DRIVER=meta_cloud cuando la WABA y la plantilla estén listas.',
                '8) Pedir a firmantes que activen Web Push en Mis preferencias (iPhone: PWA en inicio).',
                '9) Monitorear Auditoría WhatsApp y log de reglas los primeros días.',
            ],
        ],
        [
            'titulo' => '12. Alineación premium mundial (SAP / Oracle / MyLogic)',
            'parrafos' => [
                'Comparación del estado actual del módulo frente a capacidades típicas de SAP (Alert Notification Service + Task Center), Oracle (ESS + Worklist + preferencias) y suites mid-market tipo MyLogic.',
            ],
            'tabla' => [
                'caption' => 'Matriz de capacidad',
                'headers' => ['Capacidad', 'SAP / Oracle', 'anitaERP hoy', 'Estado'],
                'rows' => [
                    ['Catálogo de avisos on/off', 'Sí', 'ModuloAvisoTipo', 'Alineado'],
                    ['Programación / job', 'ANS / ESS', 'cron + cada_n / hasta / ancla', 'Alineado'],
                    ['Condiciones / umbrales', 'Reglas', 'JSON en tipo y suscripción', 'Alineado'],
                    ['Destinatarios dinámicos', 'Roles / dueño', 'Usuario, email, filtros, dueño, dest. préstamo, ablanco', 'Alineado'],
                    ['Inbox / User Task', 'Task Center / Worklist', 'Mis aprobaciones + user_task + sync', 'Alineado'],
                    ['Mail + in-app', 'Nativo', 'Mail + campanita', 'Alineado'],
                    ['WhatsApp nativo gated', 'Raro (partner/BTP)', 'Cuenta por empresa + auditoría', 'Superior / diferencial'],
                    ['Preferencias de canal', 'Por usuario', 'Global + por tipo (opt-out)', 'Alineado'],
                    ['Event bus dominio → aviso', 'Event Mesh / Events', 'ModuloAvisoEventBus + ABM suscripciones', 'Alineado'],
                    ['SLA / escalamiento / reasignar', 'Fuerte', 'vence_en, cron escala, reasignar, escaladores UI', 'Alineado (v1)'],
                    ['Auditoría de envíos', 'Fuerte', 'whatsapp_envio_log + logs programados', 'Alineado WA; mail vía queue/log'],
                    ['Plantillas proveedor mensajería', 'Sí (si hay canal)', 'Meta plantilla flag', 'Alineado'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Veredicto',
                'headers' => ['Pregunta', 'Respuesta'],
                'rows' => [
                    ['¿Estamos alineados a premium mundial en avisos + tasks?', 'Sí, en el núcleo funcional (catálogo, schedule, multi-canal, preferencias, tasks, eventos, SLA).'],
                    ['¿Hay diferencial frente a SAP/Oracle?', 'WhatsApp nativo por cuenta-empresa con gate y auditoría; en los grandes suele ser integración partner.'],
                    ['¿Enterprise full (KPI / push / reasignación masiva)?', 'Sí: KPI SLA (pantalla + CSV), Web Push PWA, reasignación masiva por usuario/rol.'],
                    ['¿Motor de reglas + diseñador de árbol?', 'Sí: reglas WHEN/IF/THEN activables; diseñador gráfico de niveles del árbol.'],
                    ['¿Listo para producción con Meta?', 'Producto listo. Producción Meta: WABA + token + plantilla + WHATSAPP_DRIVER=meta_cloud + celulares.'],
                ],
            ],
        ],
        [
            'titulo' => '12.1 Enterprise + mejoras operación/UX',
            'items' => [
                'KPI SLA: tendencia semanal, vencidas por empresa, por fuente/usuario; export CSV.',
                'SLA por fuente: en user-task-sla podés fijar horas distintas por circuito (OC, asiento, TM, etc.); vacío = hereda el global.',
                'WhatsApp: botón Enviar prueba en la cuenta de la empresa; revisar log si falla.',
                'Cobertura: en Suscripciones de eventos ves emit en código, suscripciones y destinatarios por tipo (filas en amarillo = sin destinatarios).',
                'Web Push: banner en Mis aprobaciones + botón en preferencias. iOS: agregar a pantalla de inicio primero.',
                'Digest: opt-out en Mis preferencias (canal Digest); el mail trae link Abrir por cada pendiente.',
                'Deep-link: campanita, push y digest abren URL absoluta al documento/bandeja.',
                'Reasignación masiva: motivo recomendado; el destino recibe aviso in-app con el motivo.',
                'Checklist operativo en el index de Avisos por módulo.',
            ],
        ],
        [
            'titulo' => '12.2 Motor de reglas (WHEN / IF / THEN)',
            'parrafos' => [
                'Pantalla Configuración → Reglas de aviso. Sirve para casos que el árbol de aprobación no cubre: si ocurre un evento y se cumplen condiciones, se disparan acciones (enviar un tipo de aviso, campanita a usuarios o a roles).',
                'Interruptor global + “Activa” por regla. Kill-switch: AVISO_REGLAS_HABILITADO. Motor ON con piloto; hay plantillas inactivas para TM/ticket/OC/factura/webhooks (completar roles/clave y activar).',
                'Los emits de dominio (recepción, préstamo, TM, ticket, OC, comprobante, árbol, digests) mandan contexto rico (empresa_id, monto, tipo, url) para filtrar en el IF.',
            ],
            'items' => [
                'WHEN: evento del catálogo (el mismo que Suscripciones de eventos).',
                'IF: condiciones AND/OR sobre campos del contexto (empresa_id, monto, tipo, etc.). Sin filas = siempre verdadero.',
                'THEN: enviar tipo de aviso; notificar usuarios; notificar roles; webhook allowlist (clave de .env).',
                'Probar: en edición, simulá un contexto JSON sin enviar nada real.',
                'Log: aviso_regla_log; webhooks también en modulo_aviso_webhook_log.',
                'No reemplaza el árbol: es complemento para reglas de aviso/notificación.',
            ],
        ],
        [
            'titulo' => '12.3 Diseñador gráfico del árbol de aprobación',
            'parrafos' => [
                'Desde el listado (ícono diagrama) o la edición del árbol: Diseñador gráfico. Muestra el circuito en carriles (centro de costo + rama) con flechas entre niveles.',
                'Arrastrá nodos y Guardar posiciones. Auto layout reordena. Clic en un nodo: editar firmante (código + lupa/F1 o Auto), montos, estado doc y 2ª firma.',
                'Las posiciones se guardan en diseno_layout y no cambian la lógica de aprobación. Triggers OC/RE y alta de niveles nuevos siguen en el ABM tabular.',
            ],
        ],
        [
            'titulo' => '13. Lista de pruebas (aceptación)',
            'parrafos' => [
                'Recorrido sugerido para validar el pack en un entorno real. Marcar OK / Falló / N/A.',
            ],
            'tabla' => [
                'caption' => 'Pruebas funcionales',
                'headers' => ['#', 'Área', 'Prueba', 'Resultado esperado'],
                'rows' => [
                    ['1', 'Avisos', 'Abrir configuracion/modulo-aviso', 'Lista tipos + panel Estado operativo visible.'],
                    ['2', 'Avisos', 'Editar un tipo, destinatario activo, Ejecutar ahora (si programable)', 'Mail/log sin error; destinatario recibe o queda auditado.'],
                    ['3', 'Preferencias', 'Mis preferencias: desmarcar mail de un tipo; disparar ese aviso', 'No llega mail; otros canales según tilde.'],
                    ['4', 'Preferencias', 'Activar notificaciones del navegador', 'Permiso OK; mensaje Push activado (HTTPS).'],
                    ['5', 'Push', 'Mis aprobaciones: banner si no hay push; Activar ahora', 'Banner desaparece; llega push al crear aviso in-app.'],
                    ['6', 'WhatsApp', 'Cuenta empresa: datos + Activa; Enviar prueba a tu usuario', 'Con driver null: mensaje de driver. Con meta_cloud + celular: ok en log.'],
                    ['7', 'WhatsApp', 'Auditoría whatsapp-envio-log tras prueba', 'Fila ok / error / omitido coherente.'],
                    ['8', 'Celulares', 'Carga masivo login;celular de un usuario de prueba', 'Celular visible en ficha; WA puede resolverse.'],
                    ['9', 'Eventos', 'Suscribir un evento a un tipo; confirmar recepción/préstamo/ingreso', 'Aviso extra del tipo suscripto (además del directo).'],
                    ['10', 'Eventos', 'Cobertura: fila amarilla = tipo sin destinatarios', 'Se ve el resumen de destinatarios.'],
                    ['11', 'Reglas', 'Crear regla IF monto gte X THEN campanita; Simular JSON', 'Simulación cumple/no cumple sin enviar.'],
                    ['12', 'Reglas', 'Activar motor + regla; confirmar recepción con monto alto', 'Campanita/aviso y fila en log de reglas.'],
                    ['13', 'Reglas', 'Apagar motor global', 'Mismo evento no dispara reglas; suscripciones de eventos sí.'],
                    ['14', 'SLA', 'Configurar horas distintas por fuente; sync user-task', 'vence_en respeta horas de esa fuente.'],
                    ['15', 'SLA', 'KPI SLA: rango fechas + CSV', 'Tendencia semanal; tablas fuente/usuario/empresa.'],
                    ['16', 'SLA', 'Escalar vencidos (comando o esperar cron)', 'Copia/aviso a escalador; tarea marcada escalada.'],
                    ['17', 'Reasignación', 'Masiva con motivo usuario A → B', 'Pendientes en B; aviso in-app con motivo.'],
                    ['18', 'Digest', 'Opt-out digest en preferencias; corrida digest', 'Usuario sin digest no recibe mail diario.'],
                    ['19', 'Digest', 'Con digest on y pendientes', 'Mail con columna Ir a cada documento.'],
                    ['20', 'Bandeja', 'Mis aprobaciones: abrir desde campanita/push', 'URL absoluta; llega al documento o detalle.'],
                    ['21', 'Árbol UI', 'Listado → Diseñador gráfico de un árbol con varios CC', 'Carriles, nodos, flechas visibles.'],
                    ['22', 'Árbol UI', 'Arrastrar nodo + Guardar posiciones; recargar', 'Posiciones persistidas.'],
                    ['23', 'Árbol UI', 'Auto layout', 'Nodos reordenados por carril/nivel.'],
                    ['24', 'Árbol UI', 'Editar firmante con lupa; Aplicar; abrir ABM', 'Mismo usuario en grilla de niveles.'],
                    ['25', 'Árbol UI', 'Cambiar nivel/montos en panel; aprobar caso real', 'Circuito sigue reglas de negocio (monto/CC).'],
                ],
            ],
        ],

        [
            'titulo' => '14. Comandos útiles (admin)',
            'items' => [
                'php artisan avisos:despachar-programados',
                'php artisan user-task:sincronizar',
                'php artisan user-task:sincronizar --usuario={id}',
                'php artisan user-task:escalar-vencidos',
                'php artisan anita-notificacion:purge',
                'Regenerar este manual: php docs/manual-modulo-aviso/generar.php',
            ],
        ],
        [
            'titulo' => '15. Relación con otros manuales',
            'parrafos' => [
                'Mis aprobaciones y circuitos (manual aparte) detalla la bandeja, el árbol y el dual channel mail/enlace. Este manual se centra en el catálogo de avisos, canales, WhatsApp, eventos, SLA, reglas y diseñador.',
                'Arquitectura: docs/arquitectura/anita-notificaciones.md y docs/arquitectura/modulo-aviso-premium.md.',
            ],
        ],
    ],
];
