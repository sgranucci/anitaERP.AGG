# Artículo: cuenta contable por Uso × Tipo y CC por defecto del Uso

Diseño de pantallas y endpoints. Estado: **borrador para validar**. No hay código escrito.
Origen: pedido de Contabilidad, Excel «Propuesta cuentas contables por uso de articulo» y «Cambio de uso de articulos» (26/nov/2026).

## 0. Punto de partida (verificado en producción, solo lectura)

- El «Cambio de uso» ya está aplicado en el ERP: 15.876 de 15.883 artículos tienen el Uso nuevo y 15.848 el Tipo nuevo. Los usos MAQUINAS, LEGALES, ADMINISTRACION, TESORERIA, TESORERIA CORPORATIVA, ABONOS MANTENIMIENTO y el tipo REPUESTO ya existen. **No se codifica nada para eso.**
- Casi todos los artículos ya tienen cuenta de COMPRAS y GASTOS en las 3 empresas (solo 3 no tienen ninguna).
- En AGG el artículo **no tiene centro de costo** (`centrocostocompra_id` es de Frasle y no existe acá). Hoy el CC se elige en la línea de requisición / OC (`centrocostodestino_id`).
- La matriz propuesta difiere de la cuenta actual en 2.808 artículos activos y no tiene regla para 831.

## 1. Decisiones abiertas y supuestos de este diseño

Marcadas **[D1]…[D6]**. El diseño asume el default indicado y se cambia sin rehacer todo.

| # | Decisión | Default asumido |
|---|---|---|
| D1 | ¿En qué tipos de imputación se escribe la cuenta de la matriz? | **COMPRAS y GASTOS** con la misma cuenta. VENTAS e IMPUESTOS INTERNOS no se tocan. |
| D2 | ¿Se pisan las cuentas que ya existen? | **No.** El alta y la edición proponen; el masivo es una acción aparte, con dry-run y sí explícito. |
| D3 | CC por defecto: ¿en el Uso o por artículo? | **En el Uso** (`usoarticulo.centrocosto_id`). No se copia al artículo. **Cerrada 26/nov:** si el Uso tiene CC, la línea de requisición / OC lo toma y **no se puede cambiar** (validación nueva, además de la de la cuenta). Uso sin CC = comportamiento actual. No hay interruptor: **cargar el CC en el Uso es lo que lo vuelve obligatorio.** |
| D4 | ¿Se propaga a Anita? | **Sí, solo empresa 1**, con escritura acotada por SKU. |
| D5 | Combinaciones sin regla | Quedan **sin propuesta** (el operador elige). No se inventa una cuenta. |
| D6 | «Para inactivar» (4.277) | **Fuera de este diseño.** Hay recetas y movimientos de por medio. |

## 2. Modelo de datos

Migraciones sobre tablas compartidas (corren en todos los clientes; sin gate Crown). Sin `softDeletes`. Portables (`migraciones-portables-motor`).

### 2.1 `articulo_uso_tipo_cuenta` (nueva)

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint PK | |
| `usoarticulo_id` | FK `usoarticulo` | restrict |
| `tipoarticulo_id` | FK `tipoarticulo` | restrict |
| `cuentacontable_id` | FK `cuentacontable` | cuenta **de la empresa origen** (Biyemas, id 1). Las otras empresas se resuelven por código. |
| `observacion` | string(255) null | por qué se eligió |
| `validada` | bool default false | la marca Contabilidad cuando confirma la fila (las «amarillas» del Excel) |
| `validada_usuario_id` / `validada_at` | null | |
| `creousuario_id`, timestamps | | |

Índice único `(usoarticulo_id, tipoarticulo_id)`. Modelo `App\Models\Stock\ArticuloUsoTipoCuenta` (`Auditable`, import de `Seguridad\Usuario`).

### 2.2 `usoarticulo.centrocosto_id` (columna nueva)

`unsignedBigInteger null`, FK a `centrocosto` (restrict). Se edita en el ABM de Uso (`Stock\UsoarticuloController`). `Usoarticulo` suma `belongsTo(Centrocosto::class)`.

### 2.3 Carga inicial

Una migración **no** carga datos (producción). La carga de las 70 filas y los CC de los 20 usos va por comando con `--dry-run` y OK explícito (ver 6), leyendo el Excel entregado.

## 3. Pantallas

### 3.1 ABM «Cuenta por Uso × Tipo» (nuevo)

- Menú canónico: **Configuración → Configuración por módulo → Stock → Cuenta por Uso × Tipo**. Atajo en Stock solo para `administrador` (`configuracion-menu-por-modulo`).
- URL y código en módulo Stock: `stock/articulo-uso-tipo-cuenta`, `Stock\ArticuloUsoTipoCuentaController`, vistas `stock.articulo_uso_tipo_cuenta.*`, JS en `public/assets/pages/scripts/stock/articulo_uso_tipo_cuenta/`.
- Permisos: `listar|crear|editar|actualizar|borrar-articulo-uso-tipo-cuenta`. Migración de menú/permiso en el mismo cambio, con `SuitecrmPermiso::flushCachePermisos()`.
- Index (≈70 filas): `card card-info`, `id="tabla-paginada"`, toolbar de filtros, export Pdf/Excel/Csv. Es una tabla chica de configuración, por eso va con el stack CRUD y no con el workbench.

```
┌─ Cuenta por Uso × Tipo ───────────────────────────── [Filtros] [+ Nuevo] [Pdf Excel Csv] ┐
│ Uso ▾ (consulta)  Tipo ▾ (consulta)  Estado: (Todas|OK|Sin validar|CC no habilitado)      │
├───────────────┬──────────────┬──────────────────────────┬───────────────┬───────┬────────┤
│ Uso           │ Tipo         │ Cuenta                   │ CC del Uso    │ Valid.│ Acciones│
├───────────────┼──────────────┼──────────────────────────┼───────────────┼───────┼────────┤
│ LABORATORIO   │ REPUESTO     │ 521240-002 REPUESTOS MAQ │ 93 Tecnica    │ ✔ OK  │ ✎  ✖   │
│ SALA BINGO    │ CONTABLE     │ 521070-005 …             │ 88 Bingo      │ ⚠ CC  │ ✎  ✖   │
│ MAQUINAS      │ BIEN DE USO  │ 123010-030 …             │ 89 Maquinas   │ ○ s/v │ ✎  ✖   │
└───────────────┴──────────────┴──────────────────────────┴───────────────┴───────┴────────┘
```

Columna **Valid.** (chip):
- `✔ OK`: la cuenta maneja CC y el CC del Uso está habilitado, o la cuenta no maneja CC.
- `⚠ CC no habilitado`: la cuenta maneja CC con lista y el CC del Uso no está en la lista (`CuentaCentrocostoAsignadosSupport::restriccion`).
- `○ Sin validar`: `validada = false`.

Crear / editar (`card card-primary`, `form-horizontal`, `data-enter-campos="1"`):
- **Uso**: componente de consulta (código + descripción + F1 + lupa). Ya existe `stock/partials/campo_consulta_usoarticulo` y `modalconsultausoarticulo`.
- **Tipo**: lo mismo; falta el partial de consulta de tipo (ver 7).
- **Cuenta contable**: `contable.partials.campo_consulta_cuentacontable` + `modalconsultacuentacontable`, con la empresa origen fija. Es la cuenta de la empresa 1; las otras salen por código.
- **CC del Uso**: solo lectura, mostrado desde el Uso, con enlace al ABM de Uso.
- Aviso en línea si la cuenta no tiene equivalente en las empresas 2 y 3 (`CuentacontableEmpresaResolverSupport::resolverIdDesdeId`).
- **Validada**: checkbox, solo con permiso `actualizar-…`.
- Guardar redirige al index (`crud-actualizar-redirige-index`).

Vista de consulta desde el modal / desde el artículo: `origen=modal_consulta&vista=consulta` con middleware `modo.consulta`.

### 3.2 ABM de Uso: campo CC por defecto

En `stock/usoarticulo/form.blade.php` se agrega **«Centro de costo por defecto»** con `contable.partials.campo_consulta_centrocosto` (nunca un select). Va después del nombre, antes de la aprobación. Vacío = sin CC por defecto. El index de usos suma la columna CC.

`ValidacionUsoarticulo`: `centrocosto_id` nullable, `exists:centrocosto,id`.

### 3.3 Artículo: alta y edición (pestaña «Contable», `form4`)

Hoy la grilla `#tbody-cuentacontable-table` tiene filas empresa × tipo de imputación × cuenta, con botones de replicar y quitar. No se rediseña; se le agrega un **panel de propuesta** arriba de la grilla:

```
┌ Propuesta contable por Uso + Tipo ─────────────────────────────────────────────┐
│ LABORATORIO + REPUESTO  →  521240-002 REPUESTOS MAQUINAS        [ Aplicar ]    │
│ CC por defecto del Uso: 93 - Tecnica                              [ Ignorar ]  │
│ ⓘ La cuenta actual (123010-030) es distinta. No se cambia sin tu confirmación. │
└─────────────────────────────────────────────────────────────────────────────────┘
```

Estados del panel (los define el endpoint, ver 4.1):

| Estado | Qué se muestra | Acción |
|---|---|---|
| `propuesta` | Cuenta y CC propuestos | **Aplicar** llena la grilla (COMPRAS y GASTOS en cada empresa asignada, D1) |
| `igual` | «La cuenta ya coincide con la regla» | Nada |
| `difiere` | Propuesta + cuenta actual + aviso | **Aplicar** pisa solo si el operador lo pide |
| `sin_regla` | «No hay regla para esta combinación» | El operador elige la cuenta a mano |
| `sin_validar` | Chip «Regla sin validar por Contabilidad» | Se puede aplicar igual |
| `cc_no_habilitado` | Aviso: el CC del Uso no está habilitado para esa cuenta | Se puede aplicar; el CC se corrige en la línea de la OC |

Comportamiento:
- **Alta**: al elegir Uso y Tipo (los dos cargados) se consulta el endpoint. Con grilla vacía, el panel ofrece **Aplicar**. Con la pestaña Contable abierta no se llena sola: el operador aplica con un clic. Esto es deliberado, porque «Contabilidad solo confirma».
- **Edición**: al cambiar Uso o Tipo se vuelve a consultar. Si ya hay cuentas, el estado es `igual` o `difiere`. **Nunca se pisa en silencio.**
- La grilla conserva `cuentacontable_id_previa` / `codigo_previo` (ya existen en el template) para mostrar «antes → después».
- Si el circuito de aprobación está activo (`ARTICULO_APROBACION_ALTA=true`) y la regla fue aplicada, el alta nace PENDIENTE y Contabilidad confirma o corrige en la bandeja de aprobación que ya existe. No hay circuito nuevo.
- Solo lectura (`modo.consulta` sin permiso de actualizar): el panel se muestra sin botones.

### 3.4 Revisión masiva para Contabilidad (fase 2)

Pantalla de solo consulta con acciones controladas: **Reportes → Stock → Artículos vs regla Uso × Tipo**.

- Stack de reporte de consulta (`reportes-consulta-paginado-export`): filtros por Uso, Tipo, estado de la comparación, empresa; Pdf / Excel / Csv.
- Columnas: SKU, descripción, Uso, Tipo, **cuenta actual**, **cuenta de la regla**, resultado (`igual` / `difiere` / `sin_regla` / `sin_cuenta`), CC del Uso.
- Resumen del filtro completo arriba: cuántos de cada resultado.
- Acción **«Aplicar regla a la selección»**, con modal de confirmación que muestra «se pisarán N filas». Es el mismo servicio que el comando masivo (ver 6).
- Es la entrega que Contabilidad necesita para decidir los 2.808 y los 831. Se puede entregar **antes** que el resto como Excel.

## 4. Endpoints

Todos en Stock (`modulo-menu-consistencia`). Sin rutas legacy.

### 4.1 Propuesta contable (usado por el formulario del artículo)

```
POST stock/articulo/propuesta-contable     name: articulo_propuesta_contable
```

Permiso: cualquiera de `crear-articulos`, `editar-articulos`, `actualizar-articulos` o consulta (`ArticuloConsultaDesdeModal::puedeConsultar()`).

Request (`ValidacionArticuloPropuestaContable`):

```json
{
  "usoarticulo_id": 3,
  "tipoarticulo_id": 18,
  "articulo_id": 12345,          // opcional (edición): compara con lo que ya tiene
  "empresa_ids": [1, 2, 3]       // opcional; default = empresas asignadas al usuario
}
```

Response 200:

```json
{
  "estado": "propuesta|igual|difiere|sin_regla",
  "regla": {
    "id": 17,
    "validada": true,
    "cuenta": { "cuentacontable_id": 8123, "codigo": "521240-002", "nombre": "REPUESTOS MAQUINAS" },
    "cuenta_por_empresa": [
      { "empresa_id": 1, "cuentacontable_id": 8123, "codigo": "521240-002", "nombre": "REPUESTOS MAQUINAS" },
      { "empresa_id": 2, "cuentacontable_id": 9123, "codigo": "521240-002", "nombre": "REPUESTOS MAQUINAS" },
      { "empresa_id": 3, "cuentacontable_id": null, "codigo": "521240-002", "error": "Sin cuenta en el plan de la empresa" }
    ],
    "tipos_imputacion": ["COMPRAS", "GASTOS"]
  },
  "centrocosto": {
    "centrocosto_id": 9, "codigo": "93", "nombre": "Tecnica",
    "habilitado": true,
    "motivo": null
  },
  "actual": [
    { "empresa_id": 1, "tipoimputacion": "COMPRAS", "codigo": "123010-030", "nombre": "COMPRAS BS. DE USO -CTA.PUENTE", "coincide": false }
  ],
  "avisos": ["CC 88 no habilitado para 521070-005"]
}
```

Reglas:
- `sin_regla` devuelve `regla: null`; no inventa cuenta (D5).
- `habilitado` del CC: cuenta sin `manejaccosto` → `true`; con lista → pertenece a la lista; sin lista → `true` («cualquiera»). Usa `CuentaCentrocostoAsignadosSupport`.
- Es **solo lectura**. No escribe nada.
- Códigos 422 con mensaje por campo; 403 sin permiso.

Implementación: `App\Support\Stock\ArticuloCuentaPropuestaSupport::resolver(int $usoId, int $tipoId, ?int $articuloId, array $empresaIds): array`. El controller solo valida y delega. Reutiliza `CuentacontableEmpresaResolverSupport` para las empresas 2 y 3 y `ArticuloCuentaCompraGastoSupport` para la cuenta actual.

### 4.2 CRUD de la matriz

Mismo patrón que `usoarticulo`:

```
GET    stock/articulo-uso-tipo-cuenta                     articulo_uso_tipo_cuenta
GET    stock/articulo-uso-tipo-cuenta/crear               crear_articulo_uso_tipo_cuenta
POST   stock/articulo-uso-tipo-cuenta                     guardar_articulo_uso_tipo_cuenta
GET    stock/articulo-uso-tipo-cuenta/{id}/editar         editar_articulo_uso_tipo_cuenta   (modo.consulta)
PUT    stock/articulo-uso-tipo-cuenta/{id}                actualizar_articulo_uso_tipo_cuenta (modo.consulta)
DELETE stock/articulo-uso-tipo-cuenta/{id}                eliminar_articulo_uso_tipo_cuenta
GET    stock/lista-articulo-uso-tipo-cuenta/{formato?}/{busqueda?}   lista_articulo_uso_tipo_cuenta
```

Validación al guardar (`ValidacionArticuloUsoTipoCuenta`): par Uso+Tipo único; cuenta de la empresa origen; la cuenta no está `inhabilitada`. El chip de «CC no habilitado» no bloquea (hay filas que Contabilidad puede querer dejar así); avisa.

Borrar usa `$modelo->delete()` (audita). No `Builder::delete()`.

### 4.3 CC por defecto en requisiciones y OC

El CC sale del Uso del artículo. Se agrega a la respuesta que ya consumen los wizards:

- `ArticuloController@consultaArticulo` (modal compartido): sumar `centrocosto_uso_id` por artículo (join `usoarticulo`).
- Requisición, OC y wizards (`wizard-desde-requisicion.js`, `wizard-multiples-oc.js` y el alta de línea): usar `centrocosto_uso_id` como valor inicial de `centrocostodestino_id` **si hay uno**, antes de caer en `META.centrocosto_default_id`.
- Se sigue validando con `ArticuloCentrocostoCuentaSupport::validarPayload`: si el CC del Uso no está habilitado para la cuenta de COMPRAS, el operador ve el error de siempre y lo corrige en la línea.
- **Decisión cerrada (26/nov):** Uso con CC cargado → la línea se precarga con ese CC y queda **bloqueada** (campo de solo lectura). Uso sin CC → la línea se comporta exactamente como hoy. Es una validación **nueva**, que se suma a la de la cuenta de COMPRAS (`ArticuloCentrocostoCuentaSupport`), no la reemplaza.
- **Servidor**: la regla se aplica en `ArticuloCentrocostoCuentaSupport::validarPayload` (o una clase hermana que éste invoque), no solo en el JS. Rechaza si el CC de la línea ≠ CC del Uso del artículo. La línea sin CC propio se resuelve con el CC del Uso (no con el de la cabecera).
- **Dónde se aplica**: guardar / actualizar requisición y guardar / actualizar OC. Los wizards (OC desde requisición, múltiples OC) heredan el CC de la línea de la requisición y pasan por la misma validación.
- **Documentos existentes (gradual)**: `validarPayload` también corre en confirmar, enviar al árbol y retomar sobre requisiciones ya guardadas. Para no romperlas, una línea ya guardada con el **mismo artículo y el mismo CC** se acepta aunque difiera del CC del Uso. La regla se exige a líneas nuevas o cuyo artículo o CC cambió.
- **Salida del bloqueo**: sin override. Si una línea no puede usar el CC del Uso, se corrige quitando el CC de ese Uso, cambiando el Uso del artículo o habilitando el CC en la cuenta. No hay botón para saltear la regla.
- **Cuenta que no admite el CC del Uso**: la validación de la cuenta sigue vigente, y como la línea ya no se puede cambiar, ese artículo queda **imposible de pedir**. Por eso la pantalla 3.1 pasa a **bloquear** el guardado de una fila de matriz con CC no habilitado (no solo avisar), y el Uso no puede quedar con un CC que deje sin poder pedir artículos sin que se vea antes (ver reporte de la fase 1).
- Datos: en 2026 el 82% de las 64.384 líneas de OC ya usa el CC del Uso, pero en los usos compartidos (mantenimiento, limpieza, descartables, librería, RRHH, administración, legales) solo entre el 30% y el 57%. El CC de la línea es hoy el destino del consumo. **Cargar el CC en esos usos cambia ese criterio**; es decisión de Contabilidad uso por uso.

### 4.4 Alta/edición del artículo (sin cambio de contrato)

El `POST` de guardar y el `PUT` de actualizar siguen recibiendo `empresa_ids[]`, `tipoimputaciones[]` y `cuentacontable_ids[]`. El panel de propuesta solo llena esos campos. **No se agrega lógica de negocio en el submit.**

## 5. Anita (empresa 1)

- **Alta y edición**: ya funciona. El controller arma `stkm_cta_contablec` (compras) y `stkm_cta_contable` (venta) desde la grilla de la empresa 1 (`ArticuloController` / `Articulo::actualizarAnita`). El panel no cambia eso.
- **Masivo**: no se reusa `actualizarAnita` (reescribe todo el `stkmae`). Se crea `ArticuloCuentaAnitaSupport` con escritura **acotada**:
  - `stkmae.stkm_cta_contablec` (cuenta de compras), `UPDATE … WHERE stkm_articulo = '<sku>'`, una fila por llamada.
  - La cuenta de gastos vive en `stcustom` (`cta_gasto`); se actualiza con el mismo criterio y WHERE por SKU.
  - Cumple `anita-bridge-escritura-segura`: `apiCallEscritura`, WHERE de clave, `COUNT` previo, tope de filas y OK explícito.
- Verificar con el equipo de Anita la letra `R` de REPUESTO en `stkm_tipo_articulo` antes de empujar cambios de Tipo.

## 6. Comando masivo (fase 3)

```
php artisan stock:aplicar-cuentas-uso-tipo
    {--dry-run}                 # default; no persiste
    {--ejecutar}                # requiere haber mostrado el dry-run
    {--uso=} {--tipo=} {--sku=}
    {--modo=sin-cuenta|difiere|todos}   # qué se toca (D2)
    {--empresas=1,2,3}
    {--anita}                   # además empuja a Anita (D4)
    {--limite=}                 # tope de artículos
    {--exportar=ruta.xlsx}      # snapshot antes/después
```

Reglas de ejecución (`comandos-actualizacion-dry-run-obligatorio`, `base-datos-no-escribir-sin-preguntar`):
1. Sin flags corre en dry-run y muestra: artículos que se crean, se pisan (con cuenta anterior → nueva) y no se tocan, por Uso y por Tipo.
2. `--ejecutar` solo después de que el operador vea ese resultado y diga que sí, con el **mismo** alcance.
3. Antes de pisar se exporta a Excel el estado anterior (`audit.console=false`: lo que se pisa desde consola no queda en `audits`).
4. Se actualiza por modelo (`Articulo_Cuentacontable::update`) y no por `DB::table`, para respetar el evento `updated` de auditoría.
5. Después se verifica contando lo persistido y se informa la diferencia contra el dry-run.

La lógica vive en `App\Services\Stock\ArticuloCuentaUsoTipoMasivoService`. El comando y la acción de la pantalla 3.4 la comparten.

El mismo servicio, con otro comando o flag, carga la matriz y los CC de los usos desde el Excel (`--cargar-matriz=ruta.xlsx --dry-run`).

## 7. Piezas que faltan y piezas que se reutilizan

| Pieza | Estado |
|---|---|
| Consulta de **Uso** (partial + modal + JS) | Existe: `campo_consulta_usoarticulo`, `modalconsultausoarticulo`, `stock/usoarticulo/consulta.js` |
| Consulta de **Centro de costo** | Existe: `campo_consulta_centrocosto`, `modalconsultacentrocosto` |
| Consulta de **Cuenta contable** | Existe: `campo_consulta_cuentacontable`, `modalconsultacuentacontable` |
| Consulta de **Tipo de artículo** | **Falta** (hoy es un select). Crear `campo_consulta_tipoarticulo` + modal + endpoint, o dejarlo como enum solo si el equipo acepta que es lista cerrada |
| Resolver cuenta por código en otras empresas | Existe: `CuentacontableEmpresaResolverSupport`, `ArticuloCuentacontableEmpresasSupport` |
| Validación cuenta ↔ CC | Existe: `CuentaCentrocostoAsignadosSupport`, `ArticuloCentrocostoCuentaSupport` |
| Circuito de aprobación del alta | Existe: `ArticuloArbolIntegracionService`, `Usoarticulo.aprobacion_modo` |
| Replicar cuenta a otras empresas desde la grilla | Existe: `replicarCuentaContableArticulo` |

Nota sobre el Tipo: los selects de Uso y Tipo del formulario del artículo son hoy `<select>` alimentados por tabla. No se tocan en este cambio. El pedido es la propuesta de cuenta. Migrar esos dos selects al componente de consulta es una mejora aparte y se puede hacer después.

## 8. Archivos a crear / tocar

**Crear**

| Capa | Archivo |
|---|---|
| Migración | `database/migrations/2026_11_27_…_crear_articulo_uso_tipo_cuenta.php` |
| Migración | `database/migrations/2026_11_27_…_agregar_centrocosto_a_usoarticulo.php` |
| Migración | `database/migrations/2026_11_27_…_menu_permiso_articulo_uso_tipo_cuenta.php` |
| Modelo | `app/Models/Stock/ArticuloUsoTipoCuenta.php` |
| Repository | `app/Repositories/Stock/ArticuloUsoTipoCuentaRepository(Interface).php` + binding en `AppServiceProvider` |
| Controller | `app/Http/Controllers/Stock/ArticuloUsoTipoCuentaController.php` |
| Requests | `ValidacionArticuloUsoTipoCuenta`, `ValidacionArticuloPropuestaContable` |
| Support | `app/Support/Stock/ArticuloCuentaPropuestaSupport.php`, `ArticuloUsoTipoCuentaListadoFiltros.php`, `ArticuloCuentaAnitaSupport.php` |
| Service | `app/Services/Stock/ArticuloCuentaUsoTipoMasivoService.php` |
| Comando | `app/Console/Commands/Stock/AplicarCuentasUsoTipoCommand.php` |
| Export | `app/Exports/Stock/ArticuloUsoTipoCuentaListadoExport.php` + vistas Excel / PDF |
| Vistas | `resources/views/stock/articulo_uso_tipo_cuenta/{index,crear,editar,form,listado}.blade.php` |
| Partial | `resources/views/stock/articulo/partials/panel_propuesta_contable.blade.php` |
| JS | `…/scripts/stock/articulo_uso_tipo_cuenta/{index,form}.js`, `…/stock/articulo/propuesta-contable.js` |

**Modificar**

- `routes/web.php` (rutas de 4.1 y 4.2).
- `app/Models/Stock/Usoarticulo.php` (`centrocosto_id`, relación).
- `UsoarticuloController` + `ValidacionUsoarticulo` + `stock/usoarticulo/{form,index}.blade.php`.
- `resources/views/stock/articulo/form4.blade.php` (incluir el panel).
- `public/assets/pages/scripts/stock/articulo/crear.js` (disparo de la consulta al cambiar Uso o Tipo; **Aplicar** llena la grilla con el mismo mecanismo que `replicaCuentaContable`).
- `ArticuloController@consultaArticulo` (devolver `centrocosto_uso_id`).
- JS de requisición / OC y wizards (valor inicial del CC).

## 9. Fases

La pantalla 3.4 lee la matriz desde la tabla `articulo_uso_tipo_cuenta`, que recién existe en la fase 2. Por eso la fase 1 es un Excel generado por comando de solo lectura, no una pantalla.

| Fase | Contenido | Escribe en producción |
|---|---|---|
| 1 | **Excel para Contabilidad** (comando de solo lectura que lee el Excel de la propuesta y compara contra el ERP): artículos que difieren, sin regla, bloqueos por CC y adherencia de OC por Uso | No |
| 2 | **Configuración**: migraciones, ABM de la matriz, CC en el ABM de Uso, menú/permisos, carga inicial por comando con dry-run | Solo tablas de configuración, con OK |
| 3 | **Panel de propuesta en el artículo** + endpoint 4.1 + reporte 3.4 en pantalla | No (solo propone) |
| 4 | **CC del Uso en requisición y OC**: precarga, línea bloqueada y validación de servidor | No hasta cargar CC en un Uso |
| 5 | **Masivo + Anita** | Sí, con dry-run y OK explícito |

### Fase 1: Excel para Contabilidad (sin código en la aplicación)

Comando `stock:reporte-cuenta-uso-tipo --excel=ruta.xlsx --propuesta=ruta.xlsx`, solo lectura. Hojas:
1. **Resumen**: activos con regla, iguales, que difieren, sin regla.
2. **Difieren** (2.808): SKU, descripción, Uso, Tipo, cuenta actual COMPRAS y GASTOS, cuenta de la regla, estado patrimonial / resultado.
3. **Sin regla** (831): SKU, Uso, Tipo.
4. **Matriz con problemas**: filas de la matriz con CC no habilitado, con menos de 5 artículos, o con menos del 60% de coincidencia.
5. **CC por Uso**: para cada Uso, CC propuesto, % de las líneas de OC 2026 que ya lo usan y los CC más usados en el resto. Es lo que Contabilidad necesita para decidir **en qué usos cargar CC**.
6. **Bloqueados por cuenta**: artículos activos cuya cuenta de COMPRAS no admite el CC de su Uso (347 hoy), con la cuenta y el Uso.

**Salida de la fase 1:** decisiones de Contabilidad sobre D1, D2, D5, D6 y la lista de usos que van a tener CC.

### Fase 2: Configuración

- Migraciones 2.1 y 2.2 y menú/permisos. Todas sobre tablas de configuración; no tocan artículos.
- ABM de la matriz (3.1) y campo CC en el ABM de Uso (3.2).
- Comando de carga inicial, con `--dry-run` y OK explícito. Carga la matriz validada y los CC de **los usos que Contabilidad eligió**, no los 19 del Excel por defecto.
- Con la matriz cargada y los usos sin CC, **nada cambia** en artículos, requisiciones ni OC.

### Fase 3: Panel y endpoint

- Endpoint 4.1, panel 3.3 en la pestaña Contable, reporte 3.4 en pantalla.
- Solo propone. No escribe nada que el operador no confirme con el formulario.

### Fase 4: CC del Uso en requisición y OC

- Precarga la línea, la bloquea y valida en servidor (ver 4.3), solo para los Usos que tienen CC cargado.
- Se despliega **apagada**: sin CC en ningún Uso, el comportamiento es el actual. Se enciende cargando el CC Uso por Uso.
- Requiere antes la verificación de los tres puntos de la sección 12 (árbol de aprobación, presupuesto, OC con varios CC).

### Fase 5: Masivo y Anita

- Comando 6 y acción masiva de la pantalla 3.4, con empuje a Anita (empresa 1).
- Se ejecuta solo después de resolver D1 y D2 y de revisar el riesgo de corte contable (sección 12).

## 10. Verificación al cerrar cada fase

- `php -l` de cada archivo tocado y chequeo de autoload / contenedor de las clases nuevas (`php-imports-verificacion-cierre`).
- `php artisan view:cache` (partials y `@include` resueltos).
- Rutas: `php artisan route:list --name=articulo_uso_tipo_cuenta` y `--name=articulo_propuesta_contable`.
- Recorrer cada herramienta visible de la pantalla (`copiar-modulo-verificacion-completa`).
- Casos del endpoint, contra la base real y en solo lectura:
  - combinación con regla y cuenta igual;
  - con regla y cuenta distinta (LABORATORIO + REPUESTO sobre un artículo con `123010-030`);
  - sin regla (COMPRA + OTRO);
  - CC no habilitado (SALA BINGO + CONTABLE);
  - empresa 3 sin la cuenta en el plan.
- Tests: solo con autorización del operador y con SQLite `:memory:` (`produccion-bd-y-tests`).

## 11. Qué no cambia

- Las OC, recepciones y asientos ya grabados conservan su cuenta y su CC.
- Los artículos que ya tienen cuenta no se tocan hasta que Contabilidad resuelva D2 y se ejecute la fase 3.
- El uso y el tipo ya reclasificados no se vuelven a tocar.

## 12. Riesgos a vigilar

- **Corte contable**: si cambia la cuenta de un artículo entre la recepción (COM) y la factura, la provisión y la factura podrían quedar en cuentas distintas. Hay que revisarlo en `RecepcionProveedorAsientoService` y `ComprobanteProveedorAsientoService` antes de la fase 3, y aplicar con recepciones abiertas en cero (fase 5, no fase 3).
- **Árbol de aprobación por CC (fase 4)**: la requisición elige el CC del árbol a partir de los CC de sus líneas (`resolverCentrocostoArbolParaGrabacion`, `centrocosto-arbol-grabacion.js`). Si el CC de la línea queda forzado por el Uso, cambia quién aprueba. Sin verificar todavía.
- **Presupuesto (fase 4)**: la validación de partida y CAPEX por línea (`ValidacionPresupuestoPartidaCapexLineas`) puede asociar la partida a un CC. Un CC forzado puede chocar con la partida elegida. Sin verificar todavía.
- **OC con varios CC (fase 4)**: una requisición con artículos de Usos con CC distintos genera una OC con varios CC. El flujo de factura ya prorratea (`ComprobanteProveedorCentrocostoSupport`), pero hay que probarlo con ese caso.
- **Usos compartidos (fase 4)**: cargar CC en mantenimiento, limpieza, descartables, librería, RRHH, administración o legales impide imputar esas compras al sector que consume (solo entre el 30% y el 57% de las líneas de 2026 ya usa el CC del Uso). Es una decisión de Contabilidad uso por uso.
- **Artículos imposibles de pedir (fase 4)**: 347 artículos activos tienen una cuenta de COMPRAS que no admite el CC de su Uso. Con la línea bloqueada no habría salida. Hay que corregir cuenta o CC antes de encender el Uso (el Excel de la fase 1 los lista).
- **Activo vs. resultado**: kiosco, tabaco y repuestos pasan de cuentas patrimoniales a resultado (o al revés) según la matriz. Lo firma Contabilidad antes de marcar la fila como `validada`.
- **Anita**: el cambio de `stkm_cta_contablec` modifica cómo Anita imputa las compras nativas desde ese momento.
