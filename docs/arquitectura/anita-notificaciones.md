# Centro de avisos in-app (campanita)

## Qué es

No es un chat. Es un **centro de notificaciones** por usuario:

- Campanita en el header (`theme/lte/header`)
- Tabla `anita_notificacion`
- Servicio `App\Services\Configuracion\AnitaNotificacionService`
- API JSON: `notificaciones/feed|contador|leer|leer-todas`

El mail sigue siendo el canal “empuja”; la campanita es el hogar de trabajo dentro del ERP.

## Tipos

| Tipo | Constante | Origen |
|------|-----------|--------|
| `aprobacion` | `TIPO_APROBACION` | Mail de pendiente / recordatorio del árbol |
| `digest` | `TIPO_DIGEST` | Digest matutino Mis aprobaciones |
| `sistema` | `TIPO_SISTEMA` | Productores extra (tickets, legajos OC, etc.) |

## Productores actuales

1. `ArbolaprobacionService::enviaCorreo` → `avisarAprobacionPendiente`
2. `ArbolAprobacionDigestService` → `avisarDigest`
3. `TicketTareaAsignadaNotificacionService` → `avisarSistema` (creador del ticket)
4. `OrdencompraDevolverAComprasNotificacionService` → `avisarSistemaAUsuarios` (sector COMPRAS)
5. `OrdencompraLegajoAutorizadoNotificacionService` → autorización y recordatorio Gastro

Los productores extra se pueden apagar con `ANITA_NOTIFICACION_PRODUCTORES_EXTRA=false` sin tocar aprobación/digest.

## Configuración (`config/anita_notificacion.php`)

| Clave / env | Default | Rol |
|-------------|---------|-----|
| `ANITA_NOTIFICACION_HABILITADO` | `true` | Master switch in-app |
| `ANITA_NOTIFICACION_PRODUCTORES_EXTRA` | `true` | Tickets / legajos |
| `ANITA_NOTIFICACION_WEBHOOKS_HABILITADO` | **`false`** | Fan-out Slack/Teams |
| `ANITA_NOTIFICACION_SLACK_WEBHOOK_URL` | vacío | Fallback global Slack |
| `ANITA_NOTIFICACION_TEAMS_WEBHOOK_URL` | vacío | Fallback global Teams (Workflows) |
| `ANITA_NOTIFICACION_WEBHOOKS_POR_USUARIO` | JSON vacío | Override por `usuario_id` |
| `ANITA_NOTIFICACION_RETENCION_DIAS_LEIDAS` | `90` | Purga leídos |
| `ANITA_NOTIFICACION_RETENCION_DIAS_NO_LEIDAS` | `180` | Purga no leídos (0 = no tocar) |
| `ANITA_NOTIFICACION_PURGE_HORA` | `03:45` | Cron diario |
| `ANITA_NOTIFICACION_PURGE_HABILITADO` | `true` | Enciende el schedule |

Las claves viejas `ARBOLAPROBACION_WEBHOOKS_*` siguen leyéndose como fallback de compatibilidad.

## Webhooks (dejar inactivos)

Estado de producto: **apagados**. Un webhook global publica todos los avisos de todos los usuarios en un canal → ruido. Cuando se active:

1. Preferir `por_usuario` (canal/DM por firmante).
2. En Teams usar URL de la app **Workflows**, no Connectors clásicos (`outlook.office.com/webhook/`), retirados en mayo 2026.
3. El payload `MessageCard` sigue siendo válido en Workflows; el botón puede no renderizar (el link también va en el texto).

Ejemplo JSON de overrides:

```bash
ANITA_NOTIFICACION_WEBHOOKS_POR_USUARIO='{"12":{"teams_url":"https://prod-xx.logic.azure.com/..."}}'
```

## Purga

```bash
php artisan anita-notificacion:purge
php artisan anita-notificacion:purge --dry-run
php artisan anita-notificacion:purge --dias-leidas=60 --dias-no-leidas=120
```

Schedule: diario ~03:45 si `retencion.habilitado`.

## Cómo enganchar un módulo nuevo

```php
try {
    app(\App\Services\Configuracion\AnitaNotificacionService::class)->avisarSistema(
        $usuarioId,
        'Título corto',
        'Detalle opcional',
        url('ruta/destino'),
        ['origen' => 'mi_modulo', 'id' => $id]
    );
} catch (\Throwable) {
    // Nunca romper el flujo de negocio por el aviso.
}
```

Para varios destinatarios: `avisarSistemaAUsuarios($ids, ...)`.

## UI

- JS: `public/assets/js/anita-notificaciones.js` (poll cada 45s + toast)
- Estilos: `public/assets/css/custom.css` (`.anita-notif-*`)
