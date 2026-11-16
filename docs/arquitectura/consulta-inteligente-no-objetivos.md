# Consulta Inteligente — no-objetivos (fuera de alcance a propósito)

**Fecha:** 15 nov 2026  
**Módulo:** Dirección → Consulta Inteligente  
**Contexto:** reporte CI vs premium (ThoughtSpot Spotter / Power BI Copilot / Tableau Agent).  
**Veredicto de producto:** a la par (y en varios puntos arriba) del premium **dentro del ERP**. Los tres ítems de abajo **no** son deuda técnica pendiente: son límites conscientes. Reabrirlos solo con un caso de negocio explícito.

Complementa el manual de usuario (`docs/manual-consulta-inteligente/`) y el canvas de producto `ci-vs-premium-nov2026`.

---

## Qué sí es CI

Capa semántica gobernada sobre anitaERP:

- pregunta NL → QBE verificable (diccionario + patrones + LLM opcional con failover);
- narrativa grounded (conteo / suma / promedio / % vs mes / aging);
- explorador workbench, tablero, alertas, join/cruce, snapshots, envío Excel, enlace compartible, PDF de pregunta;
- mismos permisos `listar-*` del módulo origen; no inventa SQL libre.

---

## 1. Agente que escribe

### Qué es en el premium

Un copiloto que **modifica** el sistema o crea artefactos analíticos:

- escribe medidas DAX / fórmulas / vistas;
- genera un reporte completo “desde cero”;
- dispara acciones en otros sistemas (Jira, Salesforce, Slack con write).

### Qué hace CI hoy

Solo **consulta**. El flujo es pregunta → preview QBE → números / tablero / explorador. No graba asientos, OC, depósitos, ni inventa un informe libre fuera del catálogo.

### Por qué queda afuera

En anitaERP la escritura operativa va por pantallas con permisos, validaciones y (cuando aplica) HITL. Mezclar “el agente escribió” con contabilidad/stock/caja rompe auditoría y gobernanza.

### Cuándo reabrir

Solo si hay un caso con:

1. acción acotada (una skill, no “hacer cualquier cosa”);
2. confirmación humana obligatoria;
3. permiso y rastro en `audits` / auditoría CI;
4. sin bypass de cierres ni de reglas de negocio.

Referencia de estilo HITL ya existente en la plataforma IA (`AiConsultaOperativa*`), no en CI.

---

## 2. Embed / MCP

### Qué es

| Concepto | Significado |
|---|---|
| **Embed** | Meter el tablero / NL dentro de otra app (portal, intranet, app móvil) vía SDK. |
| **MCP** | Exponer el motor como herramienta para que un agente externo (Claude, Cursor, etc.) consulte datos por un protocolo estándar. |

### Qué hace CI hoy

Vive **dentro** de anitaERP: rutas web, login, menú, permisos. No hay SDK embebible ni servidor MCP publicado para afuera.

### Por qué queda afuera

La seguridad de CI es la del ERP (sesión + `listar-*` por entidad). Un embed/MCP mal acotado filtraría el modelo semántico sin el mismo contrato de permisos.

### Cuándo reabrir

- **Embed:** portal de gerencia o app interna que ya autentica contra el ERP y necesita el tablero sin menú completo.
- **MCP:** integración controlada con la plataforma IA interna (mismo usuario, mismos permisos, allowlist de entidades), no “MCP abierto a Internet”.

Si se implementa: reutilizar `ConsultaInteligentePermisosSupport` + auditoría; nunca API key global sin usuario.

---

## 3. Warehouse (lago / Fabric / Snowflake)

### Qué es

Base analítica **aparte** del ERP operativo: Snowflake, Databricks, Microsoft Fabric, BigQuery, etc. Años de historia, muchas fuentes, modelos hechos para BI y Copilot sobre capacidad paga.

### Qué hace CI hoy

Lee el **MySQL operativo** `anitaERP` (cheques, facturas, cobranzas, OC, …) con la misma lógica de los listados workbench. Es la capa semántica **del ERP**, no un data warehouse multi-fuente.

### Por qué queda afuera

Un warehouse es otro producto (ETL, modelos, costo de capacidad, gobierno de datos). CI no reemplaza ni compite con Fabric/ThoughtSpot Cloud sobre el lago; responde preguntas operativas **con los datos que ya gobierna el ERP**.

### Cuándo reabrir

Si aparece un requisito de:

- históricos masivos que no deben vivir en MySQL operativo;
- cruce con fuentes que no están en anitaERP;
- equipo de datos que ya opera un warehouse y quiere **consumir** (no reemplazar) las definiciones semánticas del ERP.

En ese caso CI seguiría siendo la definición de negocio; el warehouse sería el motor de cómputo, no al revés.

---

## Resumen para el futuro

| Ítem | Estado | Reabrir si… |
|---|---|---|
| Agente que escribe | No-objetivo | Skill acotada + HITL + auditoría |
| Embed / MCP | No-objetivo | Caso portal/IA interna con mismos permisos |
| Warehouse | Fuera de alcance | Lago ya existente; CI como semántica, no como ETL |

**No** tratar estos tres como “falta para igualar premium”. El premium de BI y CI resuelven planos distintos: lago + agente vs consulta gobernada dentro del ERP.
