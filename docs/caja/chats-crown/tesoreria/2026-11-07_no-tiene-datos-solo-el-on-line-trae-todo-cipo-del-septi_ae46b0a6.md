# no tiene datos solo el on-line, trae todo cipo del septiembre y octubre

- **Fecha:** 2026-11-07 17:48
- **Chat id:** `ae46b0a6-4dcf-428f-9161-e43fe7444f68`
- **Módulos:** tesoreria
- **Origen local:** `agent-transcripts/ae46b0a6-4dcf-428f-9161-e43fe7444f68`

> Export redactado para viaje Crown. Sin tool dumps completos; solo diálogo útil.

## Usuario

no tiene datos solo el on-line, trae todo cipo del septiembre y octubre

## Asistente

Voy a revisar cómo se carga el “Resultado online” de CIP y de dónde salen los datos de septiembre/octubre.
Voy a revisar cómo se carga el “Resultado online” de CIP y de dónde salen los datos de septiembre/octubre.

## Asistente

Confirmado: en CIP solo hay slots; el casino está en cero. Corro el dry-run del import para sep–oct.
Confirmado: en CIP solo hay slots; el casino está en cero. Corro el dry-run del import para sep–oct.

## Asistente

Anita también trae apostado en 0; voy a mirar qué hay realmente en `resonline` / `resjonline` para CIP.
Anita también trae apostado en 0; voy a mirar qué hay realmente en `resonline` / `resjonline` para CIP.

## Asistente

`resjonline` está vacío desde 2014; reviso las tablas `online` / `novonline` / `resultado`.
`resjonline` está vacío desde 2014; reviso las tablas `online` / `novonline` / `resultado`.

## Asistente

El casino online está en `efectivo` (`efe_venta_online` / `efe_pago_online`), no en `resjonline`. Voy a corregir el import y correr dry-run.
El casino online está en `efectivo` (`efe_venta_online` / `efe_pago_online`), no en `resjonline`. Voy a corregir el import y correr dry-run.

## Asistente

En CIP el listado solo tenía **slots online** porque el import buscaba `resonline`/`resjonline` (vacías o inexistentes). El casino online real está en las rendiciones de caja: `efectivo.efe_venta_online` / `efe_pago_online`. Ya corregí el import y corrí dry-run.

**Dry-run CIP 2026-09-01 → 2026-10-31** (sin grabar):

| Acción | Cantidad | Qué trae |
|---|---|---|
| **Crear** | **30** (todo septiembre) | Slots + asistencia; casino online = 0 (en Anita septiembre no tiene venta/pago online) |
| **Actualizar** | **31** (todo octubre) | Completa apostado/pagado/resultado + asistencia; ej. 31/10: apostado 7.160.757 → resultado 7.130.757 |
| Omitir | 0 | — |

Octubre ya tenía filas solo con Res. slots; el update las completa. Septiembre se crea de cero (slots sí; casino online no hay en Anita).

¿Ejecuto el import con `--ejecutar` para ese rango?
En CIP el listado solo tenía **slots online** porque el import buscaba `resonline`/`resjonline` (vacías o inexistentes). El casino online real está en las rendiciones de caja: `efectivo.efe_venta_online` / `efe_pago_online`. Ya corregí el import y corrí dry-run.

**Dry-run CIP 2026-09-01 → 2026-10-31** (sin grabar):

| Acción | Cantidad | Qué trae |
|---|---|---|
| **Crear** | **30** (todo septiembre) | Slots + asistencia; casino online = 0 (en Anita septiembre no tiene venta/pago online) |
| **Actualizar** | **31** (todo octubre) | Completa apostado/pagado/resultado + asistencia; ej. 31/10: apostado 7.160.757 → resultado 7.130.757 |
| Omitir | 0 | — |

Octubre ya tenía filas solo con Res. slots; el update las completa. Septiembre se crea de cero (slots sí; casino online no hay en Anita).

¿Ejecuto el import con `--ejecutar` para ese rango?

## Usuario

pero para que sirve en resultado on-line lo de casino on-line? que flujo del anita estamos emulando aca?

## Asistente

Buena pregunta: voy a rastrear en Anita/Anexo I qué flujo es el de “casino online” frente a “slots online”.
Buena pregunta: voy a rastrear en Anita/Anexo I qué flujo es el de “casino online” frente a “slots online”.

## Asistente

Son **dos cosas distintas** en Anita; la pantalla las junta en una fila por día.

### Qué emula “Resultado online”
Dos programas de **administración central**, no la rendición del cajero:

| Bloque en pantalla | Anita | Qué es |
|---|---|---|
| **Casino online** (asist., apostado, pagado, drop, resultado, % , P. público) | `a-resonline.c` → tablas `resonline` / `resjonline`+`resjolmov` | Resultado del **día del casino online** (juego), cargado en central |
| **Slots online** (coin in/out, pago manual, res. slots, soft) | ventana **slot ON-LINE** → `resulol` | Resultado de **tragamonedas online** |

### Para qué sirve el casino online acá
Alimenta la línea **“On-line” del Anexo I** (`l-anexo1.c`).

En el ERP, el Anexo I hace esto:

1. Arranca el “On-line” desde las **rendiciones de caja** (`venta_online` / `pago_online` del cajero = Del Río ONLINE en el Anexo III).
2. Si en Resultado online hay apostado/pagado/resultado, **pisa** ese valor con el `resultado` del ABM central.

O sea: casino online en esta pantalla es el **corte diario de juego online de central**, no el movimiento de ventanilla.

### Qué no es
No es el flujo de la **rendición** (`efe_venta_online` / `efe_pago_online`). Eso ya entra por cajeros → Anexo III / base del Anexo I.

### En CIP hoy
- `resonline` no existe y `resjonline` está muerto (datos 2014).
- Lo que sí llega de Anita es **`resulol`** (slots).
- Por eso ves solo Res. slots: el bloque casino online **no tiene fuente viva** en CIP; el Anexo I “On-line” en la práctica sale de las rendiciones.

El fallback que armé ayer desde `efectivo` **mezcla los dos flujos** y no es el de `a-resonline`. Conviene **no ejecutarlo** así.

¿Querés que revierta ese fallback y dejemos el import solo con slots (`resulol`) + soft count, que es lo que CIP realmente usa?
Son **dos cosas distintas** en Anita; la pantalla las junta en una fila por día.

### Qué emula “Resultado online”
Dos programas de **administración central**, no la rendición del cajero:

| Bloque en pantalla | Anita | Qué es |
|---|---|---|
| **Casino online** (asist., apostado, pagado, drop, resultado, % , P. público) | `a-resonline.c` → tablas `resonline` / `resjonline`+`resjolmov` | Resultado del **día del casino online** (juego), cargado en central |
| **Slots online** (coin in/out, pago manual, res. slots, soft) | ventana **slot ON-LINE** → `resulol` | Resultado de **tragamonedas online** |

### Para qué sirve el casino online acá
Alimenta la línea **“On-line” del Anexo I** (`l-anexo1.c`).

En el ERP, el Anexo I hace esto:

1. Arranca el “On-line” desde las **rendiciones de caja** (`venta_online` / `pago_online` del cajero = Del Río ONLINE en el Anexo III).
2. Si en Resultado online hay apostado/pagado/resultado, **pisa** ese valor con el `resultado` del ABM central.

O sea: casino online en esta pantalla es el **corte diario de juego online de central**, no el movimiento de ventanilla.

### Qué no es
No es el flujo de la **rendición** (`efe_venta_online` / `efe_pago_online`). Eso ya entra por cajeros → Anexo III / base del Anexo I.

### En CIP hoy
- `resonline` no existe y `resjonline` está muerto (datos 2014).
- Lo que sí llega de Anita es **`resulol`** (slots).
- Por eso ves solo Res. slots: el bloque casino online **no tiene fuente viva** en CIP; el Anexo I “On-line” en la práctica sale de las rendiciones.

El fallback que armé ayer desde `efectivo` **mezcla los dos flujos** y no es el de `a-resonline`. Conviene **no ejecutarlo** así.

¿Querés que revierta ese fallback y dejemos el import solo con slots (`resulol`) + soft count, que es lo que CIP realmente usa?

## Usuario

si, no armemos algo que se dejo de usar porque va a confundir, saca todo el bloque de casino on-line del modulo de resultados on-line, que quede todo como se usa ahora en esta ultima version del anita "C"

## Asistente

Saco el bloque Casino online y dejo solo slots online (Anita C / `resulol`). También revierto el fallback desde `efectivo`.
Saco el bloque Casino online y dejo solo slots online (Anita C / `resulol`). También revierto el fallback desde `efectivo`.

## Asistente

Voy a dejar el módulo solo con slots online (Anita C / `resulol`) y sacar casino de UI, listado e import.
Voy a dejar el módulo solo con slots online (Anita C / `resulol`) y sacar casino de UI, listado e import.

## Asistente

Listo. El módulo quedó alineado a Anita C (slot ON-LINE / `resulol`).

**Sacado**
- Bloque Casino online del form (apostado, pagado, drop, poder público, resultado, %)
- Asistencia y KPIs de casino
- Columnas de casino del listado / QBE / export / gráfico
- Import desde `resonline` / `resjonline` / `efectivo`

**Queda**
- Solo slots: coin in/out, pago manual, cant. slots, res. slots, soft count
- Import: `tesoreria-crown:importar-resultado-online-anita` solo lee `resulol`

El **On-line del Anexo I** sigue saliendo de las rendiciones (`venta_online` / `pago_online`), no de esta pantalla. Las columnas viejas de casino en BD se graban en 0 para no pisar ese flujo.
Listo. El módulo quedó alineado a Anita C (slot ON-LINE / `resulol`).

**Sacado**
- Bloque Casino online del form (apostado, pagado, drop, poder público, resultado, %)
- Asistencia y KPIs de casino
- Columnas de casino del listado / QBE / export / gráfico
- Import desde `resonline` / `resjonline` / `efectivo`

**Queda**
- Solo slots: coin in/out, pago manual, cant. slots, res. slots, soft count
- Import: `tesoreria-crown:importar-resultado-online-anita` solo lee `resulol`

El **On-line del Anexo I** sigue saliendo de las rendiciones (`venta_online` / `pago_online`), no de esta pantalla. Las columnas viejas de casino en BD se graban en 0 para no pisar ese flujo.

## Usuario

alinea a derecha los label del index

## Asistente

Alineo a la derecha los encabezados numéricos del listado para que coincidan con las celdas.
Alineo a la derecha los encabezados numéricos del listado para que coincidan con las celdas.

## Asistente

Listo: en el index, los encabezados numéricos (Coin in/out, Pago man., Slots, Res. slots, etc.) quedan `text-right`, alineados con las celdas. Fecha y Acciones siguen a la izquierda.
Listo: en el index, los encabezados numéricos (Coin in/out, Pago man., Slots, Res. slots, etc.) quedan `text-right`, alineados con las celdas. Fecha y Acciones siguen a la izquierda.

## Usuario

no me gusta como quedo la opcion de mesas directo del menu, deberia abrir los chips de las mesas para elegir, abre directo la mesa 1. Ademas de todas estas opciones desde afuera del desktop, como selecciono la jornada?

## Asistente

Voy a ver cómo entra el menú a mesas y cómo se elige la jornada fuera del desktop.
Voy a ver cómo entra el menú a mesas y cómo se elige la jornada fuera del desktop.

## Asistente

Hoy Mesas del menú elige sola la mesa 1 y la jornada abierta, sin UI para cambiar. Voy a poner chips de mesa y un selector de jornada reutilizable.
Hoy Mesas del menú elige sola la mesa 1 y la jornada abierta, sin UI para cambiar. Voy a poner chips de mesa y un selector de jornada reutilizable.

## Asistente

Voy a sumar el mismo selector de jornada en Slots y Bóveda, que también se abren desde el menú.
Voy a sumar el mismo selector de jornada en Slots y Bóveda, que también se abren desde el menú.

## Asistente

Quedó así:

**Mesas (menú)**  
- Ya no abre la mesa 1 sola.  
- Muestra chips (`Elegir…` + cada mesa).  
- El detalle aparece recién al elegir una.

**Jornada desde fuera del escritorio**  
Antes no había forma: tomaba la abierta (o la última) en silencio.  
Ahora hay barra **Jornada** (fecha → Ir) + Escritorio + Listado, en:
- Mesas  
- Slots  
- Bóveda  

Sin fecha: sigue prefiriendo la abierta. Con fecha: va a esa jornada (o avisa si no existe).

El flujo natural sigue siendo: **Jornadas → abrir el día → escritorio**. El menú de Mesas/Slots/Bóveda es atajo; con la barra de fecha podés cambiar el día sin volver al listado.
Quedó así:

**Mesas (menú)**  
- Ya no abre la mesa 1 sola.  
- Muestra chips (`Elegir…` + cada mesa).  
- El detalle aparece recién al elegir una.

**Jornada desde fuera del escritorio**  
Antes no había forma: tomaba la abierta (o la última) en silencio.  
Ahora hay barra **Jornada** (fecha → Ir) + Escritorio + Listado, en:
- Mesas  
- Slots  
- Bóveda  

Sin fecha: sigue prefiriendo la abierta. Con fecha: va a esa jornada (o avisa si no existe).

El flujo natural sigue siendo: **Jornadas → abrir el día → escritorio**. El menú de Mesas/Slots/Bóveda es atajo; con la barra de fecha podés cambiar el día sin volver al listado.

## Usuario

slots desde menu deberia mostrar el dia directamente sin saltar al escritorio

## Asistente

Voy a hacer que Slots desde el menú muestre el día completo, sin pasar por el escritorio.
Voy a hacer que Slots desde el menú muestre el día completo, sin pasar por el escritorio.

## Asistente

Listo. **Slots** desde el menú muestra el día completo (resumen, búsqueda, listado y detalle), sin pasar por el escritorio.

La barra de jornada/sala sigue arriba; el botón Escritorio queda solo como atajo opcional.

[REDACTED]
Listo. **Slots** desde el menú muestra el día completo (resumen, búsqueda, listado y detalle), sin pasar por el escritorio.

La barra de jornada/sala sigue arriba; el botón Escritorio queda solo como atajo opcional.

[REDACTED]

## Usuario

la idea es que estas opciones externas al escritorio trabajen independiente con opcion de saltar al escritorio como hiciste en mesas, no te parece correcto plantear el flujo asi?

## Asistente

Sí: ese es el flujo correcto.

**Escritorio** = día completo (cajeros, mesas, bóveda, slots, anexos…).  
**Menú externo** = atajo a un módulo, autónomo: sala + jornada + trabajo del día, sin obligar a pasar por el escritorio. **Escritorio** solo como salto opcional.

Eso ya encaja con Mesas (chips + jornada + detalle) y con Slots (día en la misma pantalla). Bóveda y el resto deberían seguir la misma regla: no “vaciar” la pantalla con un botón que mande al escritorio.

¿Querés que alinee Bóveda (y lo que falte del menú Crown) a ese mismo patrón?
Sí: ese es el flujo correcto.

**Escritorio** = día completo (cajeros, mesas, bóveda, slots, anexos…).  
**Menú externo** = atajo a un módulo, autónomo: sala + jornada + trabajo del día, sin obligar a pasar por el escritorio. **Escritorio** solo como salto opcional.

Eso ya encaja con Mesas (chips + jornada + detalle) y con Slots (día en la misma pantalla). Bóveda y el resto deberían seguir la misma regla: no “vaciar” la pantalla con un botón que mande al escritorio.

¿Querés que alinee Bóveda (y lo que falte del menú Crown) a ese mismo patrón?

## Usuario

si

## Asistente

Voy a inventariar las pantallas Crown del menú y alinearlas al mismo patrón (autónomas + salto al escritorio).
Voy a inventariar las pantallas Crown del menú y alinearlas al mismo patrón (autónomas + salto al escritorio).

## Usuario

<available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools> <available_subagent_types> Available subagent_types and a quick description of what they do: - generalPurpose: General-purpose agent for researching complex questions, searching for code, and executing multi-step tasks. Use when searching for a keyword or file and not confident you'll find the match quickly. - explore: Fast agent specialized for exploring codebases. Use this when you need to quickly find files by patterns (eg. "src/components/**/*.tsx"), search code for keywords (eg. "API endpoints"), or answer questions about the codebase (eg. "how do API endpoints work?"). When calling this agent, specify the desired thoroughness level: "quick" for basic searches, "medium" for moderate exploration, or "very thorough" for comprehensive analysis across multiple locations and naming conventions. - cursor-guide: Read Cursor product documentation to answer questions about how Cursor Desktop, IDE, CLI, Cloud Agents, Bugbot, and other features work. Use when the user asks 'In Cursor, how do I...?' or similar questions about Cursor products. - ci-investigator: Investigate a single failing PR CI check and return a short root-cause summary. Use when the user asks to summarize, explain, diagnose, or investigate a specific failed check from a pull request. - bugbot: Use only when the user *explicitly* asks for a Bugbot-like review of local code changes. When launching this subagent, set the Task description to exactly "Bugbot". Launch exactly one Bugbot subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\", \"natural language\">\nChange Description: ...\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Change Description` only when `Diff` is `natural language`, formatting it as one block per changed file (a `<path> (added|modified|deleted|renamed)` header followed by bullets of what changed, mentioning line numbers or ranges inline where helpful), and only use `natural language` as a last resort after a regular diff-based review failed because the diff could not be computed; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - security-review: Use only when the user *explicitly* asks for a security review of local code changes. When launching this subagent, set the Task description to exactly "Security Review". Launch exactly one security-review subagent with `run_in_background: false` unless the user explicitly asks to run in background. Use this fixed prompt form: "Full Repository Path: ...\nDiff: <one of: \"branch changes\", \"uncommitted changes\">\nCustom Instructions: ..."; default to `Diff: branch changes`; include `Custom Instructions` only when the user gave specific review instructions. This subagent is single-shot and does not support `resume`; always launch a fresh subagent instead. - best-of-n-runner: Run a task in an isolated git worktree. Each best-of-n-runner gets its own branch and working directory. Use for best-of-N parallel attempts or isolated experiments. </available_subagent_types> <available_subagent_models> If the user explicitly asks for the model of a subagent/task, you may ONLY use model slugs from this list: - inherit (default; required unless the user explicitly requested another model) - claude-fable-5-1-thinking-high - claude-opus-5-5-medium - claude-opus-5-thinking-high - claude-sonnet-5-5-high - cursor-grok-4.6-high-fast - gemini-4-argon-medium - gpt-5.6-sol-medium - grok-4.7-high-fast - muse-wonder-1.0-high If the user isn't asking for a specific version, prefer the latest version of the model family. As an example, if the user just says "gpt" or "claude", use the latest available version of GPT or Claude. IMPORTANT: If the user requests a model that is NOT in the list above, do NOT substitute a different model or guess. Instead, skip launching the subagent with that model and tell the user which model was unavailable and which models are available. When speaking to the USER about which model you selected for a subagent, do NOT use the kebab-case model names unless the user requested the model using that format. Ue the same naming scheme the user used to discuss the model when they requested it. </available_subagent_models> <dynamic_tools> You have access to tools through dynamic namespaces, e.g. MCP servers, using `GetDynamicTools` and `CallDynamicTool`. ## Dynamic Tool Discovery and Invocation Use `GetDynamicTools` to discover tool schemas, then `CallDynamicTool` to invoke one tool. Aim to minimize round-trips: ideally one discovery call followed by one invocation. If the user mentions a product or service represented by an available namespace, and the request likely depends on it, proactively inspect that namespace before answering. If you are unsure which namespace matches, search with a relevant pattern. `GetDynamicTools` supports these modes: 1. `{"namespace":"<id>"}`: returns schemas and full descriptions for every tool in that namespace. 2. `{"namespace":"<id>","toolName":"<name>"}`: returns one tool schema with its full description. 3. `{"pattern":"<regex>"}`: searches namespace and tool names. 4. `{"namespace":"<id>","pattern":"<regex>"}`: searches tools within one namespace. 5. No arguments: returns the full catalog. Pattern-search and catalog results shorten long descriptions, marked by a trailing "... [truncated]"; namespace and single-tool lookups always return the complete description. Always inspect a tool's schema before invoking it with `CallDynamicTool`. If the available dynamic tools do not fully support what the user asked you to do, complete the work you can with the current tool set. In your work summary, include what you were unable to do and why. Do not use browser automation to work around missing tools unless the user explicitly asks you to use the browser. Available dynamic tool namespaces: <dynamic_tool_namespaces> <namespace name="cursor-ide-browser" tools="browser_navigate, browser_snapshot, browser_click, browser_mouse_click_xy, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, browser_drag, browser_get_bounding_box, browser_highlight, browser_tabs, browser_cdp, browser_take_screenshot, browser_lock" namespaceUseInstructions="The cursor-ide-browser MCP server provides a Cursor-owned browser tab plus a raw Chrome DevTools Protocol command tool. CORE WORKFLOW: 1. Start by understanding the user's goal and what success looks like on the page. 2. Use browser_tabs with action &quot;list&quot; to inspect open tabs and URLs before acting. 3. Use browser_navigate to create or navigate the target tab. Omit the position parameter for background automation so focus is preserved. 4. Use browser_lock before longer automation on an existing tab, then browser_lock with action &quot;unlock&quot; when finished. 5. Use browser_snapshot for accessibility context and browser_take_screenshot for visual verification. 6. Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for page interactions. 7. Use browser_highlight and browser_get_bounding_box for visual grounding and coordinate diagnostics. 8. Use browser_cdp for page inspection, profiling, runtime evaluation, DOM/CSS queries, and performance data. AVOID RABBIT HOLES: 1. Do not repeat the same failing action more than once without new evidence such as a fresh snapshot, a different ref, a changed page state, or a clear new hypothesis. 2. IMPORTANT: If four attempts fail or progress stalls, stop acting and report what you observed, what blocked progress, and the most likely next step. 3. Prefer gathering evidence over brute force. If the page is confusing, use browser_snapshot, browser_take_screenshot, or CDP inspection before trying more actions. 4. If you encounter a blocker such as login, passkey/manual user interaction, permissions, captchas, destructive confirmations, missing data, or an unexpected state, stop and report it instead of improvising repeated actions. 5. Do not get stuck in wait-action-wait loops. Every retry should be justified by something newly observed. CRITICAL - Lock/unlock workflow: 1. browser_lock requires an existing browser tab - you CANNOT call browser_lock with action: &quot;lock&quot; before browser_navigate 2. Correct order: browser_navigate -&gt; browser_lock({ action: &quot;lock&quot; }) -&gt; (interactions) -&gt; browser_lock({ action: &quot;unlock&quot; }) 3. If a browser tab already exists (check with browser_tabs list), call browser_lock with action: &quot;lock&quot; FIRST before any interactions 4. Only call browser_lock with action: &quot;unlock&quot; when completely done with ALL browser operations for this turn IMPORTANT - Waiting strategy: When waiting for page changes, prefer short CDP polling loops with Runtime.evaluate, DOM queries, Page lifecycle signals, or browser_snapshot checks rather than a single long wait. CDP USAGE: - Use browser_cdp with a DevTools Protocol method and params object, for example Runtime.evaluate, DOM.getDocument, CSS.getComputedStyleForNode, Profiler.start/stop, Performance.getMetrics, Log.enable, and Network.enable. - Do not use browser_cdp with CDP Input.* methods. They are denied because they are focus-sensitive in Electron webviews and can route input to Cursor UI instead of the browser page. - Use browser_click, browser_type, browser_fill, browser_select_option, browser_press_key, browser_scroll, and browser_drag for clicks, typing, filling inputs, selecting options, keyboard actions, scrolling, and drag-and-drop. - Use Runtime.evaluate for advanced DOM-scoped interactions that the dedicated browser tools do not cover. - For profiling, call Profiler.enable, Profiler.start, reproduce the behavior, then Profiler.stop. The profile is saved to a file and returned as a log_file; read that file only when you need to inspect details. - For JavaScript evaluation, prefer Runtime.evaluate with returnByValue when possible. - Device emulation you set with Emulation.setDeviceMetricsOverride, Emulation.setTouchEmulationEnabled, or Emulation.setUserAgentOverride (for example for a phone-sized screenshot) lasts only for the current turn: it is cleared when your turn ends, and the user can reset it from the browser tab at any time. Send Emulation.clearDeviceMetricsOverride yourself once you are done with it within a turn. - Some browser-wide or sensitive CDP methods are denied, especially cookie, storage, permission, download, target-management, filesystem-backed file-input commands, system-level commands, and CDP navigation/history navigation commands. - Large CDP responses are saved to files instead of being inlined. Prefer using the returned file path over immediately stuffing large payloads into context; read focused sections only when needed. VISION: - browser_take_screenshot attaches an image result that the model can inspect. CDP Page.captureScreenshot returns data inside JSON and should not replace browser_take_screenshot when visual verification is needed. NOTES: - browser_snapshot returns snapshot YAML and is the main source of truth for page structure. - Refs are opaque handles tied to the latest browser_snapshot for that tab. - Iframe content is not accessible - only elements outside iframes can be interacted with. - When you stop to report a blocker, include the current page, the target you were trying to reach, the blocker you observed, and the best next action. If the blocker requires manual user interaction, ask the user to take over at that point rather than assuming it in advance." source="mcp" /> <namespace name="cursor" tools="AwaitShell, CreateGoal, Delete, EditNotebook, FetchMcpResource, GenerateImage, ReadLints, SwitchMode, Task, TodoWrite, UpdateGoal, WebFetch, WebSearch" namespaceUseInstructions="Native Cursor tools for this session. These are highly recommended and useful tools that you should use when the right situation arises. Don't be afraid to look at one if it seems relevant, even if you don't end up using it. You MUST read the tool schemas before calling them. Here are some crucial instructions: - AwaitShell: Use to sleep and check shell progress. Never sleep using shell. - ReadLints: Check for linter errors after substantive edits. - SwitchMode: Switch between modes: Agent and Plan. Proactively consider switching modes for relevant requests. - Task: Spawn local and cloud subagents natively. - TodoWrite: Use this tool to manage complex multi-step tasks." source="cursor" /> </dynamic_tool_namespaces> If an MCP-backed namespace requires authentication, call `mcp_auth` through `CallDynamicTool` for that namespace, then inspect it again and retry if appropriate. Do not authenticate namespaces preemptively or repeatedly. </dynamic_tools>

## Usuario

si

## Asistente

Empiezo por Bóveda/Arqueo/Kardex (sacar `desk_nav`) y luego Poder y Anexos con el día en la misma pantalla.
Empiezo por Bóveda/Arqueo/Kardex (sacar `desk_nav`) y luego Poder y Anexos con el día en la misma pantalla.

## Asistente

Actualizo Poder y Anexos para mostrar el día en pantalla, y hago que los partials acepten URLs propias del menú.
Actualizo Poder y Anexos para mostrar el día en pantalla, y hago que los partials acepten URLs propias del menú.

## Asistente

Listo. El menú Crown queda autónomo con selector de jornada y salto a Escritorio:

- **Bóveda / Arqueo / Kardex**: sin `desk_nav`; selector de jornada (arqueo) y botón Escritorio.
- **Poder del público**: muestra el stock de fichas del día en la misma pantalla.
- **Anexos**: I–VIII y Res. cajeros en la misma URL del menú (tabs y formatos sin saltar al escritorio).

Mesas y Slots ya estaban alineados. Bingo / habilitaciones siguen como listados propios.
Listo. El menú Crown queda autónomo con selector de jornada y salto a Escritorio:

- **Bóveda / Arqueo / Kardex**: sin `desk_nav`; selector de jornada (arqueo) y botón Escritorio.
- **Poder del público**: muestra el stock de fichas del día en la misma pantalla.
- **Anexos**: I–VIII y Res. cajeros en la misma URL del menú (tabs y formatos sin saltar al escritorio).

Mesas y Slots ya estaban alineados. Bingo / habilitaciones siguen como listados propios.
