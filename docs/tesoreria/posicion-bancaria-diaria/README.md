# Posición bancaria diaria

## Modelo acordado

Un solo workbook de **Posición**. La **primera solapa es `Saldos`** y es el **dato base**.

```
Interbanking
    → import / carga
        → hoja "Saldos" (dato base)
            → Resumen / (luego) bancos / cheques / proyectado
```

- No hay Excel de Saldos externo como fuente viva.
- Cada fila de Saldos tiene un `codigo` estable (`BIY|MACRO|ARS|CC`).
- `Resumen!E1` = fecha de posición → columna `saldo_dia` (G) toma ese día.
- Resumen usa `SUMIF` por codigo, no `HLOOKUP` por número de fila.

## Plantilla Fase 1

| Archivo | Uso |
|---------|-----|
| `Posicion_Bancos_plantilla.xlsx` | Workbook listo (seed agosto 2026) |
| `generar_plantilla_posicion.py` | Regenerar desde `Saldos AGOSTO.xlsx` |

```bash
python3 generar_plantilla_posicion.py \
  --saldos "/home/sergio/tmp/Saldos AGOSTO.xlsx" \
  --fecha 2026-08-31 \
  --out "/home/sergio/tmp/Posicion_Bancos_plantilla.xlsx"
```

Hojas:
1. **Saldos** — dato base (148 cuentas × fechas)
2. **Resumen** — posiciones ARS/USD/EUR + pesificado + inversiones
3. **_MapaCodigos** — auditoría celda → codigos
4. **_Instrucciones** — cómo usar

### Fixes incluidos vs Excel viejo

- `Resumen!C23` → `KAN|BAPRO|USD|CC` (antes Francés EUR)
- `Resumen!D23` → `REB|BAPRO|USD|CC` (antes Macro tránsito USD)

### Programa ERP (Caja / Reportes)

- URL: `caja/posicion-bancaria-diaria` (menú **Módulo de Caja** y atajo **Reportes de integración**)
- Permiso: `generar-posicion-bancaria-diaria`
- Genera Excel: Saldos IB + Resumen + cheques portfolio + Disponible HOY
  + hojas proyección **Macro / Macro (BMA) / BAPRO / Bi Bank / Bind** + **Resumen descubierto**
- Support: `PosicionBancariaDiariaGeneradorSupport`
- Proyección: saldos/cheques enlazados; TRF/RRHH/impuestos se completan a mano en Excel

### Pendiente / en curso

- Persistencia ERP de movimientos del día (TRF/RRHH) + UI de carga
- Tesorería / Mercado Pago / inversiones si no vienen de IB
- Job sync cpromae + import IB diario automatizado
- Controles automáticos (TRF cruzadas, histórico versionado)

## Cheques y conciliación

Ver [cruce-conciliacion-cheques.md](./cruce-conciliacion-cheques.md).

```bash
# 1) Traer CHP 2026 desde Anita (batch, una vez / periódico)
php artisan tesoreria:sync-cheques-cpromae --anio=2026

# 2) (Opcional) Portfolio Excel tesorería
php artisan tesoreria:importar-posicion-cheques "/ruta/Posición.xlsx" --fecha=2026-09-01

# 3) Armar plantilla con hojas cheques desde ERP
php artisan tesoreria:generar-plantilla-cheques \
  --fecha=2026-09-01 \
  --base=docs/tesoreria/posicion-bancaria-diaria/Posicion_Bancos_plantilla.xlsx \
  --out=/home/sergio/tmp/Posicion_Bancos_con_cheques.xlsx
```

Conciliación bancaria: `armar()` usa primero `posicion_bancaria_cheque` (`fuente=erp_*`); Anita solo si la cuenta no tiene stock ERP.

**Disponible HOY** (Resumen filas 76–88): saldo pesos − (retenidos+tránsito) del **portfolio** (`en_portfolio_posicion=1`). El padrón 2026 completo queda para conciliación.

## Archivos Fase 0

| Archivo | Qué es |
|---------|--------|
| `cuentas-canonicas.csv` | Diccionario fila → codigo |
| `mapeo-resumen-hlookup.csv` | Celda Resumen → filas Saldos (as-is viejo) |
| `anomalias-detectadas.csv` | Bugs detectados en Excel viejo |
| `checklist-diario.md` | Pasos operativos del día |
| `modelo-dato-base.json` | Modelo JSON |
