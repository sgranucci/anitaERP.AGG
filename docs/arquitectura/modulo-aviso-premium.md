# Módulo de avisos — arquitectura premium

Complementa el manual de usuario (`docs/manual-modulo-aviso/`) y `docs/arquitectura/anita-notificaciones.md` (campanita).

**Estudio Programador vs premium mundial (15 nov 2026):** `docs/arquitectura/programador-tareas-vs-premium.md` + canvas `programador-tareas-vs-premium`.

**Estado 16 nov 2026:** Programador unifica listados (08:05), CI (08:10), definibles Contable/Sueldos + Flash (poll 15′), digests árbol, Crown avisos, AI digest/alerta. Motor de reglas **ON** + 3 piloto **activas**. Jobs de plataforma (purges, syncs, padrones) siguen en Kernel.

## Piezas

| Pieza | Rol |
|---|---|
| `ModuloAvisoTipo` + destinatarios | Catálogo configurable |
| `ModuloAvisoService` | Despacho mail + bridges in-app / WhatsApp |
| `avisos:despachar-programados` | Cron programables |
| `ModuloAvisoEventBus` + `modulo_aviso_evento_suscripcion` | Eventos de dominio → avisos |
| `WhatsappCanalSupport` + `empresa_whatsapp_cuenta` | Gate por empresa |
| `WhatsappEnvioService` + `whatsapp_envio_log` | Envío + auditoría |
| `UsuarioAvisoPreferencia*` | Opt-out global y por tipo |
| `UserTask` + sync + SLA | Índice Mis aprobaciones + vencimiento/escala |

## Enterprise full

| Pieza | Ubicación |
|---|---|
| KPI SLA | `configuracion/user-task-sla-kpi` (+ CSV, tendencia, por empresa) |
| Web Push | VAPID + `web_push_subscription`; preferencias + banner Mis aprobaciones |
| Reasignación masiva | `configuracion/user-task-reasignacion-masiva` (motivo + aviso destino) |

## Mejoras operación / UX

| Pieza | Ubicación |
|---|---|
| Prueba WhatsApp | `EmpresaWhatsappController@enviarPrueba` |
| SLA por fuente | `user_task_sla_fuente` + pantalla SLA |
| Cobertura quién recibe | `ModuloAvisoCoberturaSupport` en eventos |
| Digest opt-out | canal `digest` en preferencias |
| Deep-link | `urlAppAbsoluta` en notif / push / digest |

## Motor de reglas (fase 4 sin app nativa)

| Pieza | Ubicación |
|---|---|
| Tablas | `aviso_regla`, `aviso_regla_config`, `aviso_regla_log` |
| Motor | `AvisoReglaMotorService` + `AvisoReglaCondicionSupport` (wrapper → `ReglaNegocioCondicionSupport`; BRE sync: `docs/arquitectura/regla-negocio-motor.md`) |
| UI diseñador | `configuracion/aviso-regla` (WHEN/IF/THEN) |
| Enganche | `ModuloAvisoEventBus::emit` → evalúa reglas si motor ON |
| Default | motor_habilitado = false (listo, dormido) |

## Diseñador gráfico del árbol

| Pieza | Ubicación |
|---|---|
| Pantalla | `configuracion/arbolaprobacion/{id}/disenador` |
| Layout | `arbolaprobacion.diseno_layout` (JSON posiciones; no afecta aprobación) |
| Grafo | `ArbolAprobacionDisenoSupport` (carriles CC+rama → nodos nivel) |
| Edición | panel lateral: firmante / montos / estado doc / 2ª firma |

El ABM tabular sigue siendo la fuente completa (triggers OC/RE). El diseñador visualiza y edita nodos del circuito.

## Veredicto vs SAP / Oracle

Alineados en el núcleo, enterprise full, operación/UX, motor de reglas y diseñador visual del árbol. App nativa store queda fuera a propósito. Diferencial: WhatsApp nativo gated por empresa.
