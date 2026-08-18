# Crown en PostgreSQL — estrategia por etapas

**Estado:** agendada (Fase 6 lista para ejecutar cuando Crown dé servidor).  
**Fecha:** 2026-08-17  
**Código base:** dual-driver listo (Fases 1–5 + boy scout 3.x).  
**AGG:** permanece en MySQL. No hay cutover AGG → Postgres.

Documento hermano: `docs/arquitectura/portabilidad-base-datos.md`.

---

## 1. Decisión

| Pregunta | Respuesta |
|----------|-----------|
| ¿Crown puede arrancar en Postgres? | **Sí**, como instalación nueva sobre el mismo código. |
| ¿Flip de producción AGG? | **No.** |
| ¿Big-bang (todos los módulos el día 1)? | **No.** Implementación por etapas. |
| ¿Pruebas / smoke ampliados ahora? | **No.** Quedan para cuando se ejecute cada etapa. |
| ¿Qué se agenda ya? | **Fase 6 (ops)** + este plan de etapas + criterios de go/no-go. |

### Por qué por etapas

1. El lab vacío ya demostró schema + indexes principales; **no** demostró cada proceso de escritura con datos reales de Crown.
2. Un fallo de dialecto (UNIQUE, `ORDER BY` ambiguo, collation, cast) se detecta y se corrige en helpers (`SqlDialectSupport` / `DbContencionSupport`) **sin** ramificar el código por cliente.
3. Cada etapa habilita un módulo, lo usa un usuario de negocio, y solo entonces se abre la siguiente.
4. Si algo no cierra, se frena esa etapa (stop-the-line), se boy-scoutea, y se reintenta. No se “parchea solo Crown”.

---

## 2. Principios de implementación

1. **Misma base de código** que AGG. Crown solo cambia `DB_CONNECTION=pgsql`, `EMPRESA`, y config de negocio.
2. **Instancia nueva (greenfield).** No pgloader de datos AGG salvo pedido explícito de histórico.
3. **Stop-the-line.** Error SQL/dialectal → helper dual-driver → smoke de la etapa → continuar.
4. **Sin Anita auto-import** en vacío hasta que Crown tenga Anita (si aplica). Reusar el patrón `EMPRESA` / `AnitaSyncIndexSupport` del lab.
5. **Backup Postgres en paralelo** a los runbooks MySQL; no borrar `deploy/backup/` MySQL.
6. **Collation español en PG:** ICU `es-ES` (o `es-ES-x-icu` / collation custom). No `utf8mb4_spanish_ci` en migraciones.
7. **Gate de migraciones de menú/rol:** `EntornoEmpresaSupport` con código Crown (definir al arrancar; no reutilizar `LAB_PG`).

---

## 3. Fase 6 — Ops Postgres (agendada)

Ejecutar **cuando Crown provea servidor** (o un staging Crown dedicado). No correr contra AGG ni contra el lab de `.211` como si fuera prod.

| # | Ítem | Entregable | Criterio de hecho |
|---|------|------------|-------------------|
| 6.1 | PostgreSQL 16 + usuario/BD de app | Cluster instalado, DB vacía | `psql` conecta; sin puerto expuesto a Internet sin necesidad |
| 6.2 | PHP `pdo_pgsql` en el host de app | Extensión cargada | `php -m \| grep pgsql` |
| 6.3 | Collation ICU español | Collation documentada en el cluster | `SELECT * FROM pg_collation WHERE collname ILIKE '%es%';` o collation creada |
| 6.4 | `.env` Crown | `DB_CONNECTION=pgsql`, host, credenciales, `EMPRESA=<CROWN>`, `APP_KEY` | `php artisan migrate:status` habla con PG |
| 6.5 | `.env.example` + comentario ops | Variables PG documentadas (sin secretos) | Misma clave en example + config |
| 6.6 | Backup `pg_dump` (+ WAL archive si RPO lo exige) | Scripts bajo `deploy/backup/` **paralelos** (p. ej. `backup-pg.sh`, `RESTORE-pgsql.md`) | Dump restaurable en drill |
| 6.7 | Restore drill | Corrida documentada en staging | Restaurar a BD paralela y arrancar app en solo lectura o smoke |
| 6.8 | Monitoreo básico | Disco WAL, conexiones, autovacuum | Alerta mínima o checklist semanal |

**Fuera de Fase 6 (va en etapas de negocio):** seed de maestros Crown, UAT de módulos, ARCA, Anita.

Checklist de arranque (copiar al ticket ops):

```text
[ ] PG 16 instalado / managed
[ ] pdo_pgsql en PHP del host app
[ ] Collation ICU es-ES creada o confirmada
[ ] .env Crown: DB_CONNECTION=pgsql + EMPRESA
[ ] migrate --force en vacío (EMPRESA Crown, no AGG)
[ ] Primer dump + restore drill OK
[ ] Documentar RPO/RTO acordado con Crown
```

---

## 4. Etapas de implementación de negocio

Orden sugerido. Ajustar si Crown no usa un módulo (saltar etapa, no reordenar a la ligera: maestros → stock → compras → caja → ventas → contable).

| Etapa | Alcance | Qué validar (cuando se implemente) | Stop-the-line típico |
|-------|---------|------------------------------------|----------------------|
| **A — Plataforma** | Fase 6 + `migrate` + seed mínimo (empresa, usuario admin, roles, menú base) | Login, inicio, permisos | FK/UNIQUE al seed; collation |
| **B — Maestros** | Empresa(s), depósitos, artículos, clientes, proveedores, cuentas, CC | ABM crear/editar/listar + export PDF/Excel de 1–2 indexes | `ORDER BY id` ambiguo; filtros `LOWER`/cast |
| **C — Stock** | Movimientos, saldos depósito, recepción (si aplica) | Alta movimiento, kardex, saldo | Signo cantidad; `textoOVacio`/CONCAT |
| **D — Compras** | OC, requisiciones, factura proveedor | Flujo borrador → confirmación; unicidad fiscal | UNIQUE 23505; moneda/cotización |
| **E — Caja** | Cuentas caja, cobranzas, numeración | Numeración secuencial bajo 2 usuarios concurrentes | Deadlock / UNIQUE numeración |
| **F — Ventas** | Pedidos, remitos, punto de venta si aplica | Pedido + numerocomprobante | UNIQUE nro. comprobante; joins |
| **G — Contable** | Plan de cuentas, asientos, mayor/saldos | Asiento balanceado; saldo mes | `anioMes`; rangos cuenta |
| **H — Verticales** | Gastronomía / estacionamiento / bingo solo si Crown los usa | Jornada + turno + 1 emisión | FK jornada; DATE/hora |
| **I — Integraciones** | Anita / ARCA / bridges solo si existen en Crown | Sync acotado; no volcar vacío | Auto-import; encoding |
| **J — Go-live** | Criterios §5 | Firma Crown + ops | Rollback plan (§6) |

### Cómo correr cada etapa (cuando toque)

1. Habilitar menú/permisos del módulo (migración con gate `EntornoEmpresaSupport` Crown).
2. Ampliar smoke HTTP **de esa etapa** (script lab o checklist manual).
3. UAT con 1–2 usuarios Crown (checklist corta por pantalla).
4. Si falla SQL → boy scout en helpers → re-smoke → no avanzar.
5. Solo entonces abrir la etapa siguiente.

Las pruebas automatizadas / smoke ampliado **no se ejecutan ahora**; se agregan al entrar en la etapa correspondiente.

---

## 5. Criterios go / no-go (Fase 7)

**Go (Crown productivo en PG)** si:

- [ ] Fase 6 completa (backup + restore drill).
- [ ] Etapas A–G verdes en el alcance contratado (H–I según módulos).
- [ ] Smoke HTTP de indexes del alcance sin redirect a login ni “No tienes permisos” por error SQL.
- [ ] Al menos un ciclo de escritura concurrente en numeraciones (caja y/o venta) sin corrupción.
- [ ] RPO/RTO escrito y drill ejecutado una vez.
- [ ] AGG sigue intacto en MySQL (sin cambios de `DB_CONNECTION` en prod AGG).

**No-go / pausa** si:

- SQL dialectal sigue apareciendo en cada etapa sin tendencia a bajar.
- Backup/restore no está demostrado.
- Crown exige Anita/ARCA y no hay ventana para etapa I.

**Completado sin cutover AGG:** válido. La abstracción dual-driver ya sirve; Crown usa PG, AGG MySQL.

---

## 6. Rollback y contención

| Escenario | Acción |
|-----------|--------|
| Fallo en etapa A–B (antes de datos de negocio) | Recrear BD vacía; no hay pérdida |
| Fallo mid-etapa con datos de prueba | Restore desde `pg_dump` del inicio de etapa |
| Fallo post go-live | PITR/WAL o restore dump; **no** “volver a MySQL” salvo instancia MySQL Crown paralela acordada |
| Bug dialectal en código | Fix en helpers; deploy a Crown **y** AGG (MySQL sigue recibiendo el mismo código) |

No planear “Crown MySQL de emergencia” salvo que ops lo pida explícitamente antes del go-live.

---

## 7. Qué no hacer

- Cambiar `DB_CONNECTION` de AGG a `pgsql`.
- Reescribir las ~1300 migraciones históricas “para Postgres”.
- Copiar datos AGG con pgloader “por las dudas” sin pedido.
- Implementar Fase 6 en el lab `.211` como si fuera Crown (el lab es de schema/smoke).
- Abrir gastronomía/estacionamiento/Anita antes de maestros + stock + compras si Crown los necesita después.
- Correr `php artisan test` / writes masivos contra BD productiva sin autorización (`produccion-bd-y-tests.mdc`).

---

## 8. Próximo paso concreto

1. **Ahora (hecho):** esta estrategia + Fase 6 agendada en `portabilidad-base-datos.md`.
2. **Cuando Crown confirme:** ticket ops Fase 6.1–6.8 + nombre de `EMPRESA` + RPO/RTO.
3. **Al tener servidor:** ejecutar Fase 6 → Etapa A → B… según alcance del contrato.
4. **Pruebas:** ampliar smoke/UAT **por etapa**, no anticipar un suite completo hoy.

---

## 9. Mensaje corto para Crown

> El ERP ya puede correr en PostgreSQL. Vamos a implementar Crown por etapas (plataforma → maestros → stock → compras → caja → ventas → contable → verticales/integraciones). Primero armamos el servidor Postgres con backup y restore; después habilitamos módulos de a uno y validamos. AGG sigue en MySQL. Si algo falla por dialecto, se corrige en el código común y se reintenta la etapa.
