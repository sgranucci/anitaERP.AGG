# Programador de tareas — estudio vs premium mundial

**Fecha:** 15 nov 2026  
**Módulo:** Configuración → Avisos por módulo → **Programador de avisos** (`?solo_programables=1`)  
**Referencia UI externa:** MyLogic «PROGRAMADOR DE TAREAS» (aviso de cheques por vencer: Acciones / Rango temporal / Sitios / Eventos).  
**Canvas:** `programador-tareas-vs-premium` (abrir junto al chat).  
**Complementa:** `docs/arquitectura/modulo-aviso-premium.md`, `docs/manual-modulo-aviso/`, `docs/arquitectura/anita-notificaciones.md`.

---

## Veredicto (actualizado 16 nov 2026)

| Plano | Estado |
|---|---|
| **Avisos + User Task + canales** | **A la par / arriba** (WA nativo, SLA KPI, reglas, vacaciones) |
| **Programador de tareas (núcleo)** | **A la par MyLogic/SAP job** — cockpit, historial, impacto, dry-run, día mes/feriados, pausa/deps/reintentos |
| **Usabilidad 1+2+3 (16 nov)** | **Hecho** — ficha Acciones/Rango/Destinatarios/Ejecución; calendario semana/mes; reintento desde historial con impacto modal |
| **Usabilidad 4–7 + reloj listados/CI** | **Hecho** — alerta fallo; piloto reglas (OFF); empty states; despacho listados/CI vía Programador |
| **Automatización genérica** | **No-objetivo** (sin script / Power Automate libre) |

El equivalente MyLogic (cheques por vencer + rango) **ya está**. El roadmap A–D del cockpit está en código. Para **superar** premium el foco pasa de “más motor” a **experiencia de operador**: menos pantallas, más claridad, un reloj único.

---

## 1. Qué tenemos hoy

### 1.1 Núcleo (equivalente MyLogic)

| Capacidad | Implementación |
|---|---|
| Catálogo on/off | `ModuloAvisoTipo` + destinatarios |
| Programación día/hora/cada N/hasta | `ModuloAvisoProgramacionSupport` (alineado MyLogic) |
| Vista «Programador» | Index con `solo_programables=1` |
| Despacho unificado | `avisos:despachar-programados` cada 15′ |
| Bitácora por tipo | `modulo_aviso_programado_log` (últimas 10 en editar) |
| Ejecutar ahora / simular | UI + `--simular` CLI |
| 10 tareas programables | mapa `config/modulo_aviso.programados` → comando Artisan |

**Programables actuales:** libreta sanitaria, cheques aging, OC alertas, entrega sin recepción, contrato vencimiento, suscripción sin comprobante, borrador factura proveedor, certificado ARCA, recordatorio ingreso proveedor, préstamo.

### 1.2 Pack alrededor (ya premium en avisos)

- Event bus + suscripciones (`ModuloAvisoEventBus`, catálogo chico)
- Motor reglas WHEN/IF/THEN (dormido por default)
- Mail + campanita + WhatsApp gated + Web Push
- Preferencias por canal/tipo, reemplazo vacaciones
- User task + SLA por fuente + KPI + escalamiento + reasignación masiva
- Cobertura «quién recibe», checklist operativo, manual PDF/Word

### 1.3 Schedulers hermanos

| Sistema | Cómo se programa |
|---|---|
| Listados workbench | `listado.envios_workbench` → Programador **08:05** |
| Consulta Inteligente | `consulta_inteligente.envios_programados` → **08:10** |
| Reportes definibles Contable/Sueldos | Programador **cada pasada** (suscripción decide día/hora) |
| Flash AGG / Flash Crown | Programador cada pasada (gate AGG/Crown) |
| Digest + recordatorios árbol | Programador 08:30 / 09:00 |
| Tesorería Crown avisos | Programador (hora parámetro; solo Crown) |
| AI digest / alerta umbral | Programador 08:15 / 08:20 (si AI habilitado) |
| Abonos día-del-mes, purges, syncs | Kernel (jobs de plataforma; no-objetivo) |

Reloj de negocio unificado en `avisos:despachar-programados`. Motor reglas ON + 3 piloto activas (16 nov).  
Potencia (16 nov tarde): ventana horaria + modos dependencia; emits EventBus ampliados; plantillas de reglas; webhook allowlist auditado; alta suscripción Flash/definible desde Programador; jobs plataforma read-only en cockpit.

---

## 2. Qué aportan los premium del mercado

| Sistema | Qué tomar | Qué no copiar |
|---|---|---|
| **MyLogic** Programador | UX Acciones / Rango / Sitios / Eventos en un solo ABM; frecuencia semanal con días; impact analyzer | UI densa 2010; scripts libres |
| **SAP** Job Scheduling / ANS | Catálogo de jobs, run history, retries, alertas de fallo de job | Complejidad BTP/partner para cada canal |
| **Oracle** ESS / Worklist | Preferencias, worklist, schedule con ventana | SuiteScript-like abierto |
| **NetSuite** Scheduled Scripts / Workflow | Triggers tiempo + evento; dependencias de job | Script server-side editable por cliente |
| **Dynamics 365** + Power Automate | Flujos evento → acción multi-paso; conectores | Motor low-code externo sin auditoría ERP |
| **Odoo** Automated + Scheduled Actions | Condición sobre modelo + acción tipada; cron UI | Acciones demasiado genéricas sin gate |
| **Salesforce** Flow / Scheduled Paths | Diseñador visual de caminos; dry-run | Plataforma aparte del ERP |

### Capacidades a incorporar (prioridad)

| # | Capacidad | Por qué supera | Esfuerzo orientativo |
|---|---|---|---|
| **P0** | **Cockpit unificado** del Programador (calendario semana/mes + próximas corridas + fallos) | MyLogic/SAP muestran el plan; nosotros solo listamos tipos | 3–5 d |
| **P0** | **Run history global** (todas las corridas, filtro OK/error, reintentar) | Observabilidad enterprise; hoy 10 filas por tipo | 2–3 d |
| **P0** | **Incorporar al mapa** listados programados + CI + reportes definibles (al menos visibles; luego despacho) | Un solo lugar = sensación premium | 3–5 d |
| **P1** | **Impact preview** («quién recibe / qué consulta / cuántos docs») antes de Ejecutar ahora | MyLogic «Analizador de impacto» | 2 d |
| **P1** | **Ampliar triggers de tiempo**: día del mes, ventana horaria, feriados/skip | Cubre abonos y cierres que hoy están en Kernel | 2–3 d |
| **P1** | **Catálogo de eventos** amplio (emit en más servicios) + UI Sitios/empresa | Cierra la pestaña Eventos/Sitios de MyLogic | 2 d + emits |
| **P1** | Encender gobernado el **motor de reglas** (casos piloto + auditoría) | Diferencial vs mid-market; ya está construido | 1–2 d ops |
| **P2** | Acciones tipadas extra: **enviar listado/CI**, webhook allowlist, deep-link job | Sin script libre; sí acciones de catálogo | 3–5 d |
| **P2** | Dependencias / «no correr si falló X»; pause/resume cola | SAP Job Scheduling | 3 d |
| **P2** | Retries con backoff + alerta de job fallido (vía ModuloAviso) | Autosanación operativa | 2 d |
| **P3** | Aprobación de cambio de schedule (diseñar vs ejecutar) | Gobernanza enterprise | 2–3 d |

---

## 3. Diferenciales que ya nos ponen arriba

1. **WhatsApp nativo** por cuenta-empresa + auditoría (en SAP/Oracle suele ser partner).  
2. **Misma seguridad del ERP** (destinatarios operativos, empresa/CC, vacaciones).  
3. **User task + SLA KPI + Web Push + reasignación** en el mismo pack.  
4. **Reglas WHEN/IF/THEN** con kill-switch y simulación (listas, dormidas).  
5. **Cheques aging** y digests de compras ya migrados al despachador (no cron suelto).  
6. **Sin script libre del cliente** = menos superficie de incidente; el premium mid-market se rompe acá.

---

## 4. No-objetivos (fuera de alcance a propósito)

Igual que CI: no tratarlos como «falta para igualar premium».

| Ítem | Por qué no |
|---|---|
| **Script / código editable por operador** (SuiteScript, Apex, PHP en UI) | Rompe auditoría, cierres y permisos |
| **Power Automate / Zapier genérico** | Motor externo sin el contrato de `audits` / HITL del ERP |
| **HTTP outbound libre sin allowlist** | Riesgo de exfiltración / phishing interno |
| **Alta de tareas nuevas 100 % desde UI sin handler en código** | Cada aviso debe tener handler testeable; el mapa en `config` es intencional |
| **App store nativa** | Ya documentado en `modulo-aviso-premium.md` |
| **Reemplazar Kernel técnico** (purge PDF, padrones, sync Waitry) | Son jobs de plataforma, no de negocio; el Programador es de **tareas de negocio** |

Reabrir solo con caso de negocio + HITL + permiso + rastro en audits.

---

## 5. Roadmap — estado 15 nov 2026

```text
Fase A (P0) — HECHO
  · Cockpit (KPIs, próximas, fallos)
  · Historial global filtrable
  · Hermanos listados/CI visibles en el Programador

Fase B (P1) — HECHO (piloto reglas = doc, motor sigue OFF)
  · Analizador de impacto
  · Día del mes + skip feriados
  · Catálogo de eventos ampliado
  · Piloto motor reglas: activar solo con OK operador

Fase C (P2) — HECHO (despacho hermanos unificado 16 nov)
  · acciones_tipadas + webhooks_allowlist (vacío)
  · Dependencias + pause + reintentos max
  · listado.envios_workbench (07:05) + consulta_inteligente.envios_programados (07:10)
    vía avisos:despachar-programados; Kernel 07:05/07:10 comentado

Fase D (P3) — HECHO
  · Permiso ejecutar-modulo-aviso-programado (diseñar vs ejecutar)
  · Dry-run de schedule 30 días en UI
```

Criterio de cierre «superamos premium»:

1. Un operador encuentra **todas** las tareas de negocio recurrentes en una pantalla.  
2. Ve **próxima corrida**, **última**, **fallo** y puede **reintentar** con impacto previo.  
3. Puede armar **evento → condición → canal** sin deploy (reglas) sin poder escribir código.  
4. Los diferenciales (WA, SLA, preferencias) siguen siendo el argumento comercial.

---

## 6. Comprobación rápida (estado actual)

```bash
rg "programados|solo_programables|avisos:despachar" config/modulo_aviso.php app/Console resources/views/configuracion/modulo_aviso
rg "listado_envio_programado|enviar-consultas-inteligentes|enviar-listados" app config
php -l app/Support/Configuracion/ModuloAvisoProgramacionSupport.php
```

Si no puedo responder «el operador vio el impacto del schedule y dijo que sí», no migrar despachos hermanos al poller en el mismo cambio (regla dry-run / BD producción).
