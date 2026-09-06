<?php

/**
 * Manual de usuario — Mis aprobaciones y circuitos de autorización.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Mis aprobaciones y circuitos',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual explica la bandeja Mis aprobaciones, el dual channel (mail + avisos in-app), digest y recordatorios, el circuito de artículos (tipo AR) y las opciones de configuración para el administrador.',
                'Regla de producto: el mail avisa; la bandeja es el hogar de trabajo. Ambos canales quedan auditados con las etiquetas [vía bandeja] y [vía enlace].',
                'Documentación en el Centro de ayuda del ERP. Desde el header también puede abrir Mis aprobaciones (icono de bandeja) y la campanita de Avisos.',
            ],
            'items' => [
                'El badge de Aprobaciones en el header muestra cuántos pendientes tiene asignados.',
                'La campanita lista avisos recientes del árbol y del digest.',
                'El circuito de artículos se enciende o apaga desde Configuración general (sin tocar el servidor).',
            ],
        ],
        [
            'titulo' => '2. Idea de producto (canales)',
            'parrafos' => [
                'Hay varios canales que trabajan juntos. No se reemplazan entre sí.',
            ],
            'tabla' => [
                'caption' => 'Canales de aprobación',
                'headers' => ['Canal', 'Rol'],
                'rows' => [
                    ['Mail / enlace hash', 'Avisa y permite aprobar desde afuera (portal público del árbol).'],
                    ['Mis aprobaciones', 'Hogar de trabajo in-app (sesión + permiso).'],
                    ['Campanita (Avisos)', 'Notificación operativa in-app al firmante.'],
                    ['Slack / Teams', 'Opcional; apagado por defecto (webhooks).'],
                ],
            ],
        ],
        [
            'titulo' => '3. Mis aprobaciones (bandeja)',
            'parrafos' => [
                'URL: mis-aprobaciones. También desde el botón Aprobaciones del header.',
                'Permiso principal del árbol: aprobar-mis-aprobaciones-arbol. Otras fuentes respetan el permiso de su módulo.',
            ],
            'tabla' => [
                'caption' => 'Fuentes que agrega la bandeja',
                'headers' => ['Fuente', 'Qué entra'],
                'rows' => [
                    ['Árbol', 'RE, OC, SP, OV, RS, PE, PP y AR (artículos).'],
                    ['Indumentaria', 'Solicitudes de prenda pendientes.'],
                    ['Salida de bienes', 'Préstamos / salidas pendientes.'],
                    ['Asientos', 'Cola de aprobación contable.'],
                    ['Transferencias', 'TM pendientes.'],
                    ['Ingreso proveedor', 'Tickets de seguridad.'],
                ],
            ],
            'items' => [
                'Acciones: Ver / Editar, Aprobar, Rechazar (con motivo), Reenviar mail, aprobación masiva con reglas estrictas.',
                'Filtros: fuente, tipo, urgencia y búsqueda por texto.',
                'El contador del header suma las fuentes a las que el usuario tiene acceso.',
            ],
        ],
        [
            'titulo' => '4. Motor del árbol (documentos)',
            'parrafos' => [
                'Los circuitos de documentos se configuran en Configuración → Árbol de aprobación. Cada tipo tiene su tipocomprobante.',
            ],
            'tabla' => [
                'caption' => 'Tipos de árbol',
                'headers' => ['Código', 'Nombre en el ABM'],
                'rows' => [
                    ['RE', 'Requisiciones'],
                    ['OC', 'Ordenes de compra'],
                    ['SP', 'Solicitudes de pago'],
                    ['OV', 'Ordenes de venta'],
                    ['RS', 'Requisiciones de sala'],
                    ['PE', 'Pedidos'],
                    ['PP', 'Propuesta de pagos'],
                    ['AR', 'Artículos'],
                ],
            ],
            'items' => [
                'First-wins: un firmante del nivel cierra; el resto queda Sin efecto.',
                'Reemplazos de firmante: se respetan; un cron restaura los vencidos.',
                'Recordatorio por ítem: solo si en el ABM recordatorio = S (job diario ~09:00).',
                'Digest matutino: un mail por firmante con resumen (~08:30).',
                'Para artículos (AR): niveles con centro de costo 0 y montos amplios (0 → valor alto).',
            ],
        ],
        [
            'titulo' => '5. Campanita y webhooks',
            'parrafos' => [
                'La campanita del header abre el panel Avisos. No es un chat: es un centro de notificaciones por usuario (tabla anita_notificacion).',
                'Se alimenta con pendientes/recordatorios del árbol, el digest matutino y avisos de sistema (tareas de ticket, legajos OC devueltos o autorizados).',
                'Slack/Teams están APAGADOS por default. Si se activan, preferir webhook por usuario (no un canal global). En Teams usar Workflows, no Connectors clásicos. Variables: ANITA_NOTIFICACION_WEBHOOKS_* (ver docs/arquitectura/anita-notificaciones.md).',
                'Retención: el cron anita-notificacion:purge limpia avisos viejos (~90 días leídos / ~180 no leídos).',
            ],
        ],
        [
            'titulo' => '6. Circuito de artículos (tipo AR)',
            'parrafos' => [
                'Cuando el circuito está activo, el alta de un artículo nace PENDIENTE y sigue un árbol tipo Artículos según el uso. Si está inactivo, nace ACTIVO como siempre (comportamiento histórico).',
                'Activación: Configuración → Configuración general del sistema → grupo Aprobaciones / Stock → Circuito de aprobación al alta de artículos (Activo / Inactivo). Ese valor manda sobre el default del .env.',
            ],
            'tabla' => [
                'caption' => 'Estados del artículo',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['ACTIVO', 'Liberado para uso operativo (OC, stock, consumo).'],
                    ['PENDIENTE', 'En circuito / esperando firmas.'],
                    ['RECHAZADO', 'Rechazado; Compras debe corregir y guardar para reabrir.'],
                    ['INACTIVO / BAJA', 'Como siempre en el maestro.'],
                ],
            ],
            'items' => [
                'Hard block: solo ACTIVO es seleccionable en OC, stock y consumo.',
                'Paso de dominio (Gastro/Lab/…): puede editar ficha y fórmulas, luego firmar.',
                'Orden secuencial: dominio → Contaduría.',
                'Cambio de uso o cuentas en un ACTIVO reabre el circuito completo.',
                'Cambio de uso en PENDIENTE reevalúa el árbol.',
                'Quién crea: permiso Spatie crear-articulos (asignable a roles).',
            ],
        ],
        [
            'titulo' => '7. Router por uso de artículo',
            'parrafos' => [
                'En Stock → Uso de artículos se define cómo rutea cada uso cuando el circuito está activo.',
            ],
            'tabla' => [
                'caption' => 'Modos de aprobación por uso',
                'headers' => ['Modo', 'Efecto'],
                'rows' => [
                    ['default', 'Usa el árbol activo tipo Artículos (prioriza nombres con default o contadur).'],
                    ['auto', 'Auto-aprueba: queda ACTIVO al guardar.'],
                    ['arbol', 'Usa el árbol específico elegido (Gastro, Lab, RRHH, Marketing, etc.).'],
                ],
            ],
            'items' => [
                'Checklist: activar parámetro → crear árboles tipo Artículos → asignar modos en usos → probar un alta.',
                'Si el circuito está ON y no hay árbol usable (y el uso no es auto), el SKU queda PENDIENTE hasta configurar.',
            ],
        ],
        [
            'titulo' => '8. Flujos de ejemplo',
            'parrafos' => [
                'Gastronomía (modo arbol): Compras crea → PENDIENTE → encargado Gastro completa fórmulas → Contaduría → ACTIVO.',
                'Default / Contaduría: Compras crea → PENDIENTE → Contaduría → ACTIVO.',
                'Auto: Compras crea → ACTIVO inmediato.',
                'Rechazo: firmante rechaza → RECHAZADO → Compras edita y guarda → reabre (PENDIENTE + nuevos movimientos).',
            ],
        ],
        [
            'titulo' => '9. Operación diaria',
            'tabla' => [
                'caption' => 'Quién hace qué',
                'headers' => ['Rol', 'Acción'],
                'rows' => [
                    ['Firmante', 'Abre Aprobaciones o la campanita; aprueba/rechaza; en dominio edita ficha/fórmulas y firma.'],
                    ['Compras', 'Crea artículos; si RECHAZADO, corrige y guarda para reabrir.'],
                    ['Contaduría', 'Suele ser el último nivel: valida cuentas y libera.'],
                    ['Administrador', 'Configura árboles, usos, permisos y el parámetro de Configuración general.'],
                ],
            ],
        ],
        [
            'titulo' => '10. Seguridad y apagado',
            'parrafos' => [
                'Tres canales válidos: bandeja (sesión + permiso + ownership), enlace hash del mail, y módulos de dominio (sesión + permiso del módulo).',
                'Para apagar el circuito de artículos: Configuración general → Circuito de aprobación al alta de artículos = Inactivo. Las altas nuevas vuelven a nacer ACTIVO. Los SKU ya PENDIENTE/RECHAZADO siguen así hasta resolverlos.',
            ],
        ],
    ],
];
