# Checklist diario — Posición bancaria

Fecha de posición: _______________

## 1. Dato base (hoja Saldos)

- [ ] Export / descarga Interbanking del día (todas las cuentas del grupo)
- [ ] Cargar / importar en hoja **Saldos** (primera solapa) — no editar Resumen a mano para saldos
- [ ] Verificar sociedades: Biyemas, Kandiko, Rebisco, UT
- [ ] Verificar bancos activos con movimiento (Macro, Itaú/BMA, BAPRO, Bi Bank, BIND, etc.)
- [ ] Cargar saldos de **Tesorería** y **Mercado Pago** si no vienen de Interbanking
- [ ] Cargar / actualizar **inversiones** (FCI / PF) si aplica
- [ ] Cotización USD comprador: _______ · Euro comprador: _______

## 2. Cheques (ERP)

- [ ] Stock en `posicion_bancaria_cheque` actualizado (import Excel o altas del día)
- [ ] Aging al día: retenidos / tránsito / diferidos cuadra con Disponible Resumen
- [ ] Si hubo conciliación del mes: carátula CHP alineada con mismos números/cuentas

## 3. Proyección del día (por banco)

Completar movimientos del día en:

- [ ] Macro
- [ ] Macro (BMA / ex Itaú)
- [ ] BAPRO
- [ ] Bi Bank
- [ ] Bind

Incluir si aplica: cheques a debitar, TRF, RRHH/sueldos, impuestos, MP, descubiertos, intercompany.

- [ ] Control TRF cruzadas: lo que sale de un banco entra en otro (neto consolidado coherente)

## 4. Controles finales

- [ ] Control posiciones por moneda (Resumen) cuadra vs totales Saldos
- [ ] Disponible HOY Macro / BMA / BIND coherente con cheques retenidos+tránsito
- [ ] Resumen descubierto revisado
- [ ] Guardar copia del día: `Posición Bancos al DD.MM.AAAA.xlsx`

## Notas del día

_
_
_
