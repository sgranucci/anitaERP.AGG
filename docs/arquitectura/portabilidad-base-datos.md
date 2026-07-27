# Portabilidad de base de datos (decisión aplazada)

**Estado:** aplazado — retomar cuando anitaERP esté estable en alcance (menos cambios día a día).  
**Fecha de registro:** 2026-07-27  
**Alcance:** motor operativo Laravel (anitaERP). No incluye Anita legacy ni otras BD externas (Wigos, SuiteCRM, etc.).

## 1. Decisión vigente

| Tema | Decisión |
|------|---------|
| Motor actual / Crown (corto plazo) | **MySQL 8** (o MariaDB si el hosting lo impone; **unificar un solo motor** en todos los entornos) |
| Migrar a PostgreSQL ahora | **No** |
| Motivo “WAL” como justificación de Postgres | **No válido** — MySQL/InnoDB tiene redo log + binary log (PITR equivalente; ver `deploy/backup/`) |
| Primer proyecto futuro | **Abstraer el acceso a BD** (dual-driver / portable), no el cutover a Postgres |
| Cutover o instancia Postgres | Solo después de abstracción + requisito concreto (hosting, feature, política) |
| Migraciones nuevas (desde ya) | **Portables** — regla `.cursor/rules/migraciones-portables-motor.mdc` |

**Unificar MySQL vs MariaDB:** evitar mezclar MySQL 8 y MariaDB entre local/staging/prod (collations, dumps, binlog). Elegir uno y documentarlo en ops.

**Adelanto ya activo (Fase 4 parcial):** toda migración **nueva** debe ser motor-agnóstica (sin charset/collation MySQL ni `information_schema`). No reescribir las históricas hasta la fase formal.

**Collation MySQL (desde 2026-07-27):** `DB_COLLATION=utf8mb4_spanish_ci` en `.env` / default de `config/database.php`. Las migraciones portables **no** fijan collation en el Blueprint: Laravel la toma de la conexión. Así las tablas nuevas quedan alineadas al español de las migraciones históricas.

### Collation español: MySQL vs PostgreSQL

| Motor | Equivalente práctico |
|-------|----------------------|
| MySQL / MariaDB | `utf8mb4_spanish_ci` (case-insensitive, reglas de ordenación española) |
| PostgreSQL | **No** existe el nombre `spanish_ci`. Usar collation **ICU** con locale español, p. ej. `es-ES-x-icu` (si el cluster la trae) o `CREATE COLLATION … (provider = icu, locale = 'es-ES')`. Case-insensitive tipo `_ci` suele requerir ICU con strength de comparación (p. ej. locale `es-ES-u-ks-level2`), no solo el nombre de idioma. |

En dual-driver futuro: MySQL sigue con `DB_COLLATION`; Postgres documenta/crea la collation ICU una vez por instancia (ops Fase 6), sin hardcodear `spanish_ci` en migraciones.

---

## 2. Ventajas de PostgreSQL frente a MySQL / MariaDB

Ventajas **reales** de Postgres (no confundir con marketing de “WAL”):

| Área | PostgreSQL | MySQL / MariaDB |
|------|------------|-----------------|
| SQL / reportes complejos | CTE, window functions, `DISTINCT ON`, `FILTER`, lateral joins muy maduros | Disponible en 8.x; a menudo más limitado en reportes pesados |
| Integridad | Más estricto por defecto (tipos, checks, constraints) | Más permisivo si `strict` está off; se puede endurecer |
| JSON | `JSONB` + índices GIN sólidos | JSON útil; indexación avanzada menos cómoda |
| Full-text search | Nativo (`tsvector`) | Existe; menos “todo en la BD” |
| Extensiones | PostGIS, **pgvector**, FDW, etc. | Plugins; ecosistema más acotado |
| Tipos | Arrays, ranges, enums nativos, UUID | Menos variedad; a menudo se emula en app |
| WAL / PITR / réplica | Excelente | Equivalente (redo + **binlog**) — **no es diferencial** |
| Laravel (tendencia 2025–26) | Momentum (Cloud, AI/pgvector) | Default histórico LAMP; plenamente soportado |
| Ops anitaERP | Curva distinta (autovacuum, WAL archive) | Know-how y runbooks ya existentes |

**Qué no justifica el cambio hoy**

- “Tiene WAL” (MySQL también cubre durabilidad + PITR).
- “Más rápido” en CRUD ERP típico (ganan índices y diseño de queries).
- Arrancar Crown “limpio”: empresa vacía en MySQL = schema limpio igual; la deuda es dialectal en código, no en datos AGG.

**Cuándo Postgres sí podría importar más adelante**

- Reportes analíticos muy complejos en SQL.
- Búsqueda vectorial / semántica en la misma BD (`pgvector`).
- GIS (PostGIS).
- Política de hosting/cliente que solo ofrezca PostgreSQL.

---

## 3. Por qué el beneficio de migrar *ahora* es bajo para este ERP

1. Carga típica ERP (comprobantes, stock, asientos, numeraciones) — MySQL la cubre bien.
2. El dolor está en reglas de negocio e integraciones (Anita, ARCA, multiempresa), no en el motor.
3. Inversión operativa ya hecha en MySQL/MariaDB (migraciones, backups, PITR).
4. Código acoplado: SQL crudo (`CAST ... AS UNSIGNED`, `HOUR()`, `information_schema`), collations `utf8mb4_*`, `MysqlContencionSupport`, etc.
5. Abstracción total al 100% no es realista; dual-driver sí lo es — y es el trabajo previo útil.

---

## 4. ¿Vale la pena abstraer el nivel de BD?

**Sí como inversión estratégica de largo plazo; no como urgencia.**

Beneficios **aunque producción siga en MySQL**:

1. Menos deuda dialectal esparcida.
2. Opción real de Postgres / otro motor sin rewrite.
3. Mejor diseño (Query Builder, scopes, reportes más limpios).
4. Base para CI multi-motor.
5. Reglas claras de equipo para SQL nuevo.

**No esperar**

- Abstracción total (“olvidar el motor”): irreal con locks, collations, +1000 migraciones y errores de deadlock específicos.
- Hacerlo en plena fase de features diarias: el costo de rehacer dialecto en cada PR es alto.
- Abstracción sin disciplina: en meses vuelve el SQL MySQL-only.

**Objetivo realista:** dual-driver (MySQL + PostgreSQL) con ~5–10% de dialecto **encapsulado**, no “BD invisible”.

---

## 5. Estrategia: abstraer la base de datos (primer tema)

Orden obligatorio: **primero portabilidad**, después (opcional) motor Postgres.

### 5.1 Principios

1. **Query Builder / Eloquent primero.** Raw SQL solo detrás de un helper de dialecto.
2. **Un solo lugar de dialecto** (p. ej. `App\Support\Database\SqlDialectSupport` + generalizar `MysqlContencionSupport` → `DbContencionSupport`).
3. **Prohibir SQL nuevo MySQL-only** en PRs una vez iniciada la fase (regla de equipo / grep en CI).
4. **No reescribir las ~1100 migraciones históricas** una por una para Postgres; usar baseline + migraciones nuevas neutrales.
5. **Prod sigue en MySQL** hasta criterio de go/no-go explícito.
6. Anita / Wigos / SuiteCRM **fuera de alcance** de esta abstracción (conexiones propias).

### 5.2 Lista de trabajo por fase (qué tocar, día a día)

Usar como backlog: un ítem o un archivo hotspot por día/PR. Marcar al cerrar.

#### Fase 0 — Arranque (gobierno, sin código de abstracción)

| # | Qué hacer | Qué se toca |
|---|-----------|-------------|
| 0.1 | Confirmar que el ERP está estable para invertir tiempo | — (decisión de equipo) |
| 0.2 | Acordar motor único en local/staging/prod (MySQL 8 **o** MariaDB) | Ops / `.env` de cada entorno; nota en este doc |
| 0.3 | Abrir épica/issue “Portabilidad BD” con enlace a este doc | Tracker |

#### Fase 1 — Inventario (solo lectura + plan)

| # | Qué hacer | Qué se toca |
|---|-----------|-------------|
| 1.1 | Listar raw SQL en `app/` | Entregar CSV/tabla en issue (no editar código aún) |
| 1.2 | Contar `CAST AS UNSIGNED`, `HOUR(`, `information_schema`, charset en migraciones | `app/`, `database/migrations/` |
| 1.3 | Inventario locks/numeraciones | `MysqlContencionSupport`, `CobranzaNumeracionTransaccion`, jornadas gastro/estacionamiento/bingo, CAEA |
| 1.4 | Matriz prioridad (alto/medio/bajo) | Issue + actualizar §5.2 Fase 3 de este doc si hace falta |

**Hotspots iniciales conocidos (priorizar en Fase 3):**

- `app/Support/Stock/MovimientoStockListadoUnificadoSupport.php`
- `app/Queries/Ventas/PedidoQuery.php`
- `app/Queries/Ventas/Gastronomia*.php` (varios reportes)
- `app/Support/Listado/CoincidenciaFlexibleTexto.php` + `*ListadoFiltros.php`
- `app/Support/Caja/CobranzaNumeracionTransaccion.php`
- `app/Support/Database/MysqlContencionSupport.php`
- Controllers con `CAST(... AS UNSIGNED)` (depósitos, PV, artículos, etc.)

#### Fase 2 — Capa de dialecto (fundación; 2–4 PRs)

| # | Qué hacer | Qué se toca | Estado |
|---|-----------|-------------|--------|
| 2.1 | Crear helper de expresiones SQL portables | `app/Support/Database/SqlDialectSupport.php` | **Hecho** |
| 2.2 | Generalizar reintentos deadlock | `MysqlContencionSupport` → `DbContencionSupport` | Pendiente |
| 2.3 | Regla Cursor uso obligatorio del helper | `.cursor/rules/sql-dialect-portable.mdc` | **Hecho** |
| 2.4 | Smoke / test unitario puro del helper | `tests/Unit/Support/Database/` sin RefreshDatabase | Pendiente |

APIs del helper (2.1): `castEntero`, `ordenCodigoAsc`, `hora`, `anioMes`, `lower` — ramas mysql/mariadb vs pgsql solo dentro de esa clase.

Pilotos Fase 3: `CamionRepository`, `DepmaeController`.

#### Fase 3 — Código incremental (el grueso; 1 archivo o módulo por día)

Regla boy scout: si tocás un módulo por otra feature, convertís su raw SQL al helper en el mismo PR.

| # | Bloque | Archivos / carpetas típicas |
|---|--------|-----------------------------|
| 3.1 | Contención + numeraciones | `DbContencionSupport`, `CobranzaNumeracion*`, `*NumeracionComprobanteSupport`, servicios de jornada/turno |
| 3.2 | Filtros listado compartidos | `CoincidenciaFlexibleTexto`, `app/Support/**/*ListadoFiltros.php` |
| 3.3 | Stock / movimientos | `MovimientoStockListadoUnificadoSupport`, `ExistenciasDepositoReporteService`, `ArticuloController` (casts) |
| 3.4 | Ventas pedidos / kilos | `PedidoQuery`, `KiloPedidoListadoFiltros`, reportes pedidos |
| 3.5 | Gastronomía reportes | `app/Queries/Ventas/Gastronomia*.php` |
| 3.6 | Estacionamiento / caja reportes | `app/Queries/Caja/*`, supports estacionamiento |
| 3.7 | Contable / saldos | `CuentacontableSaldo*`, `SumasSaldosProcesador`, conciliación IVA |
| 3.8 | Compras / CC | repos cuentacorriente, unicidad comprobantes |
| 3.9 | Resto controllers con `UNSIGNED` / raw | grep y ir cerrando lista Fase 1 |

Cada día: **un** hotspot → helper → `php -l` → smoke manual del pantallazo → listo.

#### Fase 4 — Migraciones (continua; ya empezó)

| # | Qué hacer | Qué se toca | Estado |
|---|-----------|-------------|--------|
| 4.1 | Regla migraciones nuevas portables | `.cursor/rules/migraciones-portables-motor.mdc` | **Hecho** |
| 4.2 | Cumplir la regla en **toda migración nueva** | `database/migrations/20xx_*.php` nuevas | **En curso** |
| 4.3 | Al tocar una migración vieja por otro motivo: quitar charset/collation / `information_schema` si es barato | Solo el archivo que ya se edita | Oportunista |
| 4.4 | Documento de baseline PG (pgloader / schema dump) | `docs/arquitectura/` o `deploy/` | Cuando exista CI PG |

**No** reescribir las ~1100 migraciones históricas en bloque.

#### Fase 5 — CI dual-driver

| # | Qué hacer | Qué se toca |
|---|-----------|-------------|
| 5.1 | Servicio Postgres en CI + `DB_CONNECTION=pgsql` | pipeline CI (GitHub Actions / local compose) |
| 5.2 | Job `migrate` en PG vacío (o baseline) | mismo pipeline |
| 5.3 | Smoke mínimo automatizado o checklist manual documentada | script o job |

#### Fase 6 — Ops Postgres (solo con go-ahead)

| # | Qué hacer | Qué se toca |
|---|-----------|-------------|
| 6.1 | Install `pdo_pgsql`, `.env` ejemplo | servidor + `.env.example` |
| 6.2 | Backup `pg_dump` + WAL archive | `deploy/backup/*` paralelo (no borrar MySQL) |
| 6.3 | Restore drill documentado | `docs/` / `RESTORE-pgsql.md` |

#### Fase 7 — Go / no-go

| # | Qué hacer | Qué se toca |
|---|-----------|-------------|
| 7.1 | Criterios: Fase 5 verde + requisito real | decisión |
| 7.2a | Si sí: cutover o instancia Crown PG | deploy + config |
| 7.2b | Si no: cerrar épica — abstracción hecha, prod MySQL | este doc → estado “completado sin cutover” |

### 5.3 Checklist técnico (referencia)

Ver también conversación de evaluación 2026-07-27. Resumen:

- [ ] Helpers dialecto + contención multi-motor  
- [ ] Eliminar / encapsular `UNSIGNED`, funciones fecha MySQL, `information_schema`  
- [ ] Collations / ordenamiento español  
- [ ] Tipos: unsigned, enum, boolean, decimales moneda  
- [ ] Locks y numeraciones bajo carga en ambos motores (cuando haya PG en CI)  
- [ ] Backup/PITR del motor elegido en ops  
- [ ] Integraciones externas no acopladas al cambio  

### 5.4 Estimación relativa

| Fase | Esfuerzo | Nota |
|------|----------|------|
| 1 Inventario | Bajo–medio | Días |
| 2 Capa dialecto | Medio | Fundación |
| 3 Código incremental | **Alto** | Semanas–meses, en paralelo a mantenimiento |
| 4 Migraciones nuevas | Bajo (continuo) | Disciplina |
| 5 CI dual | Medio | |
| 6–7 Ops + cutover | Alto | Solo con go-ahead |

### 5.5 Anti-patrones

- Cambiar `DB_CONNECTION=pgsql` en Crown sin Fases 1–5.
- Mantener MySQL en AGG y Postgres en Crown con el **mismo** código **sin** helpers (doble mantenimiento caótico).
- Reescribir historial completo de migraciones “para que sean PG”.
- Confundir abstracción de BD del ERP con reemplazo de Anita.

---

## 6. Respuesta tipo a “lo queremos por WAL”

Usar cuando infra/cliente insista en Postgres solo por WAL:

> WAL es el registro anticipado de durabilidad y base del PITR. MySQL/InnoDB cubre lo mismo con redo log + binary log. anitaERP ya tiene procedimientos de dump, retención de binlog y restauración a punto en el tiempo (`deploy/backup/`). Un cambio de motor no aporta un escenario de recuperación nuevo; sí implica portar SQL/migraciones y rehacer ops. Pedimos concretar RPO/RTO (ej. restaurar a HH:MM del día D) y lo medimos contra el PITR MySQL actual. Si la restricción es política de plataforma (solo Postgres), se evalúa como proyecto de portabilidad con el costo de las fases de este documento.

---

## 7. Referencias en el repo

- `config/database.php` — conexiones `mysql` y `pgsql`
- `app/Support/Database/MysqlContencionSupport.php` — reintentos deadlock MySQL (a generalizar)
- `deploy/backup/RESTORE.md` — PITR / binlog MySQL–MariaDB
- `deploy/backup/backup-db.sh` — dump + snapshot binlog
- `.cursor/rules/migraciones-portables-motor.mdc` — migraciones nuevas neutrales al motor (**activo**)
- `app/Support/Database/SqlDialectSupport.php` — expresiones SQL portables (**activo**)
- `.cursor/rules/sql-dialect-portable.mdc` — SQL crudo nuevo vía helper (**activo**)
- Reglas CRUD/listados: SQL en filtros y reportes (candidatos a Fase 3)

---

## 8. Qué se puede hacer ya (sin esperar “ERP terminado”)

1. **Migraciones nuevas portables** (Fase 4.1–4.2) — obligatorio desde ahora vía la regla Cursor.  
2. Boy scout: al tocar archivos con `CAST AS UNSIGNED`, migrar a `SqlDialectSupport` (ej. ya hecho en `CamionRepository`, `DepmaeController`).  
3. Siguiente formal: Fase 2.2 (`DbContencionSupport`) o más hotspots de orden por código (PV gastronomía, `ArticuloController`, etc.).

## 9. Próximo paso cuando se reactive el proyecto formal

1. Confirmar Fase 0 si aún aplica (estabilidad + motor único).  
2. Fase 1 inventario completo (matriz hotspots) si no está hecha.  
3. Fase 2.2 `DbContencionSupport` + seguir boy scout Fase 3 (casts / reportes).  
4. No spike de Postgres en deploy real sin Fase 5 en verde.
