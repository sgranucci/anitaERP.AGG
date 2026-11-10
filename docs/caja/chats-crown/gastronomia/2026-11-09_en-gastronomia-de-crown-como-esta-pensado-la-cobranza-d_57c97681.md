# en gastronomia de crown, como esta pensado la cobranza de las facturas antes de generarlas?

- **Fecha:** 2026-11-09 21:58
- **Chat id:** `57c97681-4c88-445c-a6d0-a6b7dd34c00c`
- **Módulos:** gastronomia
- **Origen local:** `agent-transcripts/57c97681-4c88-445c-a6d0-a6b7dd34c00c`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

en gastronomia de crown, como esta pensado la cobranza de las facturas antes de generarlas?

## Asistente

Voy a revisar cómo está armado el flujo de cobranza en gastronomía Crown antes de emitir las facturas.
Voy a revisar cómo está armado el flujo de cobranza en gastronomía Crown antes de emitir las facturas.

## Asistente

Encontré el servicio de emisión; ahora miro el POS y el modelo de cuenta para ver si la cobranza se carga antes o junto con la factura.
Encontré el servicio de emisión; ahora miro el POS y el modelo de cuenta para ver si la cobranza se carga antes o junto con la factura.

## Asistente

En Crown **no hay una grilla de cobranza previa** como en gastronomía AGG. El cobro se decide al cerrar la cuenta y se aplica **en la misma transacción** que la factura.

## Flujo pensado

1. En el POS se abre el modal de cobro (`F5` / Cobrar) y se elige un **tipo de cobro**, no medios ni montos parciales.
2. Tipos:
   - **efectivo** — factura + cobranza en la cuenta de caja de efectivo de la empresa
   - **factura_sola** — igual que efectivo respecto de cobranza (por config)
   - **interna / GST** — factura **sin** cobranza (consumo interno / canje)
   - **hotel** — factura **sin** cobranza (cargo a habitación)
3. Orden interno (`GastronomiaCrownFacturaEmisionService`): **factura → cobranza (si corresponde) → CAE/CAEA → insumos → cierra cuenta**. Si ARCA falla, rollback de todo.

## Cómo se arma la cobranza

No se carga antes en pantalla. Si el tipo está en `GASTRONOMIA_CROWN_TIPOS_COBRO_CON_COBRANZA` (default `efectivo,factura_sola`), el backend inventa un único medio:

- cuenta de caja de efectivo (`GastronomiaCuentacajaEfectivo`)
- monto = total de la cuenta
- observación `POS Crown cuenta #… (tipo)`

Eso va a `GastronomiaCobranzaService::registrarCobranzaPos` **después** de crear la venta, no antes.

## Qué no hace Crown (frente a AGG)

- No pide grilla de medios (efectivo / tarjeta / CTG / etc.) antes de emitir.
- No valida “cobranza = total” en pantalla.
- Interna y hotel facturan; el cobro real queda fuera de caja (interno / hotel).

En resumen: la “cobranza previa” es solo la **elección del tipo**; el registro contable de caja ocurre **junto con** la factura, no como paso separado anterior.
En Crown **no hay una grilla de cobranza previa** como en gastronomía AGG. El cobro se decide al cerrar la cuenta y se aplica **en la misma transacción** que la factura.

## Flujo pensado

1. En el POS se abre el modal de cobro (`F5` / Cobrar) y se elige un **tipo de cobro**, no medios ni montos parciales.
2. Tipos:
   - **efectivo** — factura + cobranza en la cuenta de caja de efectivo de la empresa
   - **factura_sola** — igual que efectivo respecto de cobranza (por config)
   - **interna / GST** — factura **sin** cobranza (consumo interno / canje)
   - **hotel** — factura **sin** cobranza (cargo a habitación)
3. Orden interno (`GastronomiaCrownFacturaEmisionService`): **factura → cobranza (si corresponde) → CAE/CAEA → insumos → cierra cuenta**. Si ARCA falla, rollback de todo.

## Cómo se arma la cobranza

No se carga antes en pantalla. Si el tipo está en `GASTRONOMIA_CROWN_TIPOS_COBRO_CON_COBRANZA` (default `efectivo,factura_sola`), el backend inventa un único medio:

- cuenta de caja de efectivo (`GastronomiaCuentacajaEfectivo`)
- monto = total de la cuenta
- observación `POS Crown cuenta #… (tipo)`

Eso va a `GastronomiaCobranzaService::registrarCobranzaPos` **después** de crear la venta, no antes.

## Qué no hace Crown (frente a AGG)

- No pide grilla de medios (efectivo / tarjeta / CTG / etc.) antes de emitir.
- No valida “cobranza = total” en pantalla.
- Interna y hotel facturan; el cobro real queda fuera de caja (interno / hotel).

En resumen: la “cobranza previa” es solo la **elección del tipo**; el registro contable de caja ocurre **junto con** la factura, no como paso separado anterior.

## Usuario

y en donde funciona el F8 de descuento? deberia pedir la autorizacion aca en crown, el cliente y el codigo de descuento igual que a-comandera.c 2) quiero que la cobranza sea igual que en AGG con la grilla de medios de cobro, el F5 es efectivo directo igual que anita ahora a-comandera.c 3) el tema GST es todo lo cableado en anita a-comandera.c ? Se puede chequear mejor el a-comandera.c para ver si todo lo de ERP esta como ahi?

## Asistente

Voy a contrastar Crown con AGG y con `a-comandera.c` en F8/descuento, grilla de cobranza/F5 y GST.
Voy a contrastar Crown con AGG y con `a-comandera.c` en F8/descuento, grilla de cobranza/F5 y GST.

## Asistente

Voy a validar en el fuente de Anita los puntos de F8, F5 y GST para no dejar nada a medias.
Voy a validar en el fuente de Anita los puntos de F8, F5 y GST para no dejar nada a medias.

## Asistente

Sí: contrastado contra `a-comandera.c` (Crown CIP) y el POS AGG. Hoy Crown **no** replica esos tres flujos.

## 1) F8 / descuento

En Anita, **F8 = venta interna con descuento**, no “otro botón de cobrar”:

- Si `cpvta_dto_autor = S` → `carga_dto_autorizante()`: menú **Invitados / Staff / Descuento**, **autorizante** (legajo + ccosto), **cliente**, **código de descuento**.
- Si no → `carga_dto()`: cliente + código dto (+ password si el dto lo exige).
- Canje GST fuerza código `10` / cliente `000500` y sigue por F8.
- **F10** aplica el dto a la mesa sin facturar.

En Crown hoy:

- F8 solo abre el **mismo modal de cobro** que F5 (rótulo “Interna / GST”).
- El “descuento” es **F10** con un **% libre** (`porcentaje_descuento`), sin maestro, sin cliente interno, sin autorizante.
- AGG ya tiene F8 con maestro `descuento_gastronomia` + cliente interno, **pero sin** autorizante/legajo/password como Anita.

Conclusión: **sí debería pedir autorización + cliente + código** como `a-comandera`; en Crown eso **no está**.

## 2) Cobranza / F5

En Anita:

- **F5** → `cierra_comanda(EFE)` → 100 % efectivo, sin grilla.
- Multi-medio → modo `TES` + `carga_valores()` (`carga_valcom.fc`) + `COBR_graba_cobranza`.
- F9 = factura sola.

En AGG: grilla de medios + **F5 = efectivo directo** (`prepararCobranzaEfectivo` + emitir).

En Crown hoy: F5 abre modal; “efectivo” arma **un solo** medio automático. No hay grilla.

Lo que pedís encaja con Anita/AGG: **grilla tipo AGG** + **F5 one-shot efectivo**.

## 3) GST

En Anita **sí está cableado de punta a punta** (`gst.fc`):

1. Lectura track → `GST_lee_tarjeta`
2. `PATH_GST/ClientLoadAnita` + `GetRewardsAnita`
3. Elección de premio (`GST_elige_premio`)
4. Fuerza **F8** con dto del premio / autorizante
5. Persiste el canje al cerrar

En Crown ERP: solo flag `habilita_canje_gst` + guarda `tarjeta_gst` en la cuenta. **No** llama a la API GST, **no** elige premio, **no** aplica el % ni encadena a F8 real.

## Mapa rápido

| Capacidad | Anita | AGG | Crown hoy |
|---|---|---|---|
| F5 efectivo directo | Sí | Sí | No (modal) |
| Grilla multi-medio | Sí (`TES`) | Sí | No |
| F8 dto + cliente + código | Sí | Parcial (sin autorizante) | No |
| Autorizante legajo/CC | Sí | No | No |
| GST API + premio → F8 | Sí | N/A (Wigos) | Solo guarda track |

Fuente Anita: `/home/sergio/tmp/fuentes/crown/ventas.cipo/ventas/a-comandera.c` + `gst.fc` / `carga_valcom.fc`.

Si querés, el orden natural de implementación sería: **(2) F5 + grilla AGG** → **(1) F8 con autorizante/cliente/código** → **(3) GST real contra `PATH_GST`**. ¿Arranco por la cobranza?
Sí: contrastado contra `a-comandera.c` (Crown CIP) y el POS AGG. Hoy Crown **no** replica esos tres flujos.

## 1) F8 / descuento

En Anita, **F8 = venta interna con descuento**, no “otro botón de cobrar”:

- Si `cpvta_dto_autor = S` → `carga_dto_autorizante()`: menú **Invitados / Staff / Descuento**, **autorizante** (legajo + ccosto), **cliente**, **código de descuento**.
- Si no → `carga_dto()`: cliente + código dto (+ password si el dto lo exige).
- Canje GST fuerza código `10` / cliente `000500` y sigue por F8.
- **F10** aplica el dto a la mesa sin facturar.

En Crown hoy:

- F8 solo abre el **mismo modal de cobro** que F5 (rótulo “Interna / GST”).
- El “descuento” es **F10** con un **% libre** (`porcentaje_descuento`), sin maestro, sin cliente interno, sin autorizante.
- AGG ya tiene F8 con maestro `descuento_gastronomia` + cliente interno, **pero sin** autorizante/legajo/password como Anita.

Conclusión: **sí debería pedir autorización + cliente + código** como `a-comandera`; en Crown eso **no está**.

## 2) Cobranza / F5

En Anita:

- **F5** → `cierra_comanda(EFE)` → 100 % efectivo, sin grilla.
- Multi-medio → modo `TES` + `carga_valores()` (`carga_valcom.fc`) + `COBR_graba_cobranza`.
- F9 = factura sola.

En AGG: grilla de medios + **F5 = efectivo directo** (`prepararCobranzaEfectivo` + emitir).

En Crown hoy: F5 abre modal; “efectivo” arma **un solo** medio automático. No hay grilla.

Lo que pedís encaja con Anita/AGG: **grilla tipo AGG** + **F5 one-shot efectivo**.

## 3) GST

En Anita **sí está cableado de punta a punta** (`gst.fc`):

1. Lectura track → `GST_lee_tarjeta`
2. `PATH_GST/ClientLoadAnita` + `GetRewardsAnita`
3. Elección de premio (`GST_elige_premio`)
4. Fuerza **F8** con dto del premio / autorizante
5. Persiste el canje al cerrar

En Crown ERP: solo flag `habilita_canje_gst` + guarda `tarjeta_gst` en la cuenta. **No** llama a la API GST, **no** elige premio, **no** aplica el % ni encadena a F8 real.

## Mapa rápido

| Capacidad | Anita | AGG | Crown hoy |
|---|---|---|---|
| F5 efectivo directo | Sí | Sí | No (modal) |
| Grilla multi-medio | Sí (`TES`) | Sí | No |
| F8 dto + cliente + código | Sí | Parcial (sin autorizante) | No |
| Autorizante legajo/CC | Sí | No | No |
| GST API + premio → F8 | Sí | N/A (Wigos) | Solo guarda track |

Fuente Anita: `/home/sergio/tmp/fuentes/crown/ventas.cipo/ventas/a-comandera.c` + `gst.fc` / `carga_valcom.fc`.

Si querés, el orden natural de implementación sería: **(2) F5 + grilla AGG** → **(1) F8 con autorizante/cliente/código** → **(3) GST real contra `PATH_GST`**. ¿Arranco por la cobranza?

## Usuario

arranca a desarrollar lo faltante con el modo de descuento fijo para crown con lo que hace carga_dto_autorizante(). 2) Fijate que quede una UI muy atractiva e impactante para agregar la grilla de medios de pago con los iconos de cada uno con la funcionalidad igual que en AGG.

## Asistente

Arranco por F8/`carga_dto_autorizante` y la grilla de medios estilo AGG; primero inventariaré el POS Crown y los patrones AGG a reutilizar.
Arranco por F8/`carga_dto_autorizante` y la grilla de medios estilo AGG; primero inventariaré el POS Crown y los patrones AGG a reutilizar.

## Asistente

Sigo con el esquema de cuenta Crown, el maestro de descuentos y los medios rápidos de AGG para armar la UI.
Sigo con el esquema de cuenta Crown, el maestro de descuentos y los medios rápidos de AGG para armar la UI.

## Asistente

Empiezo por migración y backend; después la UI de grilla y el modal F8.
Empiezo por migración y backend; después la UI de grilla y el modal F8.

## Usuario

en la UI del POS estimo que podes achicar el bloque de mesas

## Asistente

Achico el panel de mesas y sigo con la grilla de cobro y el F8 en ese espacio ganado.
Achico el panel de mesas y sigo con la grilla de cobro y el F8 en ese espacio ganado.

## Asistente

Achico mesas y avanzo la grilla de cobro y el modal F8 en el espacio liberado.
Achico mesas y avanzo la grilla de cobro y el modal F8 en el espacio liberado.

## Usuario

agrego algo, todo esto no tiene que afectar en nada la facturacion POS de agg ni estacionamiento ni canje de marketing

## Asistente

Queda aislado en Crown: no toco POS AGG, estacionamiento ni canje marketing. Sigo solo en namespaces/vistas/JS `gastronomia_crown`.
Queda aislado en Crown: no toco POS AGG, estacionamiento ni canje marketing. Sigo solo en namespaces/vistas/JS `gastronomia_crown`.

## Asistente

Ajusto el orden para que `medios` no caiga en efectivo automático si la grilla viene vacía.
Ajusto el orden para que `medios` no caiga en efectivo automático si la grilla viene vacía.

## Asistente

Integro F5 directo, grilla F7 y modal F8 solo en el JS de Crown.
Integro F5 directo, grilla F7 y modal F8 solo en el JS de Crown.

## Asistente

Quedó **aislado a Crown**. No toqué POS AGG, estacionamiento ni canje marketing: solo `gastronomia_crown/*`, rutas `gastronomia-crown/api/*` y `config('gastronomia.crown.*')`.

### UI
- Panel **Mesas** más chico (`col-xl-2`) y botones compactos; Detalle gana espacio.
- **F5** = efectivo directo (sin modal).
- **F7 / Cobrar** = modal con chips con iconos + grilla multi-medio.
- **F8** = modal Invitados / Staff / Descuento + autorizante + CC + cliente + código dto.

### Backend Crown
- `medios_pago` en cobrar; tipo `medios` para la grilla.
- `aplicarDescuentoAutorizante()` estilo `carga_dto_autorizante`.
- APIs propias: `cuentas-caja`, `resolver-descuento`.
- Migración Crown-gated para campos de autorizante.

### Pendiente de tu OK
Para crear las columnas en BD (solo si `EMPRESA=CROWN`):

```bash
php artisan migrate --path=database/migrations/2026_11_09_210000_gastro_crown_cuenta_descuento_autorizante_crown.php
```

¿La corro?
Quedó **aislado a Crown**. No toqué POS AGG, estacionamiento ni canje marketing: solo `gastronomia_crown/*`, rutas `gastronomia-crown/api/*` y `config('gastronomia.crown.*')`.

### UI
- Panel **Mesas** más chico (`col-xl-2`) y botones compactos; Detalle gana espacio.
- **F5** = efectivo directo (sin modal).
- **F7 / Cobrar** = modal con chips con iconos + grilla multi-medio.
- **F8** = modal Invitados / Staff / Descuento + autorizante + CC + cliente + código dto.

### Backend Crown
- `medios_pago` en cobrar; tipo `medios` para la grilla.
- `aplicarDescuentoAutorizante()` estilo `carga_dto_autorizante`.
- APIs propias: `cuentas-caja`, `resolver-descuento`.
- Migración Crown-gated para campos de autorizante.

### Pendiente de tu OK
Para crear las columnas en BD (solo si `EMPRESA=CROWN`):

```bash
php artisan migrate --path=database/migrations/2026_11_09_210000_gastro_crown_cuenta_descuento_autorizante_crown.php
```

¿La corro?

## Usuario

1) correlo 2) como selecciona las cuentas de cuentas de caja para que aparezcan en cobranzas? con uso?

## Asistente

Corro la migración y confirmo cómo se filtran las cuentas de caja por uso.
Corro la migración y confirmo cómo se filtran las cuentas de caja por uso.

## Asistente

Migración corrida: columnas OK en `gastro_crown_cuenta`.

### Cómo salen las cuentas en la grilla

Sí: **por uso de cuenta de caja**, igual que AGG.

1. Resuelve el uso:
   - `GASTRONOMIA_USO_CUENTACAJA_ID` en `.env`, o
   - si no está, busca `usocuentacaja` con nombre **`Gastronomia`**
2. Lista cuentas que:
   - tienen ese uso asignado,
   - son **operativas**,
   - pasan `paraEmpresa` (empresa del PV / multiempresa),
   - no están en las de solo-automáticas (`GastronomiaCuentacajaSoloAutomaticaSupport`)

La lupa del renglón usa el mismo filtro (modal de cuenta de caja con uso gastronomía).

### En este Crown ahora

No hay uso `Gastronomia` y `GASTRONOMIA_USO_CUENTACAJA_ID` está vacío. Usos actuales: Rendición de máquinas, Remesas…, **Local**.

Sin ese uso, la grilla F7 va a salir vacía. Para que aparezcan hay que:

1. Crear (o elegir) un uso — idealmente `Gastronomia`, o setear `GASTRONOMIA_USO_CUENTACAJA_ID=<id>`, y  
2. En cada cuenta de caja del POS, marcar ese uso.

¿Querés que usemos el uso **Local (id 5)** como default Crown, o preferís crear el uso `Gastronomia`?
Migración corrida: columnas OK en `gastro_crown_cuenta`.

### Cómo salen las cuentas en la grilla

Sí: **por uso de cuenta de caja**, igual que AGG.

1. Resuelve el uso:
   - `GASTRONOMIA_USO_CUENTACAJA_ID` en `.env`, o
   - si no está, busca `usocuentacaja` con nombre **`Gastronomia`**
2. Lista cuentas que:
   - tienen ese uso asignado,
   - son **operativas**,
   - pasan `paraEmpresa` (empresa del PV / multiempresa),
   - no están en las de solo-automáticas (`GastronomiaCuentacajaSoloAutomaticaSupport`)

La lupa del renglón usa el mismo filtro (modal de cuenta de caja con uso gastronomía).

### En este Crown ahora

No hay uso `Gastronomia` y `GASTRONOMIA_USO_CUENTACAJA_ID` está vacío. Usos actuales: Rendición de máquinas, Remesas…, **Local**.

Sin ese uso, la grilla F7 va a salir vacía. Para que aparezcan hay que:

1. Crear (o elegir) un uso — idealmente `Gastronomia`, o setear `GASTRONOMIA_USO_CUENTACAJA_ID=<id>`, y  
2. En cada cuenta de caja del POS, marcar ese uso.

¿Querés que usemos el uso **Local (id 5)** como default Crown, o preferís crear el uso `Gastronomia`?

## Usuario

no tenemos que armar los usos, pero aca hay algo que no pasa en agg, las cuentas de caja de efectivo son distintas para cada sala, podes chequear eso en cuenta_caja?

## Asistente

Reviso en `cuentacaja` cómo se vincula el efectivo por sala en Crown frente a AGG.
Reviso en `cuentacaja` cómo se vincula el efectivo por sala en Crown frente a AGG.

## Asistente

Sí: en Crown el efectivo es **por sala**, y hoy el ERP lo trata como AGG (**solo por empresa**). Eso no cierra.

### Qué hay en `cuentacaja`

No tiene `sala_id`. El vínculo es por **nombre/código** de cada sala:

| Sala | Fondo fijo | Tesoro $ |
|---|---|---|
| CIP | `201` FONDO FIJO CIPOLLETTI (id 3) | `202` |
| LGR | `301` | `302` |
| VIE | `401` | `402` |
| GRO | `501` | `502` |
| EFA | `601` | `602` |
| CHO | `701` | `702` |
| CAT | `801` | `802` |
| VRE | `901` | `903` |
| CSA | — | `1603` Tesoro Cinco Saltos |

Ninguna de esas tiene uso asignado (pivot vacío).

### Qué hace hoy el POS

- F5 / efectivo: `GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA` → acá `{1: 3}` = **solo CIP**.
- Grilla: filtro por uso `Gastronomia` → en Crown **no existe**, grilla vacía.
- Los PV gastro están con `sala_id = 1` (CIP); `caja_id` null.

En Anita, `c_efectivo` del confpto es **cuenta contable**, no `cuentacaja`; la cobranza multi-medio va por `tctes`.

### Conclusión

No hace falta inventar usos. Hace falta un resolver **Crown por sala** (p. ej. `sala_id` del PV → fondo fijo/tesoro de esa sala), distinto del mapa AGG por empresa.

¿Lo armamos como mapa `sala_id → cuentacaja_id` en config Crown, o preferís cargar la cuenta de efectivo en la config del PV (`configuracion_puntoventa_gastronomia`)?
Sí: en Crown el efectivo es **por sala**, y hoy el ERP lo trata como AGG (**solo por empresa**). Eso no cierra.

### Qué hay en `cuentacaja`

No tiene `sala_id`. El vínculo es por **nombre/código** de cada sala:

| Sala | Fondo fijo | Tesoro $ |
|---|---|---|
| CIP | `201` FONDO FIJO CIPOLLETTI (id 3) | `202` |
| LGR | `301` | `302` |
| VIE | `401` | `402` |
| GRO | `501` | `502` |
| EFA | `601` | `602` |
| CHO | `701` | `702` |
| CAT | `801` | `802` |
| VRE | `901` | `903` |
| CSA | — | `1603` Tesoro Cinco Saltos |

Ninguna de esas tiene uso asignado (pivot vacío).

### Qué hace hoy el POS

- F5 / efectivo: `GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA` → acá `{1: 3}` = **solo CIP**.
- Grilla: filtro por uso `Gastronomia` → en Crown **no existe**, grilla vacía.
- Los PV gastro están con `sala_id = 1` (CIP); `caja_id` null.

En Anita, `c_efectivo` del confpto es **cuenta contable**, no `cuentacaja`; la cobranza multi-medio va por `tctes`.

### Conclusión

No hace falta inventar usos. Hace falta un resolver **Crown por sala** (p. ej. `sala_id` del PV → fondo fijo/tesoro de esa sala), distinto del mapa AGG por empresa.

¿Lo armamos como mapa `sala_id → cuentacaja_id` en config Crown, o preferís cargar la cuenta de efectivo en la config del PV (`configuracion_puntoventa_gastronomia`)?

## Usuario

como armarias, en lugar de uso, o con uso GASTRONOMIA, abrir otra configuracion de la sala para que tenga el mapa sala_id / cuentacaja_id? en donde estaria ese ABM?

## Asistente

Reviso dónde vive hoy el ABM de salas y la config por módulo, para proponerte el lugar más natural.
Reviso dónde vive hoy el ABM de salas y la config por módulo, para proponerte el lugar más natural.

## Asistente

Yo lo armaría **en dos capas**, no todo en el uso:

### 1) Uso `Gastronomia` (como AGG) — medios comunes
Visa, Master, MP, transferencias, etc. No dependen de la sala.  
Sirve para la **grilla F7**. No resuelve el efectivo Crown.

### 2) Mapa `sala → cuentacaja efectivo` — solo Crown
F5 y el chip “Efectivo” salen de la **sala del PV** (`configuracion_puntoventa_gastronomia.sala_id`), no de `empresa_id`.

## Dónde pondría el ABM

**No** en el ABM genérico `configuracion/sala` (maestro chico: código/nombre/empresa; lo usan todos los módulos).

**Sí** pantalla propia Crown:

**Configuración → Configuración por módulo → Ventas → Cuentas de caja gastronomía por sala**  
URL sugerida: `ventas/gastronomia-crown/configuracion-cuentacaja-sala`

Una grilla: Sala | Cuenta efectivo ($) | (opcional Tesoro / USD)  
Consulta de cuenta con modal (no `<select>` de tabla).

Tabla: `gastro_crown_sala_cuentacaja` (`sala_id`, `cuentacaja_efectivo_id`, …) gated Crown.

## Por qué no otras opciones

| Opción | Problema |
|---|---|
| Solo uso Gastronomia | El efectivo CIP/LGR/VIE… son cuentas distintas; el uso no distingue sala |
| Campo en cada PC/PV | 30+ terminales; se duplica y se desincroniza |
| Solo `.env` mapa sala | Vuelve a hardcode; no lo opera el usuario |
| Meterlo en ABM Sala genérico | Contamina un maestro compartido |

## Flujo en el POS Crown

1. Lee `sala_id` del PV.  
2. Efectivo F5 ← mapa sala.  
3. Resto de medios ← uso `Gastronomia` (si lo cargan) **o**, si preferís cero usos, solo tarjetas/MP en esa misma pantalla por sala (más carga operativa).

Recomendación: **uso para medios + ABM por sala para efectivo**.

Si te cierra, el siguiente paso es crear esa pantalla bajo Ventas (config por módulo) y cablear F5/F7 a ese mapa.
Yo lo armaría **en dos capas**, no todo en el uso:

### 1) Uso `Gastronomia` (como AGG) — medios comunes
Visa, Master, MP, transferencias, etc. No dependen de la sala.  
Sirve para la **grilla F7**. No resuelve el efectivo Crown.

### 2) Mapa `sala → cuentacaja efectivo` — solo Crown
F5 y el chip “Efectivo” salen de la **sala del PV** (`configuracion_puntoventa_gastronomia.sala_id`), no de `empresa_id`.

## Dónde pondría el ABM

**No** en el ABM genérico `configuracion/sala` (maestro chico: código/nombre/empresa; lo usan todos los módulos).

**Sí** pantalla propia Crown:

**Configuración → Configuración por módulo → Ventas → Cuentas de caja gastronomía por sala**  
URL sugerida: `ventas/gastronomia-crown/configuracion-cuentacaja-sala`

Una grilla: Sala | Cuenta efectivo ($) | (opcional Tesoro / USD)  
Consulta de cuenta con modal (no `<select>` de tabla).

Tabla: `gastro_crown_sala_cuentacaja` (`sala_id`, `cuentacaja_efectivo_id`, …) gated Crown.

## Por qué no otras opciones

| Opción | Problema |
|---|---|
| Solo uso Gastronomia | El efectivo CIP/LGR/VIE… son cuentas distintas; el uso no distingue sala |
| Campo en cada PC/PV | 30+ terminales; se duplica y se desincroniza |
| Solo `.env` mapa sala | Vuelve a hardcode; no lo opera el usuario |
| Meterlo en ABM Sala genérico | Contamina un maestro compartido |

## Flujo en el POS Crown

1. Lee `sala_id` del PV.  
2. Efectivo F5 ← mapa sala.  
3. Resto de medios ← uso `Gastronomia` (si lo cargan) **o**, si preferís cero usos, solo tarjetas/MP en esa misma pantalla por sala (más carga operativa).

Recomendación: **uso para medios + ABM por sala para efectivo**.

Si te cierra, el siguiente paso es crear esa pantalla bajo Ventas (config por módulo) y cablear F5/F7 a ese mapa.

## Usuario

bueno, armalo asi y que es F7?

## Asistente

F7 es la grilla multi-medio (Anita `TES`); F5 sigue siendo efectivo directo. Armo el ABM sala→cuenta efectivo y lo cableo al POS Crown.
F7 es la grilla multi-medio (Anita `TES`); F5 sigue siendo efectivo directo. Armo el ABM sala→cuenta efectivo y lo cableo al POS Crown.

## Asistente

Creo tabla, ABM tipo matriz, menú y cableado del efectivo por sala.
Creo tabla, ABM tipo matriz, menú y cableado del efectivo por sala.

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-2-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace names, tool names and tool descriptions. Matching ignores case. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tool names and descriptions within one namespace. Matching ignores case. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, with "... [truncated]" at each cut; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-2-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace names, tool names and tool descriptions. Matching ignores case. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tool names and descriptions within one namespace. Matching ignores case. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, with "... [truncated]" at each cut; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

bueno, armalo asi y que es F7?

## Asistente

**F7** en el POS Crown es la **grilla multi-medio** (equivalente a TES / `carga_valores` de Anita): abrís el modal, elegís Visa/MP/efectivo/etc. y cobrás. **F5** es 100% efectivo directo; **F8** es dto con autorizante.

Quedó armado así:
- ABM **Configuración → Ventas → Cuentas caja gastro por sala** (`ventas/gastronomia-crown/configuracion-cuentacaja-sala`)
- F5 resuelve efectivo por **sala** (mapa), no por empresa AGG
- F7 lista medios con uso **Gastronomia** (`usocuentacaja` id 6)
- Migración corrida (tabla + menú + permisos admin/Enc-sistemas)

Falta que carguen en el ABM la cuenta de efectivo de cada sala; sin eso F5 avisa y no cobra.
**F7** en el POS Crown es la **grilla multi-medio** (equivalente a TES / `carga_valores` de Anita): abrís el modal, elegís Visa/MP/efectivo/etc. y cobrás. **F5** es 100% efectivo directo; **F8** es dto con autorizante.

Quedó armado así:
- ABM **Configuración → Ventas → Cuentas caja gastro por sala** (`ventas/gastronomia-crown/configuracion-cuentacaja-sala`)
- F5 resuelve efectivo por **sala** (mapa), no por empresa AGG
- F7 lista medios con uso **Gastronomia** (`usocuentacaja` id 6)
- Migración corrida (tabla + menú + permisos admin/Enc-sistemas)

Falta que carguen en el ABM la cuenta de efectivo de cada sala; sin eso F5 avisa y no cobra.

## Usuario

1) da error 500 el POS 2) no hace falta F7 con la grilla de cobros si la haces aparecer siempre, la idea es que la usen si quieren y si le dan F5 asume efectivo directo sin ningun otro click

## Asistente

Reviso el 500 del POS y el flujo F5/F7 para que F5 cobre efectivo sin modal y F7 solo abra la grilla si la piden.
Reviso el 500 del POS y el flujo F5/F7 para que F5 cobre efectivo sin modal y F7 solo abra la grilla si la piden.

## Asistente

El 500 viene de un `@if` dentro de un `title=` que Blade compiló mal. Lo corrijo y dejo F5 directo / F7 solo a demanda.
El 500 viene de un `@if` dentro de un `title=` que Blade compiló mal. Lo corrijo y dejo F5 directo / F7 solo a demanda.

## Asistente

1) El 500 era un `@if` dentro del `title=` del panel Mesas: Blade compilaba el `@if` y dejaba el `@endif` literal. Ya renderiza bien.

2) Flujo de cobro:
- **F5** → efectivo directo, sin modal ni clicks extra
- **F7** (o herramienta “Medios”) → grilla solo si la piden

Recargá el POS y probá de nuevo.
1) El 500 era un `@if` dentro del `title=` del panel Mesas: Blade compilaba el `@if` y dejaba el `@endif` literal. Ya renderiza bien.

2) Flujo de cobro:
- **F5** → efectivo directo, sin modal ni clicks extra
- **F7** (o herramienta “Medios”) → grilla solo si la piden

Recargá el POS y probá de nuevo.

## Usuario

quiero que aparezca la grilla de medios siempre como en AGG y el F7 que sea un cambio de foco al primer item de la grilla, pero que se vea siempre 2) antes de cablear esto fijate si entra bien en pantalla del POS, si no, dejalo asi. 3) que falta para que todo quede listo, no veo las cuentas de tarjetas, etc, no armaste el uso gastronomia?

## Asistente

Reviso el layout AGG vs el POS Crown (si entra la grilla) y el estado del uso Gastronomia / cuentas asignadas.
Reviso el layout AGG vs el POS Crown (si entra la grilla) y el estado del uso Gastronomia / cuentas asignadas.

## Asistente

**Grilla siempre visible:** no la cableo. El POS Crown ya gasta bastante alto (topbar + F-keys + herramientas + 3 columnas). En AGG la cobranza vive bajo el detalle en la misma card; acá meterla fija achica demasiado las líneas. Queda en modal; **F7** la abre y enfoca el primer ítem. Si más adelante quieren el layout AGG, conviene un monitor alto o achicar mesas/carga.

**Por qué no ves tarjetas:** el uso **Gastronomia** sí está (id 6), pero **ninguna** cuenta de caja lo tiene asignado (`cuentacaja_usocuentacaja` = 0 filas en todo Crown). El listado del F7 sale vacío hasta que las vinculen.

**Qué falta para quedar listo**

| Ítem | Estado |
|---|---|
| Uso `Gastronomia` | Creado |
| Cuentas con ese uso (Visa 1001, Master 1002, Amex, Naranja, Diners, Cabal, MP, …) | **Falta asignar** en ABM cuenta de caja → usos |
| Mapa sala → efectivo (F5) | Tabla/menú listos, **0 salas cargadas** |
| F5 / F7 / F8 en POS | Hecho (F5 directo, F7 modal, F8 autorizante) |
| GST / Wigos en cobro | Pendiente (API stub) |
| `sala_id` real en cada PV | Casi todos siguen en sala 1 (CIP) |

En ABM de cada medio: agregar uso **Gastronomia**. En **Configuración → Ventas → Cuentas caja gastro por sala**: fondo fijo/tesoro por sala.

Si querés, con un OK explícito puedo asignar al uso Gastronomia las típicas (1001–1006 + Mercado Pago) sin tocar bancos/fondos fijos.
**Grilla siempre visible:** no la cableo. El POS Crown ya gasta bastante alto (topbar + F-keys + herramientas + 3 columnas). En AGG la cobranza vive bajo el detalle en la misma card; acá meterla fija achica demasiado las líneas. Queda en modal; **F7** la abre y enfoca el primer ítem. Si más adelante quieren el layout AGG, conviene un monitor alto o achicar mesas/carga.

**Por qué no ves tarjetas:** el uso **Gastronomia** sí está (id 6), pero **ninguna** cuenta de caja lo tiene asignado (`cuentacaja_usocuentacaja` = 0 filas en todo Crown). El listado del F7 sale vacío hasta que las vinculen.

**Qué falta para quedar listo**

| Ítem | Estado |
|---|---|
| Uso `Gastronomia` | Creado |
| Cuentas con ese uso (Visa 1001, Master 1002, Amex, Naranja, Diners, Cabal, MP, …) | **Falta asignar** en ABM cuenta de caja → usos |
| Mapa sala → efectivo (F5) | Tabla/menú listos, **0 salas cargadas** |
| F5 / F7 / F8 en POS | Hecho (F5 directo, F7 modal, F8 autorizante) |
| GST / Wigos en cobro | Pendiente (API stub) |
| `sala_id` real en cada PV | Casi todos siguen en sala 1 (CIP) |

En ABM de cada medio: agregar uso **Gastronomia**. En **Configuración → Ventas → Cuentas caja gastro por sala**: fondo fijo/tesoro por sala.

Si querés, con un OK explícito puedo asignar al uso Gastronomia las típicas (1001–1006 + Mercado Pago) sin tocar bancos/fondos fijos.

## Usuario

1) ok 2) en la asignacion de cuentas de efectivo tenes que modificarlo para que sea como grilla, porque podemos tener efectivo dolar y euro tambien, converti el abm en master detail, ademas a futuro por si arman alguna tarjeta de credito especial para alguna sala en particular, no solo cuentas de efectivo 3) el mapa actual no abre el modal de consulta de cuentas

## Asistente

Asigno las cuentas al uso Gastronomia, paso el ABM a master-detail (varias cuentas por sala) y arreglo el modal de consulta.
Asigno las cuentas al uso Gastronomia, paso el ABM a master-detail (varias cuentas por sala) y arreglo el modal de consulta.

## Asistente

Listo.

1) Uso **Gastronomia**: asignadas Visa, Master, Amex, Naranja, Diners, Cabal y Mercado Pago. En F7 ya deberían verse.

2) ABM pasado a **master-detail** (sala → N cuentas): efectivo $, u$s, euro o tarjeta especial por sala. Checkbox **Default F5** marca cuál usa el cobro directo.

3) Modal de consulta: el ABM viejo llamaba a una función inexistente. Ahora tiene JS propio (lupa / F1 / código).

Queda cargar por sala al menos el fondo fijo/tesoro y marcar Default F5. Recargá la pantalla del mapa.
Listo.

1) Uso **Gastronomia**: asignadas Visa, Master, Amex, Naranja, Diners, Cabal y Mercado Pago. En F7 ya deberían verse.

2) ABM pasado a **master-detail** (sala → N cuentas): efectivo $, u$s, euro o tarjeta especial por sala. Checkbox **Default F5** marca cuál usa el cobro directo.

3) Modal de consulta: el ABM viejo llamaba a una función inexistente. Ahora tiene JS propio (lupa / F1 / código).

Queda cargar por sala al menos el fondo fijo/tesoro y marcar Default F5. Recargá la pantalla del mapa.

## Usuario

1) que diferencia hay entre la herramienta Consulta y Buscar art? 2) si abro el modal de articulos no trae nada 3) facturo algo y no me trae ningun precio 4) en el F8 al abrir los usuarios y los clientes queda el modal arriba

## Asistente

Reviso las 4 cosas en el POS Crown: herramientas Consulta vs Buscar, modal de artículos vacío, precios en cero y stacking de modales en F8.
Reviso las 4 cosas en el POS Crown: herramientas Consulta vs Buscar, modal de artículos vacío, precios en cero y stacking de modales en F8.

## Asistente

Corrigo SKU AGG (V+4) que deja el modal vacío en Crown, el stacking de F8, la herramienta duplicada, y dejo claro el tema de la lista de precios.
Corrigo SKU AGG (V+4) que deja el modal vacío en Crown, el stacking de F8, la herramienta duplicada, y dejo claro el tema de la lista de precios.

## Asistente

1) **Consulta (F1)** y **Buscar art.** eran lo mismo (mismo modal). Saqué el duplicado de herramientas; queda solo **F1 Consulta**.

2) Modal vacío: el POS filtraba catálogo AGG `V` + 4 dígitos, y en Crown los SKU son `777`, `922`, etc. Ajusté env a prefijo vacío / dígitos 0. Recargá el POS (Ctrl+F5).

3) Precios en 0: los PV apuntan a lista **1 (A-PUBLICO)**; la que tiene masa de precios Crown es la **21 (STD Diciemb.)** y ningún PV la usa. Además varios ítems en 21 tienen precio 0 en la tabla. Hay que poner la lista correcta en la config del PC. ¿Actualizo los PV gastro a lista 21?

4) F8: las consultas de usuario/cliente/CC ya se apilan por encima del modal.
1) **Consulta (F1)** y **Buscar art.** eran lo mismo (mismo modal). Saqué el duplicado de herramientas; queda solo **F1 Consulta**.

2) Modal vacío: el POS filtraba catálogo AGG `V` + 4 dígitos, y en Crown los SKU son `777`, `922`, etc. Ajusté env a prefijo vacío / dígitos 0. Recargá el POS (Ctrl+F5).

3) Precios en 0: los PV apuntan a lista **1 (A-PUBLICO)**; la que tiene masa de precios Crown es la **21 (STD Diciemb.)** y ningún PV la usa. Además varios ítems en 21 tienen precio 0 en la tabla. Hay que poner la lista correcta en la config del PC. ¿Actualizo los PV gastro a lista 21?

4) F8: las consultas de usuario/cliente/CC ya se apilan por encima del modal.

## Usuario

2) no, esta mal, en crown los articulos de venta son con Vxxxxx (5 digitos en lugar de 4), eso estaba en .env, no? 3) actualiza a la lista que usa el anita actual

## Asistente

Restauro el catálogo Crown a `V` + 5 dígitos y busco en Anita qué lista de precios está vigente para actualizar los PV.
Restauro el catálogo Crown a `V` + 5 dígitos y busco en Anita qué lista de precios está vigente para actualizar los PV.

## Usuario

2) no, esta mal, en crown los articulos de venta son con Vxxxxx (5 digitos en lugar de 4), eso estaba en .env, no? 3) actualiza a la lista que usa el anita actual

## Asistente

2) Corregido: **`GASTRONOMIA_SKU_CATALOGO_PREFIJO=V`** y **`DIGITOS_SUFIJO=5`** (había quedado vacío/0; antes estaba mal en 4). Recargá el POS.

3) Contrasté con Anita (`cpvta_lista`): **ya estaban alineados** (mayoría **A-PUBLICO** id 1; carro/hotel/eventos con las suyas). `V00001` en lista 1 = **$3000**. El precio en 0 venía del filtro V+4, no de la lista.
2) Corregido: **`GASTRONOMIA_SKU_CATALOGO_PREFIJO=V`** y **`DIGITOS_SUFIJO=5`** (había quedado vacío/0; antes estaba mal en 4). Recargá el POS.

3) Contrasté con Anita (`cpvta_lista`): **ya estaban alineados** (mayoría **A-PUBLICO** id 1; carro/hotel/eventos con las suyas). `V00001` en lista 1 = **$3000**. El precio en 0 venía del filtro V+4, no de la lista.

## Usuario

1) si tengo el item comandado y enviado al kds, que indique el estado en el KDS, en el ejemplo de la unica mesa abierta, el kds ya lo entrego. 2) no vi en el kds el boton para poder ver la receta 3) como selecciona el sistema a que KDS va? porque podemos tener varios en la sala (cocina, panaderia, postres, barra para bebidas)

## Asistente

Voy a revisar cómo está armado el KDS en Crown: estado en comanda, botón de receta y el ruteo a cocina/barra/etc.
Voy a revisar cómo está armado el KDS en Crown: estado en comanda, botón de receta y el ruteo a cocina/barra/etc.

## Asistente

Reviso cómo el POS muestra el estado de las líneas comandadas y cómo se resuelve el área KDS al marchar.
Reviso cómo el POS muestra el estado de las líneas comandadas y cómo se resuelve el área KDS al marchar.

## Asistente

No hay áreas KDS cargadas todavía. Voy a mostrar el estado en el POS, sumar el botón de receta en el KDS y dejar claro el ruteo por área.
No hay áreas KDS cargadas todavía. Voy a mostrar el estado en el POS, sumar el botón de receta en el KDS y dejar claro el ruteo por área.

## Asistente

Implemento estado KDS en el POS y el botón Receta en la pantalla de cocina.
Implemento estado KDS en el POS y el botón Receta en la pantalla de cocina.

## Asistente

Ahora el botón Receta en el KDS y el permiso para abrirlo desde cocina.
Ahora el botón Receta en el KDS y el permiso para abrirlo desde cocina.

## Asistente

1) **Estado en el POS:** cada ítem marchado muestra el estado del KDS (`En KDS` → `Preparando` → `Listo` → `Entregado`). En tu mesa abierta, café chico/doble ya salen como **Entregado**. El POS refresca solo mientras haya marchas pendientes.

2) **Receta en el KDS:** cada línea tiene botón **Receta** (abre la fórmula en otra solapa). Solo aparece en comandas activas (las entregadas ya no están en la grilla).

3) **A qué KDS va:** por **área de comanda** según la **subcategoría** del artículo:
   - ABM: `Ventas → Área comanda gastronomía` (cocina, panadería, postres, barra…)
   - En cada **subcategoría**, asignás el área destino
   - Al marchar, se agrupa por área → cada KDS filtra con el chip **Área**

Hoy en Crown **no hay áreas cargadas** (`area_comanda_gastronomia` vacío), por eso todo va a un solo pool sin destino. Si querés, armo las 4 áreas (cocina / panadería / postres / barra) y te indico cómo mapear las subcategorías.
1) **Estado en el POS:** cada ítem marchado muestra el estado del KDS (`En KDS` → `Preparando` → `Listo` → `Entregado`). En tu mesa abierta, café chico/doble ya salen como **Entregado**. El POS refresca solo mientras haya marchas pendientes.

2) **Receta en el KDS:** cada línea tiene botón **Receta** (abre la fórmula en otra solapa). Solo aparece en comandas activas (las entregadas ya no están en la grilla).

3) **A qué KDS va:** por **área de comanda** según la **subcategoría** del artículo:
   - ABM: `Ventas → Área comanda gastronomía` (cocina, panadería, postres, barra…)
   - En cada **subcategoría**, asignás el área destino
   - Al marchar, se agrupa por área → cada KDS filtra con el chip **Área**

Hoy en Crown **no hay áreas cargadas** (`area_comanda_gastronomia` vacío), por eso todo va a un solo pool sin destino. Si querés, armo las 4 áreas (cocina / panadería / postres / barra) y te indico cómo mapear las subcategorías.

## Usuario

1) no se ve bien el codigo del articulo al tickear, esta muy grande la letra, el problema es cuando lo trae de la consulta como V00001 eso no entra, o le sacas todo y pones solo los numeros significativos o modificas la tipografia 2) arma las areas y como mapeo, no entiendo cada KDS como se configura para leer un area determinada segun la IP en donde este trabajando

## Asistente

Voy a revisar el tipado del código en el POS y cómo el KDS elige área (IP / puesto).
Voy a revisar el tipado del código en el POS y cómo el KDS elige área (IP / puesto).

## Asistente

En Crown las ventas van por categoría (no hay subcategorías). Voy a achicar el código en pantalla, crear las áreas y cablear el KDS por IP.
En Crown las ventas van por categoría (no hay subcategorías). Voy a achicar el código en pantalla, crear las áreas y cablear el KDS por IP.

## Usuario

antes de mapear las areas, fijate en cncadmin como comanda.fc usa la tabla de areas y crea las mismas en el POS de ERP

## Asistente

Busco en cncadmin cómo `comanda.fc` usa la tabla de áreas para replicarlas en el POS.
Busco en cncadmin cómo `comanda.fc` usa la tabla de áreas para replicarlas en el POS.

## Asistente

En Anita las áreas son `areacom` (1–6) y el destino sale de la agrupación del artículo (`stkagr.stka_area`). Creo esas mismas áreas en el ERP y achico el código del POS.
En Anita las áreas son `areacom` (1–6) y el destino sale de la agrupación del artículo (`stkagr.stka_area`). Creo esas mismas áreas en el ERP y achico el código del POS.

## Asistente

Áreas creadas iguales a Anita `areacom` (cncadmin):

| Código | Nombre |
|---|---|
| 1 | ALIMENTOS |
| 2 | BEBIDAS |
| 3 | KIOSCO |
| 4 | POSTRES |
| 5 | ENTRADAS |
| 6 | SUSHI |

Cómo lo usa `comanda.fc` (no es “el KDS elige por IP”):
1. El artículo tiene **agrupación** → `stkagr.stka_area` apunta a `areacom`.
2. Al marchar, agrupa por ese área e imprime un ticket por área.
3. **`confcomanda(host, área)`**: en cada PC (barra1a, sala5, IP…) dice **qué áreas puede imprimir** ese host. No define qué ve un monitor KDS.

En el ERP hoy: las áreas ya están; el ruteo del ítem aún no (falta mapear agrupación/categoría → área, como Anita). El filtro KDS por chip **Área** ya funciona.

También: el código del POS muestra solo dígitos significativos (`V00001` → `1`) con tipografía más chica. Ctrl+F5.

Cuando quieras, el siguiente paso es sincronizar `stkagr` → área por artículo (como Anita) y, si hace falta, una estación KDS por IP que abra ya filtrada a un área.
Áreas creadas iguales a Anita `areacom` (cncadmin):

| Código | Nombre |
|---|---|
| 1 | ALIMENTOS |
| 2 | BEBIDAS |
| 3 | KIOSCO |
| 4 | POSTRES |
| 5 | ENTRADAS |
| 6 | SUSHI |

Cómo lo usa `comanda.fc` (no es “el KDS elige por IP”):
1. El artículo tiene **agrupación** → `stkagr.stka_area` apunta a `areacom`.
2. Al marchar, agrupa por ese área e imprime un ticket por área.
3. **`confcomanda(host, área)`**: en cada PC (barra1a, sala5, IP…) dice **qué áreas puede imprimir** ese host. No define qué ve un monitor KDS.

En el ERP hoy: las áreas ya están; el ruteo del ítem aún no (falta mapear agrupación/categoría → área, como Anita). El filtro KDS por chip **Área** ya funciona.

También: el código del POS muestra solo dígitos significativos (`V00001` → `1`) con tipografía más chica. Ctrl+F5.

Cuando quieras, el siguiente paso es sincronizar `stkagr` → área por artículo (como Anita) y, si hace falta, una estación KDS por IP que abra ya filtrada a un área.

## Usuario

como en agg tenemos el area por subcategoria, entonces en categoria hace el cambio gated a crown y avanza con la sincronizacion. 2) Como accedo a la configuracion de la comanda por IP?

## Asistente

Voy a ver cómo AGG ata área↔subcategoría y cómo sincronizar eso en Crown por categoría; después te indico dónde se configura la comanda por IP.
Voy a ver cómo AGG ata área↔subcategoría y cómo sincronizar eso en Crown por categoría; después te indico dónde se configura la comanda por IP.

## Asistente

Implemento tabla + ABM Crown, resolución en el POS y sync desde Anita `stkagr`.
Implemento tabla + ABM Crown, resolución en el POS y sync desde Anita `stkagr`.

## Asistente

1) Listo en Crown:
- Solapa **Áreas de comanda** en ABM Categoría (gated Crown)
- POS resuelve área por **categoría** (AGG sigue por subcategoría)
- Sync Anita `stkagr.stka_area` → **26 vínculos** (ej. CAFETERIA→BEBIDAS, PLATOS→ALIMENTOS, POSTRES→POSTRES)

Re-sync: `php artisan ventas:sincronizar-categoria-area-comanda-crown --ejecutar`

2) Config por IP/host: **Configuración → Configuración por módulo → Ventas → Config. terminales gastronomía**  
URL: `/ventas/configuracion-puntoventa-gastronomia`  
Ahí el campo **Identificador de PC** es el host/IP (`barra1a`, `192.168.1.1`, etc.) y la **Salida de comandas** del puesto.  
En Anita, `confcomanda` además listaba área×host×impresora; en ERP hoy es un puesto = una salida principal (más salidas B/K en el mismo form).
1) Listo en Crown:
- Solapa **Áreas de comanda** en ABM Categoría (gated Crown)
- POS resuelve área por **categoría** (AGG sigue por subcategoría)
- Sync Anita `stkagr.stka_area` → **26 vínculos** (ej. CAFETERIA→BEBIDAS, PLATOS→ALIMENTOS, POSTRES→POSTRES)

Re-sync: `php artisan ventas:sincronizar-categoria-area-comanda-crown --ejecutar`

2) Config por IP/host: **Configuración → Configuración por módulo → Ventas → Config. terminales gastronomía**  
URL: `/ventas/configuracion-puntoventa-gastronomia`  
Ahí el campo **Identificador de PC** es el host/IP (`barra1a`, `192.168.1.1`, etc.) y la **Salida de comandas** del puesto.  
En Anita, `confcomanda` además listaba área×host×impresora; en ERP hoy es un puesto = una salida principal (más salidas B/K en el mismo form).

## Usuario

sigo sin entender como armo la PC que va a tener el KDS activo para que lea las comandas correctas, porque es distinto a la del POS, una PC POS Puede tickear algo y sale en la cocina en la otra PC con el KDS. 2) achicar el cuadro del KDS esta muy desproporcionado 3) como es el filtro del modal de consulta de articulos? no veo el anular articulos o desactivar

## Asistente

Voy a cablear la estación KDS por IP (aparte del POS), achicar el selector de destino y revisar el filtro del modal de artículos.
Voy a cablear la estación KDS por IP (aparte del POS), achicar el selector de destino y revisar el filtro del modal de artículos.

## Asistente

Implemento estaciones KDS por IP (independientes del POS), achico el selector de destino y aclaro el filtro del modal.
Implemento estaciones KDS por IP (independientes del POS), achico el selector de destino y aclaro el filtro del modal.

## Asistente

1) **PC KDS ≠ PC POS.** Son dos equipos:
- POS tickea y marcha → la comanda va al área de la **categoría** del artículo.
- En la PC de cocina abrís `/ventas/gastronomia-crown/kds` → botón **Esta PC** → elegís área (ej. ALIMENTOS) y Guardar. Queda fijada por **IP** de esa máquina.
- Barra: otra PC KDS → área BEBIDAS. Así cada pantalla solo ve lo suyo.

2) Achiqué **KDS destino** (`form-control-sm`, columna más chica) y el label ahora dice **Auto (categoría)**. Ctrl+F5 en el POS.

3) Filtro del modal F1 en el POS:
- Solo **activos** (no muestra inactivos/anulados)
- Solo **facturables** (`nofactura=0`)
- Solo SKU **V + 5 dígitos**
- Precio de la lista del puesto

Anular/desactivar no está en el modal: se hace en **Stock → Artículos** (estado). El POS no lista esos.
1) **PC KDS ≠ PC POS.** Son dos equipos:
- POS tickea y marcha → la comanda va al área de la **categoría** del artículo.
- En la PC de cocina abrís `/ventas/gastronomia-crown/kds` → botón **Esta PC** → elegís área (ej. ALIMENTOS) y Guardar. Queda fijada por **IP** de esa máquina.
- Barra: otra PC KDS → área BEBIDAS. Así cada pantalla solo ve lo suyo.

2) Achiqué **KDS destino** (`form-control-sm`, columna más chica) y el label ahora dice **Auto (categoría)**. Ctrl+F5 en el POS.

3) Filtro del modal F1 en el POS:
- Solo **activos** (no muestra inactivos/anulados)
- Solo **facturables** (`nofactura=0`)
- Solo SKU **V + 5 dígitos**
- Precio de la lista del puesto

Anular/desactivar no está en el modal: se hace en **Stock → Artículos** (estado). El POS no lista esos.

## Usuario

sigue feo el area de tickeo 2) el filtro por precio, podria traer algo sin cargo? deberia estar cargado con precio vigente 0? no veo en el crud de articulos en editar el boton del estado del articulo

## Asistente

Reviso el layout de carga, el manejo de precio 0 y el botón de estado en el ABM de artículos.
Reviso el layout de carga, el manejo de precio 0 y el botón de estado en el ABM de artículos.

## Asistente

Rediseño el área de carga, aclaro precio 0 vs sin precio, y dejo visible el botón de estado (admin no tenía el permiso).
Rediseño el área de carga, aclaro precio 0 vs sin precio, y dejo visible el botón de estado (admin no tenía el permiso).

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-2-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace names, tool names and tool descriptions. Matching ignores case. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tool names and descriptions within one namespace. Matching ignores case. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, with "... [truncated]" at each cut; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-2-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace names, tool names and tool descriptions. Matching ignores case. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tool names and descriptions within one namespace. Matching ignores case. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, with "... [truncated]" at each cut; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

sigue feo el area de tickeo 2) el filtro por precio, podria traer algo sin cargo? deberia estar cargado con precio vigente 0? no veo en el crud de articulos en editar el boton del estado del articulo

## Asistente

Implementando los tres puntos: pulir Carga, filtro de precio (0 = sin cargo), y botón de estado visible junto al campo.
Implementando los tres puntos: pulir Carga, filtro de precio (0 = sin cargo), y botón de estado visible junto al campo.

## Asistente

Listo. Resumen de lo que quedó:

**1) Carga** — Código compacto (prefijo `V` + dígitos chicos), descripción en una sola línea (sin caja vacía), KDS a ancho completo, Prepara y precio al lado. El preview muestra **Sin cargo** si el precio es 0, o **Sin precio** si no hay fila vigente.

**2) Precio** — Sí: un artículo **sin cargo** debe tener precio vigente **0** cargado en la lista del puesto. Si no hay fila de precio, no entra en el modal (filtro `requiere_precio`) y el POS lo rechaza al tickear. Diferencia: `0` explícito = cortesía; sin registro = no se vende.

**3) Estado** — El botón **Inactivar / Activar** quedó al lado del campo Estado (además del toolbar). En Crown se asignó `cambiar-estado-articulos` a `Enc-admin` y roles de gastronomía/stock/compras. Si ya estabas logueado, recargá sesión o volvé a entrar para ver el permiso.
Listo. Resumen de lo que quedó:

**1) Carga** — Código compacto (prefijo `V` + dígitos chicos), descripción en una sola línea (sin caja vacía), KDS a ancho completo, Prepara y precio al lado. El preview muestra **Sin cargo** si el precio es 0, o **Sin precio** si no hay fila vigente.

**2) Precio** — Sí: un artículo **sin cargo** debe tener precio vigente **0** cargado en la lista del puesto. Si no hay fila de precio, no entra en el modal (filtro `requiere_precio`) y el POS lo rechaza al tickear. Diferencia: `0` explícito = cortesía; sin registro = no se vende.

**3) Estado** — El botón **Inactivar / Activar** quedó al lado del campo Estado (además del toolbar). En Crown se asignó `cambiar-estado-articulos` a `Enc-admin` y roles de gastronomía/stock/compras. Si ya estabas logueado, recargá sesión o volvé a entrar para ver el permiso.

## Usuario

asi quedo mejor pero el input del codigo sku es demasiado grande 2) en el kds no veo bien como quedo, si configuras con el boton para que tenes las etiquetas de filtro? ademas esa linea verde no tiene sentido. Deja toda la configuracion sala/area arriba en la linea de la barra

## Asistente

Voy a achicar el input del SKU en Carga y rearmar la barra del KDS: filtros sala/área arriba y fuera la franja verde.
Voy a achicar el input del SKU en Carga y rearmar la barra del KDS: filtros sala/área arriba y fuera la franja verde.

## Asistente

Achico el SKU a un ancho fijo y rearmo el KDS: sin franja verde; sala/área en la barra; si la estación está fijada, solo badges (sin chips sueltos).
Achico el SKU a un ancho fijo y rearmo el KDS: sin franja verde; sala/área en la barra; si la estación está fijada, solo badges (sin chips sueltos).

## Asistente

Listo.

**Carga:** el código queda angosto (`V` + dígitos + lupa), no a todo el ancho del panel.

**KDS:** saqué la franja verde. Sala/área van en la barra de arriba.
- Con **Esta PC** configurada: solo ves las etiquetas fijas de esa estación (no hay chips para cambiar a mano; se cambia con el engranaje).
- Sin estación: los chips de filtro quedan en esa misma barra, hasta que configures la PC.
Listo.

**Carga:** el código queda angosto (`V` + dígitos + lupa), no a todo el ancho del panel.

**KDS:** saqué la franja verde. Sala/área van en la barra de arriba.
- Con **Esta PC** configurada: solo ves las etiquetas fijas de esa estación (no hay chips para cambiar a mano; se cambia con el engranaje).
- Sin estación: los chips de filtro quedan en esa misma barra, hasta que configures la PC.

## Usuario

en el sector de items, agrega el codigo del articulo, tenemos mucho lugar 2) los cubiertos es para elegir el KDS? mejora la pantalla (imagen 2) esta muy fea asi, pone etiquetas o algo mas imapactante 3) agrega otro boton para que tambien se pueda agregar observaciones al item para la comanda igual que la herramienta y como tenemos en AGG

## Asistente

Voy a sumar el SKU en las líneas, reemplazar el `prompt` del KDS por un modal con etiquetas, y agregar el botón de observación del ítem como en AGG.
Voy a sumar el SKU en las líneas, reemplazar el `prompt` del KDS por un modal con etiquetas, y agregar el botón de observación del ítem como en AGG.

## Asistente

Implementando SKU en filas, modal de KDS con chips y botón de observación por ítem.
Implementando SKU en filas, modal de KDS con chips y botón de observación por ítem.

## Asistente

Quedó así:

1. **Código** — columna **Cód.** con el SKU (ej. `V01386`) en cada ítem.
2. **Cubiertos** — sí, es para elegir el **KDS / prepara**. Ahora abre un modal con chips de área (Auto, ALIMENTOS, BEBIDAS…) y el switch Prepara; se acabó el `prompt` del navegador.
3. **Observación** — ícono de nota en cada fila (además de F11 / Obs. ítem). Modal con texto para la comanda, como en AGG; se ve debajo del artículo si hay nota.
Quedó así:

1. **Código** — columna **Cód.** con el SKU (ej. `V01386`) en cada ítem.
2. **Cubiertos** — sí, es para elegir el **KDS / prepara**. Ahora abre un modal con chips de área (Auto, ALIMENTOS, BEBIDAS…) y el switch Prepara; se acabó el `prompt` del navegador.
3. **Observación** — ícono de nota en cada fila (además de F11 / Obs. ítem). Modal con texto para la comanda, como en AGG; se ve debajo del artículo si hay nota.

## Usuario

1) al abrir el modal de indicaciones poner foco en la carga de la leyenda 2) como abro una cuenta sin mesa para alguien eventual que puede estar en la barra?

## Asistente

Reviso el foco del modal de observación y cómo queda expuesto el alta de cuenta sin mesa (barra).
Reviso el foco del modal de observación y cómo queda expuesto el alta de cuenta sin mesa (barra).

## Asistente

Arreglo el foco del modal y dejo bien visible/usable la cuenta de barra (+ sin mesa).
Arreglo el foco del modal y dejo bien visible/usable la cuenta de barra (+ sin mesa).

## Asistente

1. Al abrir el modal de indicaciones, el foco queda en el textarea (al terminar de mostrarse).

2. Cuenta sin mesa / barra: en el panel de mesas tocá **+ Barra**. Abre una cuenta eventual sin mesa; aparece abajo en **Abiertas** con ícono de copa. Después tickeás normal.
1. Al abrir el modal de indicaciones, el foco queda en el textarea (al terminar de mostrarse).

2. Cuenta sin mesa / barra: en el panel de mesas tocá **+ Barra**. Abre una cuenta eventual sin mesa; aparece abajo en **Abiertas** con ícono de copa. Después tickeás normal.

## Usuario

y como veo las cuentas que quedan abiertas sin facturar?, fijate de agregar los controles del cierre para esto igual que tenemos en agg

## Asistente

Reviso cómo AGG lista y cierra cuentas abiertas, y qué hay hoy en Crown, para replicar esos controles.
Reviso cómo AGG lista y cierra cuentas abiertas, y qué hay hoy en Crown, para replicar esos controles.

## Asistente

Implemento listado de cuentas abiertas (mesa + barra) y cierre sin facturar / cerrar todas, como en AGG.
Implemento listado de cuentas abiertas (mesa + barra) y cierre sin facturar / cerrar todas, como en AGG.

## Asistente

Quedó abajo del panel de mesas/planos:

**Cuentas abiertas** — lista todas las sin facturar (mesa y barra), con `#`, etiqueta y total. Clic para abrirlas.

Controles (como AGG):
- **Cerrar** — cierra la cuenta activa sin facturar
- **Cerrar todas** — cierra todas las abiertas de la sala/PC

La mesa queda libre; los ítems se conservan en la cuenta cerrada.
Quedó abajo del panel de mesas/planos:

**Cuentas abiertas** — lista todas las sin facturar (mesa y barra), con `#`, etiqueta y total. Clic para abrirlas.

Controles (como AGG):
- **Cerrar** — cierra la cuenta activa sin facturar
- **Cerrar todas** — cierra todas las abiertas de la sala/PC

La mesa queda libre; los ítems se conservan en la cuenta cerrada.
