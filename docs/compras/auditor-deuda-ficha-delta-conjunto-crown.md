# Crown — informe correcciones delta 19/11/2026

**Para:** Contaduría  
**Empresa:** Emprendimientos Crown SA  
**Fecha:** 19/11/2026  
**Asunto:** Se corrigieron las 3 causas del aviso de delta conjunto

---

## Resultado

| Control | Antes (alerta 19/11) | Después de corregir |
|---|---:|---:|
| Deuda vs ficha | 0 diferencias | 0 diferencias |
| Delta conjunto | −750.480.285,96 | **−747.307.756,46** |
| Variación vs base comparable | +360.312 (ALERTA) | **0,00 (estable)** |

El conjunto nuevo coincide con el del 18/11 **sin** la cuenta GALENO FONDO (que salió del control al corregir Andes). No debería volver a alertar por estas tres causas.

---

## Qué se corrigió

### 1) Albano Giovanni — FDS C-5-1 (#13048)

| Campo | Valor |
|---|---|
| Problema | Asiento con Haber 4.800.000 en `310101000` y **sin** CC |
| Acción | Se creó CC **#3489** por **4.800.000,00** (fecha 03/02/2026), abierta |
| Cuota | `total_pagado` pasó de 4.800.000 → **0** (estaba marcada pagada sin pago en ERP) |

Quedó deuda espejo del mayor. Si la factura ya estaba paga en la práctica, Contaduría debe registrar el pago (OPP) para cerrar esta CC; no anular la CC a mano.

### 2) VTO HPE A-4-201370 (#33625)

| Campo | Valor |
|---|---|
| Problema | Concepto Debe **7.519.050** iba a `310150000` LEASING (pasivo del proveedor) |
| Acción | Se reimputó a **`715335000` ALQUILER DE EQUIPOS/MAQUINARIA** |
| Asiento | 634566 — balancea en 0 |

Asiento corregido:

| Cuenta | Importe |
|---|---:|
| 715335000 ALQUILER DE EQUIPOS/MAQUINARIA | +7.519.050,00 |
| 711331000 IVA PERDIDO 21% | +1.579.000,50 |
| 310150000 PROVEEDORES BS.REC.EN LEASING | −9.098.050,50 |

### 3) Galeno FA A-1069-844630 + Andes Investment

**Galeno (asiento 634454):** el Debe de **3.019.078** salió de `310210000` GALENO FONDO y pasó a **`130308000` OTROS GAST PAG.ADELANTADO** (junto al resto del gasto de la factura).

Asiento corregido:

| Cuenta | Importe |
|---|---:|
| 130308000 OTROS GAST PAG.ADELANTADO | +39.697.125,30 |
| 330180000 GALENO | −39.697.125,30 |

**Andes Investment (2736):** cuenta MN/ME de `310210000` GALENO FONDO → **`310101000` PROVEEDORES $**.  
Con eso **ningún proveedor** queda con GALENO FONDO: esa cuenta **deja de entrar** al control de delta proveedores.

---

## Lectura para Contaduría

1. **Albano:** CC abierta de 4,8 M. Si corresponde pago, cargarlo; si no, queda deuda real a gestionar.
2. **VTO HPE:** los próximos vencimientos no deben debitar `310150000` como gasto. Usar gasto/activo (como `715335000`) y dejar el pasivo solo en el Haber.
3. **Galeno / sueldos:** el asiento de sueldos que acredita FONDO ya no mueve el delta de proveedores (FONDO salió del universo controlado). Revisar igual si FONDO es la cuenta correcta en sueldos.
4. Base vigente del control: **−747.307.756,46** al 19/11/2026.

---

## Números de referencia

| | 18/11 (con FONDO) | 19/11 alerta | 19/11 post-fix |
|---|---:|---:|---:|
| Delta conjunto | −750.840.597,96 | −750.480.285,96 | **−747.307.756,46** |
| Variación día a día | −3.019.078 (Galeno) | +360.312 | **0** |

Desglose del salto 18→19 que se revirtió:

| Cuenta | Variación alerta | Corrección |
|---|---:|---|
| 310101000 PROVEEDORES $ | +4.800.000 | CC Albano |
| 310150000 LEASING | −7.519.050 | Reimputación VTO |
| 310210000 GALENO FONDO | +3.079.362 | Sale del control (Andes) + FA Galeno a prepaid |
| **Neto** | **+360.312** | **Estable** |
