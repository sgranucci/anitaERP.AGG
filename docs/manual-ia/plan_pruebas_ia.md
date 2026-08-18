# Plan de pruebas — Plataforma IA / Document AI

Fecha: incluye soporte **FC / ND / NC** en el pipeline PDF+IA (listaConcepto por tipo genérico + CC).
Precondición: facturas (FC) por modal ya validadas.

## A. Conceptos, tipos FC / ND / NC

Objetivo: el agente interno usa las mismas reglas que Facturas_scan `listaConcepto` para **FC, ND y NC**.

| # | Caso | Pasos | Resultado esperado |
|---|------|-------|--------------------|
| A1 | FC + CC | PDF factura + OC con CC conocido | Preview: `tipo_solicitado=FC`, abreviatura F* (ej. FIA/FGA); conceptos matcheados |
| A2 | Paridad API FC | Misma OC: `GET …/tipo-comprobante/FC/conceptos` vs preview FC | Mismos id_concepto / nombres |
| A3 | **NC** por texto | PDF «Nota de Crédito» + OC | `tipo_solicitado=NC`, abreviatura C* (CGA/CIA…); catálogo NC |
| A4 | **ND** por texto | PDF «Nota de Débito» + OC | `tipo_solicitado=ND`, abreviatura D* |
| A5 | **NC** por código AFIP | PDF con COD. 003 / 008 / 013 | Detecta NC (heurística o LLM) |
| A6 | **ND** por código AFIP | PDF con COD. 002 / 007 / 012 | Detecta ND |
| A7 | Paridad API NC/ND | Misma OC: API `…/NC/conceptos` o `…/ND/conceptos` vs preview | Misma lista de conceptos que el tipo solicitado |
| A8 | UI preview | Modal/portal | Muestra etiqueta «Nota de crédito → Cxx» (o débito) |

## B. Portal — scan directo (smoke)

| # | Caso | Resultado esperado |
|---|------|--------------------|
| B1 | Presentar PDF FC | Precarga origen Portal, tipo contable F* |
| B2 | Presentar PDF NC | Precarga con tipo C*; no forzar FC |
| B3 | PDF sin OC → OC manual | Resolver y confirmar |
| B4 | Proveedor PDF ≠ seleccionado | Error, no graba |

## C. Portal — canal mail

Casilla `PRECARGA_MAIL_USUARIO` · label `PRECARGA_MAIL_CARPETA`.

| # | Caso | Pasos | Resultado esperado |
|---|------|-------|--------------------|
| C1 | UI portal | Mail ON | Card Canal 2 con casilla + mailto |
| C2 | Mail FC | Asunto `Factura OC ######` + PDF; label OK | Precarga origen **Mail**, tipo FC→F* |
| C3 | Mail NC | Asunto `Nota de crédito OC ######` + PDF NC | Precarga Mail, `tipo_solicitado=NC` |
| C4 | Mail ND | Igual con ND | `tipo_solicitado=ND` |
| C5 | Basura | Presupuesto sin factura/OC | No encola / omitido_filtro |
| C6 | Dry-run | `compras:ingestar-facturas-mail --dry-run` | Detalle coherente |
| C7 | Auto-aplicar FC alta | Score ≥ 0.92, CAE, OC PDF | `auto_aplicada` |
| C8 | Score bajo / sin CAE | — | Para revisar; no auto_aplicada |

## D. HITL / Gobernanza

| # | Caso | Resultado esperado |
|---|------|--------------------|
| D1 | Evento pendiente | Visto → Resuelto cierra |
| D2 | KPIs post C7/C8 | auto_aplicada vs editada visibles |

## E. Panel IA / permisos / RAG

| # | Caso | Resultado esperado |
|---|------|--------------------|
| E1 | Sin `ejecutar-consulta-ia` | Sin FAB |
| E2 | Sin `consulta-ia-contable` | Sin mayor/asiento |
| E3 | «cómo cargo una OC» | consultar_manual con citas |
| E4 | Manual IA | `configuracion/manual-ia` OK |
| E5 | Roles Contaduría/Impuestos/Logística | FAB visible (`ejecutar-consulta-ia`); Impuestos también `consulta-ia-contable` |
| E6 | Mayor + CC | «mayor cuenta {cta} CC {cc} este mes» | Tabla filtrada por `centrocosto_id` |
| E7 | Mayor OC | «mayor de la OC {nro}» | Movimientos de asientos/CP vinculados a la OC |
| E8 | Mayor multi | cuenta + empresa + rango fechas | Totales del filtro completo |
| E9 | Enc-compras | FAB visible; Op-Compras sin FAB |
| E10 | KPI compras | «resumen operativo de compras» | Tabla KPI OC/RQ/lead time |
| E11 | OC vencidas | «OC vencidas sin recepción» | Listado APROBADA vencida sin recepción |
| E12 | RQ sin OC | «requisiciones sin OC» | RQ activas con líneas pendientes |

## F. MCP (opcional)

| # | Caso | Resultado esperado |
|---|------|--------------------|
| F1 | tools/list + Bearer | 200 |
| F2 | Token malo | 401 |
| F3 | call NL | ok + intent |

## G. Pedido por consumo (skill ejemplo)

| # | Caso | Pasos | Resultado esperado |
|---|------|-------|--------------------|
| G1 | Sin depósito | «pedido consumo CC 93 últimos 60 días» | Pide aclaración: depósito obligatorio |
| G2 | Proyección 60 días | «pedido consumo CC {codigo} depósito {id} últimos 60 días» | Tabla SKU/consumo/stock/pedir/doc; borradores compra y/o sala |
| G3 | Split documentos | Misma consulta con stock origen en algunos SKU | Líneas `sala` vs `compra` coherentes |
| G4 | Confirmar HITL | Botón Crear RQ compra/sala | Crea documento; `ai_decision` → confirmada (si hay permiso) |
| G5 | Hooks roadmap | Params `solo_sabados` / `multiplicador_evento` | Reflejados en `_meta` y párrafos |

## Orden sugerido

1. **A1–A2** (FC ok) → **A3–A7** (ND/NC) → **A8** UI  
2. **B2** portal NC  
3. **C1–C4** mail (FC + NC + ND) → C5–C8  
4. **D** → **E** → **F** → **G** (pedido consumo)

## Criterio de cierre de esta tanda

- [ ] Preview NC/ND muestra tipo solicitado correcto y abreviatura C*/D*
- [ ] A7 paridad API vs IA en al menos un NC y un ND reales
- [ ] Portal card mail visible; ≥1 precarga origen Mail
- [ ] Inbox personal no procesa basura
- [x] G2 proyección pedido consumo con CC + depósito reales

## Corte de verificación — 16/08/2026

- **A3**: NC real confirmada por el pipeline (`docu_0000361656.pdf`) y persistida como precarga 233, tipo `CIB`, OC 221022, origen `PORTAL`.
- **A4**: detección ND validada con entradas de texto; falta un PDF ND real disponible para cerrar el caso integral.
- **A5–A6**: pasaron los códigos AFIP NC 003/008/013 y ND 002/007/012.
- **A7**: paridad exacta API/agente para OC 221022: `FIB` (15 conceptos), `CIB` (13) y `DIB` (13).
- **A8/B2**: el preview compone «Nota de crédito/débito → C*/D*»; existe NC real del portal.
- **C1–C8 bloqueado (16/08 noche)**: casilla apuntada a `AnitaERP@grupoagg.com` (misma de SMTP; `PRECARGA_MAIL_PASSWORD` vacío → `MAIL_PASSWORD`). Dry-run falló con `NO AUTHENTICATE failed` en IMAP `outlook.office365.com:993`. Schedule sigue con `PRECARGA_MAIL_INGESTA_HABILITADA=false`. No hay precargas `MAIL`.
- **D**: existen 22 eventos pendientes y 1 resuelto. KPIs visibles: 862 decisiones, 840 pendientes y 11 errores. No se cambiaron estados durante esta verificación.
- **E**: router y grounding validados para manual, mayor cuenta+CC, mayor por OC, KPIs, OC vencidas y RQ sin OC. Los mayores no tuvieron movimientos en el rango consultado. Rutas del manual presentes y vistas Blade compiladas.
- **Permisos**: FAB para administrador, Compras, Contaduría, Impuestos y Logística; acceso contable solo para administrador, Contaduría e Impuestos.
- **F1–F3**: endpoints MCP validados en controller: token válido → HTTP 200 con 9 tools; token inválido → HTTP 401. Con autorización del operador, F3 ejecutó `consultar_contexto_operativo_nl` («cómo cargo una OC»), resolvió `consultar_manual` y creó exactamente una decisión (`ai_decision.id=871`), sin documentos operativos.
- **G1/G2/G3/G5**: depósito obligatorio validado; proyección real CC 85/depósito 8 generó 80 líneas de compra. Con depósito origen 1862, el split dio 75 líneas compra + 5 sala. Hooks de evento/sábados/lead time/buffer reflejados en `_meta`.
- **Escrituras no ejecutadas**: el operador decidió dejar pendiente D1 (evento 2: 40 anomalías altas de conciliación) y no crear la requisición real de G4.
