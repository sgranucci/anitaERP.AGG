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

## F. MCP (opcional)

| # | Caso | Resultado esperado |
|---|------|--------------------|
| F1 | tools/list + Bearer | 200 |
| F2 | Token malo | 401 |
| F3 | call NL | ok + intent |

## Orden sugerido

1. **A1–A2** (FC ok) → **A3–A7** (ND/NC) → **A8** UI  
2. **B2** portal NC  
3. **C1–C4** mail (FC + NC + ND) → C5–C8  
4. **D** → **E** → **F**

## Criterio de cierre de esta tanda

- [ ] Preview NC/ND muestra tipo solicitado correcto y abreviatura C*/D*
- [ ] A7 paridad API vs IA en al menos un NC y un ND reales
- [ ] Portal card mail visible; ≥1 precarga origen Mail
- [ ] Inbox personal no procesa basura
