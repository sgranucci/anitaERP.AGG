# Conciliación bancaria — manual de usuario

> **Versión con mockups (canónica):** Centro de ayuda → Contable — Conciliación bancaria  
> URL: `/contable/manual-conciliacion-bancaria` · PDF/Word vía botones del manual  
> Regenerar: `php artisan manual:generar-mockups conciliacion-bancaria` y `php docs/manual-conciliacion-bancaria/generar.php`  
> Este Markdown queda como borrador técnico; el operador usa el manual HTML del ERP.

Manual de operación del módulo **Conciliación bancaria** en anitaERP. Está pensado para Contaduría y Tesorería: cruza el mayor (Anita: subdiario + ctamov) contra el extracto bancario (Interbanking o archivo Excel/CSV/OFX), arma la carátula del mes, permite conciliar a mano con multi-match, cerrar y aprobar el período, y generar el egreso de gastos bancarios.

El modelo es el de Argentina: **Interbanking + archivo de extracto**. No hay feed open-banking europeo.

---

## 1. Qué hace el módulo

1. Lee el **mayor analítico** de la cuenta contable ligada a la cuenta de caja (subdiario y ctamov).
2. Lee los movimientos del **banco** (Interbanking persistido, o extracto importado como `EXT-{código}`).
3. **Empareja** automático (1:1 y, si está activo, multi N:1 / 1:N) y deja pendientes.
4. Arma la **carátula** Contaduría: saldo banco, cheques no acreditados, pendientes, saldo contable y diferencia.
5. Permite **workspace manual** (dos columnas) para terminar el mes.
6. Permite **cerrar → aprobar** el período.
7. Codifica gastos del extracto y puede **generar egreso + ICO** de gastos bancarios.

Los emparejamientos quedan guardados (pares 1:1 y grupos multi) y no se vuelven a proponer en meses siguientes.

---

## 2. Dónde está en el menú

| Pantalla | Ruta típica | Para qué |
|---|---|---|
| Conciliación bancaria | Contable → Conciliación bancaria | Consulta del mes, carátula, solapas, Excel Contaduría |
| Conciliación manual | Desde el index (botón Manual) | Workspace de cruce diario |
| Tablero | Contable → Tablero conciliación bancaria | Avance multi-cuenta / empresa |
| Aging | Contable → Aging conciliación bancaria | Pendientes por antigüedad |
| Configuración / reglas | Configuración → por módulo → Contable → Conciliación bancaria (reglas) | Mapas y parámetros editables |

Atajos desde el index: **Tablero**, **Aging**, **Reglas**, **Manual**, **Excel**.

---

## 3. Permisos

| Permiso | Quién lo usa |
|---|---|
| Listar conciliación bancaria | Ver index, consultar, workspace manual |
| Listar aging / tablero | Ver aging y tablero (+ export) |
| Editar / actualizar configuración | Pantalla de reglas y mapas |
| Aprobar conciliación bancaria | Botón **Aprobar** tras el cierre |
| Alinear Excel Contaduría | Botón **Alinear Excel Contaduría** en el index |

Roles habituales con menú: administrador, Enc-contaduría, Ger-administracion, Enc-admin.

---

## 4. Antes de empezar (checklist)

1. **Cuenta de caja** con cuenta contable y, si hay Interbanking, cuenta IB enganchada.
2. Sin IB: se puede trabajar igual con **Importar extracto** (Excel Contaduría, CSV u OFX); el sistema usa cuenta `EXT-{código}`.
3. Mayor del período disponible en Anita (bridge).
4. Período del mes a conciliar (mes / año).
5. (Opcional) Excel de Contaduría del mes anterior para semilla de carátula / alineación.

---

## 5. Circuito mensual recomendado

```
1. Index → empresa + cuenta + mes → Conciliar (corrida automática)
2. Revisar carátula y diferencia
3. Manual → terminar pendientes (traer IB / importar extracto / multi-match)
4. Cerrar período
5. Contaduría aprueba
6. (Si corresponde) generar gastos bancarios desde el index
7. Tablero / Aging para control multi-cuenta
```

Orden práctico Contaduría:

1. Corrida del mes en el **index**.
2. Revisar solapas (cheques, cruce IB, gastos).
3. Entrar a **Manual** para lo que no cerró solo.
4. **Cerrar** y pedir **Aprobar**.
5. Exportar Excel de respaldo.

---

## 6. Pantalla principal (index)

### 6.1 Filtros

- **Empresa** (asignadas al usuario).
- **Cuenta de caja**: código + F1 / lupa (no usar ID a mano).
- **Mes / año**.
- Botón **Conciliar**.

Al elegir cuenta se muestra el **enganche**: si tiene Interbanking, si falta extracto, o si hay que importar archivo.

### 6.2 Resumen / carátula

Tras consultar aparece:

| Concepto | Significado |
|---|---|
| Saldo banco (extracto) | Último saldo del extracto del período |
| Cheques no acreditados | Cheques propios con F.Dev en el mes (carátula) |
| Pendientes banco | Movimientos banco sin match que entran a carátula |
| Saldo banco ajustado | Banco ± ajustes de carátula |
| Saldo contable | Del mayor de la cuenta |
| Diferencia | Debe tender a 0 (o a la tolerancia de Contaduría) |

También: pares nuevos de la corrida, pendientes cpromae, ANULA, mayor sin match, pendientes banco.

### 6.3 Solapas del resultado

| Solapa | Contenido |
|---|---|
| Pendientes cheques | Cheques propios (cpromae) + ANULA ya conciliadas en banco |
| Cruce cheques IB | Débitos CH* del extracto vs cheques ERP (código 9) |
| Pendientes otros | Movimientos de mayor sin match (no cheque) |
| Saldo banco (codificado) | Extracto con código Contaduría (2…10 / AC) |
| Pendientes banco | Banco sin match |
| Gastos bancarios | Resumen por código + generación de egreso/ICO |

### 6.4 Alinear Excel Contaduría

Con permiso **alinear**:

1. Elegir empresa, cuenta y período.
2. **Alinear Excel Contaduría** → elegir `.xlsx` / `.xls`.
3. Cancelar en el diálogo = **solo dry-run** (análisis).
4. Aceptar = **aplicar** (persiste pares / import extracto según el análisis).

Sirve para alinear el stock de Contaduría (solapa Saldo / pendientes) con lo del ERP.

### 6.5 Export

Con resultado en pantalla: **Excel** de la conciliación del período (filtros activos).

---

## 7. Workspace manual

Entrada: index → **Manual** (con empresa, cuenta y período ya elegidos).

### 7.1 Modos

| Modo | Enfoque |
|---|---|
| Contaduría | Carátula, cheques, cierre formal, vista de Contaduría |
| Tesorería | Operación diaria, traer banco destacado, pendientes del día |

El modo se **guarda por empresa**.

### 7.2 Barra de herramientas

| Acción | Qué hace |
|---|---|
| Fecha / texto / importe | Filtran las grillas |
| Solo pendientes | Oculta ya conciliados |
| Incluir anteriores | Trae pendientes de meses previos |
| Traer del banco | Baja IB del día de la fecha filtro |
| Importar extracto | Excel / CSV / OFX / QFX → cuenta EXT si no hay IB |
| Sugerir par (S) | Marca un candidato por importe |
| Conciliar (Enter) | Confirma selección (1:1 o multi) |
| Limpiar (Esc) | Saca la selección |
| Cerrar período | Congela el mes (snapshot) |
| Aprobar | Solo con permiso; tras cerrado |
| Reabrir | Vuelve a borrador (si el circuito lo permite) |
| Excel | Export del workspace |

### 7.3 Chips de tipo

Todos · Cheques · Transferencias · Depósitos · Gastos / débitos.

### 7.4 Totales vivos

Barra con suma de selección anitaERP, selección banco y diferencia. En multi-match la diferencia debe quedar dentro de la **tolerancia de importe** configurada.

### 7.5 Cómo conciliar a mano

**1:1**

1. Clic en un movimiento de mayor (izquierda).
2. Clic en el del banco (derecha) del mismo importe (o sugerir con **S**).
3. **Conciliar** o Enter.

**Multi (N:1 / 1:N / N:N)**

1. Marcar varios de un lado y uno o varios del otro.
2. La barra muestra si la suma cierra.
3. **Conciliar** graba un **grupo** (no un par suelto).

**Desconciliar:** desde el detalle del par/grupo ya conciliado (acción de deshacer en el panel).

### 7.6 Atajos de teclado

| Tecla | Acción |
|---|---|
| Enter | Conciliar selección |
| S | Sugerir par |
| Esc | Limpiar selección |

### 7.7 Bancos sin Interbanking

1. **Importar extracto** con el archivo del banco o el Excel Contaduría.
2. El enganche indica cuenta `EXT-…`.
3. Seguir conciliando igual; “Traer del banco” no aplica si no hay IB.

Formatos: `.xlsx`, `.xls`, `.csv`, `.txt`, `.ofx`, `.qfx`.

---

## 8. Cierre y aprobación

Estados del período (por empresa + cuenta + mes):

| Estado | Significado |
|---|---|
| Borrador / abierto | Se puede conciliar y traer banco |
| Cerrado | Snapshot de cierre; Contaduría revisa |
| Aprobado | Cierre formal aceptado |

Flujo:

1. Terminar pendientes relevantes.
2. **Cerrar período**.
3. Quien tenga permiso **Aprobar**.
4. Si hace falta corregir: **Reabrir** (vuelve a editable).

El cierre no borra pares ni grupos; deja constancia de quién cerró/aprobó.

---

## 9. Tablero de avance

**Contable → Tablero conciliación bancaria**

- Empresas (checkboxes de asignadas).
- Mes / año → Consultar.
- Por cuenta: extracto sí/no, pares del mes, grupos multi, hashes C/B, estado (sin corrida / borrador / cerrado / aprobado).
- Accesos a Manual y Aging.
- Export **Pdf / Excel / Csv**.

Uso típico: gerencia o Enc-contaduría al cierre de mes, multi-empresa.

---

## 10. Aging de pendientes

**Contable → Aging conciliación bancaria**

1. Empresa + cuenta de caja (F1) + período → Consultar.
2. Buckets: 0–7, 8–30, 31–60, 61+ días; total e |importe|.
3. Detalle por lado (contable / banco), fecha, días, bucket, detalle, importe.
4. Export **Pdf / Excel / Csv**.

Sirve para cheques viejos, transferencias sin match y excepciones que frenan la diferencia.

---

## 11. Gastos bancarios (egreso + ICO)

En el index, solapa **Gastos bancarios**:

- Agrupa débitos del extracto por **código de codificación** (2…8; no 9 ni 10).
- Con la configuración de **Doc. gastos**, resuelve cuenta contable o concepto IVA compras.
- Si está “listo”, se puede generar el documento (EGR + ICO según abreviaturas de reglas).

Si falta cuenta o concepto, el panel lista los faltantes: corregir en **Configuración → Doc. gastos** (códigos de cuenta / concepto IVA).

---

## 12. Configuración (sin intervención de sistemas)

**Configuración → Configuración por módulo → Contable → Conciliación bancaria (reglas)**  
o atajo **Reglas** desde el index.

Un solo **Guardar** aplica todas las solapas.

### 12.1 Reglas

Tolerancias de importe y fechas, lookback histórico, anomalías, multi-match automático (sí/no, máx. miembros, ventana días), abreviaturas **EGR** / **ICO**, códigos default de transferencia y gasto.

### 12.2 P.C.C.

`grouping_code_ib` → código Contaduría.

- Sin patrones = mapa directo.
- Con patrones (separados por `|`) = regla ambigua (concepto o descripción).
- Código **AC** = acumulado diario.

### 12.3 Conceptos IB

`code_description_ib` → código cuando el P.C.C. no alcanza. Marcar **Acumulado** para sellos / IVA 10,5 % / intereses acuerdo.

### 12.4 Cód. operación

`operation_code_ib` → código (capa de respaldo).

### 12.5 Patrones gasto

Capa final por texto (concepto + descripción). También define las **etiquetas** de código 2…10 usadas en las otras solapas.

### 12.6 Tipo comp.

Compatibilidad tipo de comprobante del mayor (OPP, OPA, TRF, ING, EGR, COB…) con patrones del extracto. Ayuda al matcher a no cruzar tipos incompatibles.

### 12.7 Doc. gastos

Por cada código de gasto (2…8):

- **Concepto IVA compras** (manda si está), o
- **Cuenta contable** (código del plan).

Lupa / F1 para buscar cuenta y concepto. Los tipos EGR/ICO están en Reglas.

---

## 13. Códigos Contaduría (referencia)

| Código | Uso habitual |
|---|---|
| 2 | Impuesto al débito/crédito |
| 3 | IIBB retención (SIRCREB / AGIP) |
| 4 | IIBB percepción (PBS/BSAS) |
| 5 | IVA 21 % |
| 6 | IVA percepción 3ros |
| 7 | Comisiones / intereses / sellos |
| 8 | Retención IVA |
| 9 | Cheques |
| 10 | Transferencias / pagos / depósitos |
| AC | Acumulado diario (no entra al egreso de gastos) |

La lista exacta y los patrones se editan en **Patrones gasto** y en los mapas P.C.C. / Conceptos.

---

## 14. Corrida automática: qué hace el matcher

Orden típico:

1. Overrides del Excel Contaduría (solapa Saldo), si hubo alineación.
2. Emparejamientos 1:1 por referencia / cheque / importe+fecha.
3. Si **multi-match automático** está activo: grupos por suma de importes en ventana de días.
4. Lo no matcheado queda pendiente (mayor y banco).

Los resúmenes IB de cierre (sistema V/C/T) no se duplican cuando ya hay detalle de subdiario del mes.

---

## 15. Fuentes de datos (resumen técnico para el operador)

| Lado | Fuente |
|---|---|
| Contable | Mayor Anita: **subdiario + ctamov** (mismo criterio que el mayor plano) |
| Banco | `interbanking_movimiento` o extracto importado (`EXT-…`) |
| Cheques propios | cpromae (+ ANULA del mayor ya conciliadas en banco) |
| Pares / grupos | Tablas de conciliación del ERP (persisten entre corridas) |

No hay que “copiar el subdiario a ctamov” para que aparezca: el mayor ya lee las dos fuentes.

---

## 16. Problemas frecuentes

| Síntoma | Qué mirar |
|---|---|
| Enganche “sin extracto” | Traer IB o Importar extracto; verificar cuenta IB en la caja |
| Diferencia de carátula ≠ 0 | Aging + Manual; cheques F.Dev; pendientes banco; ANULA |
| Cheque en banco y no en ERP | Solapa Cruce cheques IB → FALTAN |
| Gasto sin generar documento | Doc. gastos: falta cuenta o concepto para ese código |
| Multi no propone | Reglas: multi-match activo, tolerancia, ventana de días |
| Período no deja conciliar | Está cerrado/aprobado → Reabrir (con permiso) |
| Alinear Excel no graba | En el diálogo eligieron Cancelar (= dry-run) |
| Código de gasto “raro” | Revisar P.C.C. / Conceptos / Patrones en Configuración |

---

## 17. Exportaciones disponibles

| Pantalla | Formatos |
|---|---|
| Index (con resultado) | Excel conciliación |
| Manual | Excel del workspace |
| Aging | Pdf, Excel, Csv |
| Tablero | Pdf, Excel, Csv |

Los exports respetan los filtros de la consulta.

---

## 18. Qué no incluye (a propósito)

- **Open banking / feed continuo PSD2**: no es el modelo operativo local; acá el banco entra por **Interbanking** y/o **archivo**.
- Edición del plan de cuentas o del ABM de conceptos IVA: se **consultan** desde la config; el alta/baja vive en sus pantallas de Contable / Compras.

---

## 19. Glosario breve

| Término | Significado |
|---|---|
| Carátula | Cuadro de saldos del mes (banco vs contable) |
| Par | Emparejamiento 1:1 guardado |
| Grupo | Emparejamiento multi N:1 / 1:N / N:N |
| P.C.C. | Código de agrupación Interbanking (`grouping_code_ib`) |
| AC | Acumulado diario (sellos, IVA 10,5 %, etc.) |
| EXT-{código} | Cuenta virtual de extracto cuando no hay IB |
| Dry-run | Análisis sin grabar (Alinear Excel) |

---

## 20. Contacto operativo

Cambios de negocio (tolerancias, mapas, cuentas de gasto): Contaduría en **Configuración conciliación bancaria**.  
Incidencias de bridge Anita / Interbanking / permisos: Enc-sistemas.

*Versión del módulo: conciliación premium 100 % (oleadas 1–4 + polish): Contaduría + Tesorería, multi-match, extracto sin IB (Excel/CSV/OFX/CAMT/MT940), extracto versionado + KPI + lock, clearing OP/cobranza/IE, worklist, residuales, forecast, score IA, evidencia SoD, cierre/aprobación, aging, tablero, configuración 100 % en pantalla (incluye Residuales). Manual usuario v2.0: `docs/manual-conciliacion-bancaria/`.*
