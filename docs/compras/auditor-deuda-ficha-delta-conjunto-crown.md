# Crown — alerta delta 18/11/2026: factura Galeno del 17/11

**Para:** Contaduría  
**Empresa:** Emprendimientos Crown SA  
**Asunto:** Revisar imputación de la FA A-1069-844630 (Galeno) y corregir la cuenta de proveedores de Andes Investment

---

## Qué pasó

El auditor diario del 18/11 avisó que se movió el **delta conjunto** de proveedores + anticipos.

- **Deuda vs ficha:** cuadrada (sin diferencias). No hay problema de ficha.
- **Variación del delta:** **−3.019.078,00** respecto del 17/11.
- Ese importe sale **entero** de una sola línea del asiento cargado ayer.

---

## Comprobante a revisar (cargado 17/11/2026 09:55)

| Campo | Valor |
|---|---|
| Proveedor | **3022 GALENO ARGENTINA S.A.** |
| Comprobante | **FA A-1069-844630** |
| Fecha comprobante | 19/10/2026 |
| Total | 39.697.125,30 |
| Leyenda | DIF PLAN Y APORTES 09-26 |
| Asiento | 634454 (nro 824533) |
| Estado | CONTABILIZADO |
| Cuenta corriente | Cerrada (aplicada) |

Cuenta de proveedores del maestro de Galeno: **330180000 GALENO** (no usa la genérica 310101000).

---

## Asiento tal como quedó

| Cuenta | Nombre | Debe / Haber |
|---|---|---:|
| 310210000 | **GALENO FONDO** | **+3.019.078,00 Debe** |
| 710127000 | ADICIONAL OB.SOCIAL | +36.678.047,30 Debe |
| 330180000 | GALENO | −39.697.125,30 Haber |

---

## Por qué no es lógico (y por eso saltó el control)

1. La **deuda del proveedor** vive en **330180000 GALENO** (cuenta del maestro). Ahí fue el Haber y ahí se manejó la CC.
2. Parte del pasivo del asiento (**3.019.078**) se mandó a **310210000 GALENO FONDO**, que **no es** la cuenta de proveedores de Galeno.
3. En FONDO no queda deuda abierta de esta factura. El mayor de FONDO sí se movió. El control compara mayor vs deuda por cuenta: en FONDO queda mayor sin deuda → mueve el delta → ALERTA.
4. Mientras el Haber esté en GALENO y un tramo del Debe en GALENO FONDO, **cada carga así va a volver a disparar el aviso**, aunque la ficha siga cuadrada.

El control no está “confundido”: refleja que el asiento parte el pasivo en una cuenta que no es la de la deuda del proveedor.

---

## Qué tiene que cambiar Contaduría

Revisar y **corregir la imputación de esta factura / asiento 634454** para que el pasivo del proveedor quede alineado con la cuenta de Galeno:

**Opción A (preferida si FONDO no es deuda de proveedor)**  
- Llevar los **3.019.078** a **330180000 GALENO** (junto con el resto del Haber de proveedor), o a la cuenta de resultado/gasto que corresponda al concepto “fondo / plan y aportes”, **no** a una cuenta de pasivo ajena a la deuda de Galeno.
- Criterio: la pierna que representa la deuda con Galeno tiene que estar en **330180000** (su cuenta de proveedores).

**Opción B (si Contaduría sostiene que esos 3.019.078 son un pasivo distinto “Galeno Fondo”)**  
- Entonces ese pasivo tiene que tener **deuda / CC espejo** en esa misma cuenta, no solo el movimiento de mayor. Hoy la CC de la factura está en la órbita de **330180000**, no de FONDO.
- Sin deuda en FONDO, el control seguirá alertando.

En ambos casos: **no dejar un Debe en 310210000 sin contrapartida de deuda en esa misma cuenta** si la factura es de Galeno y la deuda se registra contra 330180000.

---

## Segundo cambio: cuenta del proveedor Andes Investment

Además del asiento de Galeno, hay que **corregir el maestro** de este proveedor:

| Campo | Valor actual |
|---|---|
| Proveedor | **2736 ANDES INVESTMENT CORP.** |
| Cuenta MN | **310210000 GALENO FONDO** ← incorrecta |
| Cuenta ME | **310210000 GALENO FONDO** ← incorrecta |
| Cuenta corriente de Andes | **0** filas |

GALENO FONDO no es una cuenta de deuda de Andes. Quedó cargada en el maestro y por eso el auditor la incluye entre las cuentas de proveedores que controla. No es la causa del asiento de ayer, pero **hay que cambiarle la cuenta** a la que corresponda (en general **310101000 PROVEEDORES $**, u otra Pasivo real de ese proveedor).

Mientras Andes tenga FONDO como cuenta de proveedores, esa cuenta sigue entrando al control aunque Andes no opere ahí.

---

## Pedido concreto

1. Revisar la FA **A-1069-844630** / asiento **634454**.
2. Definir la imputación correcta de los **3.019.078** (¿gasto/resultado o pasivo Galeno?).
3. Reimputar para que no quede pasivo en **GALENO FONDO** desacoplado de la deuda del proveedor.
4. En el maestro, cambiar la cuenta de **2736 ANDES INVESTMENT CORP.**: sacar **310210000 GALENO FONDO** y poner la cuenta de proveedores correcta.
5. Avisar a Sistemas cuando ambos puntos estén hechos, para verificar que el delta del día siguiente quede estable.

---

## Números de referencia (auditor)

| | 17/11 | 18/11 | Variación |
|---|---:|---:|---:|
| Delta conjunto | −747.821.519,96 | −750.840.597,96 | **−3.019.078,00** |
| Delta 310210000 GALENO FONDO | −513.763,50 | −3.532.841,50 | **−3.019.078,00** |

Base vigente tras la alerta: conjunto **−750.840.597,96** al 18/11/2026 (mientras no se corrija el asiento, ese es el nuevo piso del control).
