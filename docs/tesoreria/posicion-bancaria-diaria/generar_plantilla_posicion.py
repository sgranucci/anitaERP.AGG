#!/usr/bin/env python3
"""
Genera plantilla Posición Bancos diaria:
  - Hoja 1 Saldos = dato base (tabla con codigo + fechas)
  - Hoja 2 Resumen = consume Saldos por codigo (no HLOOKUP por fila)

Uso:
  python3 generar_plantilla_posicion.py \\
    --saldos "/home/sergio/tmp/Saldos AGOSTO.xlsx" \\
    --out "/home/sergio/tmp/Posicion_Bancos_plantilla.xlsx"
"""

from __future__ import annotations

import argparse
import csv
from datetime import datetime
from pathlib import Path

from openpyxl import Workbook, load_workbook
from openpyxl.styles import Alignment, Font, NamedStyle, numbers
from openpyxl.utils import get_column_letter

DOCS = Path(__file__).resolve().parent
CUENTAS_CSV = DOCS / "cuentas-canonicas.csv"

# Columna en Saldos donde empieza el historial de fechas
SALDOS_FIRST_DATE_COL = 8  # H
# Columna auxiliar saldo del día (fórmula INDEX/MATCH sobre fecha Resumen!E1)
SALDOS_SALDO_DIA_COL = 7  # G


def load_cuentas() -> list[dict]:
    rows = []
    with CUENTAS_CSV.open(encoding="utf-8") as f:
        for r in csv.DictReader(f):
            r["fila_saldos"] = int(r["fila_saldos"])
            rows.append(r)
    return rows


def read_saldos_agosto(path: Path) -> tuple[list[datetime], dict[int, list]]:
    """Devuelve (fechas, {fila: [valores por fecha]})."""
    wb = load_workbook(path, data_only=True)
    ws = wb.active
    fechas = []
    for c in range(2, ws.max_column + 1):
        v = ws.cell(1, c).value
        if isinstance(v, datetime):
            fechas.append(v)
        elif v is None:
            break
        else:
            fechas.append(v)
    by_row: dict[int, list] = {}
    for r in range(2, ws.max_row + 1):
        vals = []
        empty = True
        for i, _ in enumerate(fechas):
            v = ws.cell(r, i + 2).value
            if v is None:
                v = 0
            else:
                empty = False
            vals.append(v)
        if not empty or ws.cell(r, 1).value:
            by_row[r] = vals
    wb.close()
    return fechas, by_row


def saldo_dia_formula(excel_row: int, n_dates: int) -> str:
    """G{row} = valor de la columna cuya fecha = Resumen!$E$1."""
    first = get_column_letter(SALDOS_FIRST_DATE_COL)
    last = get_column_letter(SALDOS_FIRST_DATE_COL + n_dates - 1)
    # INDEX(fila_fechas, MATCH(fecha, encabezados, 0))
    return (
        f'=IFERROR(INDEX({first}{excel_row}:{last}{excel_row},'
        f'MATCH(Resumen!$E$1,{first}$1:{last}$1,0)),0)'
    )


def sumif_codigos(codigos: list[str]) -> str:
    """Suma saldo_dia (col G) para uno o más codigos en Saldos!A."""
    parts = [
        f'SUMIF(Saldos!$A:$A,"{c}",Saldos!$G:$G)' for c in codigos
    ]
    return "=" + "+".join(parts) if parts else "=0"


# Mapeo Resumen (sociedad col -> lista de codigos). Corregido vs Excel viejo.
# Clave: (fila_resumen, col) col in B,C,D,G
def build_resumen_codigo_map() -> dict[tuple[int, str], list[str]]:
    """Replica la lógica del Excel actual, con fixes Provincia USD Kan/Reb."""
    m: dict[tuple[int, str], list[str]] = {}

    def put(row: int, col: str, *codigos: str) -> None:
        m[(row, col)] = list(codigos)

    # --- PESOS (filas 6-16) ---
    put(6, "B", "BIY|MACRO|ARS|CC", "BIY|MACRO|ARS|TRANSITO")
    put(6, "C", "KAN|MACRO|ARS|CC", "KAN|MACRO|ARS|TRANSITO", "KAN|MACRO|ARS|CA")
    put(6, "D", "REB|MACRO|ARS|CC", "REB|MACRO|ARS|TRANSITO", "REB|MACRO|ARS|CA")
    put(6, "G", "UT|MACRO|ARS|CC")

    put(7, "B", "BIY|ITAU|ARS|CC", "BIY|ITAU|ARS|TRANSITO", "BIY|ITAU|ARS|AVELLANEDA")
    put(7, "C", "KAN|ITAU|ARS|CC")  # as-is Excel (sin tránsito/Avellaneda)
    put(7, "D", "REB|ITAU|ARS|CC", "REB|ITAU|ARS|TRANSITO", "REB|ITAU|ARS|AVELLANEDA")

    put(8, "B", "BIY|FRANCES|ARS|CC")
    put(8, "C", "KAN|FRANCES|ARS|CC")
    put(8, "D", "REB|FRANCES|ARS|CC")

    put(9, "B", "BIY|BAPRO|ARS|CC", "BIY|BAPRO|ARS|TRANSITO")
    put(9, "C", "KAN|BAPRO|ARS|CC", "KAN|BAPRO|ARS|TRANSITO")
    put(9, "D", "REB|BAPRO|ARS|CC", "REB|BAPRO|ARS|TRANSITO")

    put(10, "B", "BIY|BNA|ARS|CC", "BIY|BNA|ARS|TRANSITO")
    put(10, "C", "KAN|BNA|ARS|CC", "KAN|BNA|ARS|TRANSITO")
    put(10, "D", "REB|BNA|ARS|CC", "REB|BNA|ARS|TRANSITO")

    put(11, "B", "BIY|BIBANK|ARS|CC", "BIY|BIBANK|ARS|TRANSITO")
    put(11, "C", "KAN|BIBANK|ARS|CC", "KAN|BIBANK|ARS|TRANSITO")
    put(11, "D", "REB|BIBANK|ARS|CC", "REB|BIBANK|ARS|TRANSITO")

    put(12, "B", "BIY|BIND|ARS|CC", "BIY|BIND|ARS|TRANSITO")
    put(12, "C", "KAN|BIND|ARS|CC", "KAN|BIND|ARS|TRANSITO")
    put(12, "D", "REB|BIND|ARS|CC", "REB|BIND|ARS|TRANSITO")

    put(13, "B", "BIY|MACO|ARS|CC")
    put(13, "C", "KAN|MACO|ARS|CC")
    put(13, "D", "REB|MACO|ARS|CC")

    # Ciudad: Bingos en 0; UT en G
    put(14, "G", "UT|CIUDAD|ARS|CC", "UT|CIUDAD|ARS|LOTBA")

    put(15, "B", "BIY|MP|ARS|SALDO")
    put(15, "C", "KAN|MP|ARS|SALDO")
    put(15, "D", "REB|MP|ARS|SALDO")
    put(15, "G", "UT|MACRO|ARS|TRANSITO")  # fila 207 as-is (depósito tránsito UT Macro)

    put(16, "B", "BIY|TESORERIA|ARS|CC")
    put(16, "C", "KAN|TESORERIA|ARS|CC")
    put(16, "D", "REB|TESORERIA|ARS|CC")

    # --- USD (20-28) ---
    put(20, "B", "BIY|MACRO|USD|CC")
    put(20, "C", "KAN|MACRO|USD|CC")
    put(20, "D", "REB|MACRO|USD|CC")

    put(21, "B", "BIY|ITAU|USD|CC")
    put(21, "C", "KAN|ITAU|USD|CC")
    put(21, "D", "REB|ITAU|USD|CC")

    put(22, "B", "BIY|FRANCES|USD|CC")
    put(22, "C", "KAN|FRANCES|USD|CC")
    put(22, "D", "REB|FRANCES|USD|CC")

    put(23, "B", "BIY|BAPRO|USD|CC")
    # FIX bugs Excel viejo C23/D23
    put(23, "C", "KAN|BAPRO|USD|CC")
    put(23, "D", "REB|BAPRO|USD|CC")

    put(24, "B", "BIY|BNA|USD|CC")
    put(24, "C", "KAN|BNA|USD|CC")
    put(24, "D", "REB|BNA|USD|CC")

    put(25, "B", "BIY|BIBANK|USD|CC")
    put(25, "C", "KAN|BIBANK|USD|CC")
    put(25, "D", "REB|BIBANK|USD|CC")

    put(26, "B", "BIY|BIND|USD|CC")
    put(26, "C", "KAN|BIND|USD|CC")
    put(26, "D", "REB|BIND|USD|CC")

    put(27, "B", "BIY|MACO|USD|CC")
    put(27, "C", "KAN|MACO|USD|CC")
    put(27, "D", "REB|MACO|USD|CC")

    put(28, "B", "BIY|TESORERIA|USD|CC")
    put(28, "C", "KAN|TESORERIA|USD|CC")
    put(28, "D", "REB|TESORERIA|USD|CC")

    # --- Inversiones: códigos según filas Saldos (labels Resumen pueden diferir) ---
    # Biyemas filas 55-62
    put(66, "B", "BIY|INVERSIONES|ARS|FCI_GOAL_RENTA_GLOBAL")
    put(67, "B", "BIY|INVERSIONES|ARS|FCI_PIONERO_RENTA_AHORRO")
    put(68, "B", "BIY|INVERSIONES|ARS|FCI_RENTA_CRECIMIENTO")
    put(69, "B", "BIY|INVERSIONES|ARS|FCI_GOAL_PESOS")
    put(70, "B", "BIY|INVERSIONES|ARS|FCI_ACCIONES_ARGENTINAS")
    put(71, "B", "BIY|INVERSIONES|ARS|IAM_AHORRO_PESOS")
    put(72, "B", "BIY|INVERSIONES|ARS|PLAZO_FIJO")
    put(73, "B", "BIY|INVERSIONES|ARS|OTROS")

    put(66, "C", "KAN|INVERSIONES|ARS|FCI_PIONERO_PESOS")
    put(67, "C", "KAN|INVERSIONES|ARS|FCI_PIONERO_RENTA_AHORRO")
    put(68, "C", "KAN|INVERSIONES|ARS|FCI_PELLEGRINI_RENTA_PESOS")
    put(69, "C", "KAN|INVERSIONES|ARS|FCI_GOAL_PESOS")
    put(70, "C", "KAN|INVERSIONES|ARS|FCI_GOAL_CAPITAL_PLUS")
    put(71, "C", "KAN|INVERSIONES|ARS|IAM_AHORRO_PESOS")
    put(72, "C", "KAN|INVERSIONES|ARS|PLAZO_FIJO")
    put(73, "C", "KAN|INVERSIONES|ARS|FCI_GOAL_PESOS#127")  # slot Otros Kan = 2º Goal Pesos

    put(66, "D", "REB|INVERSIONES|ARS|FCI_PIONERO_PESOS")
    put(67, "D", "REB|INVERSIONES|ARS|FCI_PIONERO_RENTA_AHORRO")
    put(68, "D", "REB|INVERSIONES|ARS|FCI_PELLEGRINI_RENTA_PESOS")
    put(69, "D", "REB|INVERSIONES|ARS|FCI_GOAL_PESOS")
    put(70, "D", "REB|INVERSIONES|ARS|FCI_GOAL_CAPITAL_PLUS")
    put(71, "D", "REB|INVERSIONES|ARS|IAM_AHORRO_PESOS")
    put(72, "D", "REB|INVERSIONES|ARS|PLAZO_FIJO")
    put(73, "D", "REB|INVERSIONES|ARS|OTROS")

    put(69, "G", "UT|INVERSIONES|ARS|FCI_GOAL_PESOS")

    return m


def build_workbook(fechas: list, values_by_fila: dict[int, list], fecha_pos) -> Workbook:
    cuentas = load_cuentas()
    codigo_map = build_resumen_codigo_map()
    n_dates = len(fechas)

    wb = Workbook()

    # ========== Saldos (primera hoja = dato base) ==========
    ws = wb.active
    ws.title = "Saldos"

    headers = [
        "codigo",
        "sociedad",
        "banco",
        "moneda",
        "tipo_cuenta",
        "rol",
        "saldo_dia",
        *fechas,
    ]
    # Insert concepto after rol visually: reorder
    # codigo, sociedad, banco, moneda, tipo_cuenta, rol, saldo_dia, fechas...
    # Add concepto as column before saldo_dia — adjust:
    # A codigo B sociedad C banco D moneda E tipo F rol G saldo_dia H... fechas
    # We'll put concepto in a far column or replace: use
    # A codigo B sociedad C banco D moneda E tipo_cuenta F concepto G saldo_dia H+ fechas

    headers = [
        "codigo",
        "sociedad",
        "banco",
        "moneda",
        "tipo_cuenta",
        "concepto",
        "saldo_dia",
        *fechas,
    ]
    for col, h in enumerate(headers, 1):
        cell = ws.cell(1, col, h)
        cell.font = Font(bold=True)
        if isinstance(h, datetime):
            cell.number_format = "DD/MM/YYYY"
            cell.value = h

    for i, cta in enumerate(cuentas):
        r = i + 2
        ws.cell(r, 1, cta["codigo"])
        ws.cell(r, 2, cta["sociedad"])
        ws.cell(r, 3, cta["banco"])
        ws.cell(r, 4, cta["moneda"])
        ws.cell(r, 5, cta["tipo_cuenta"] or ("TRANSITO" if cta["es_transito"] == "1" else ""))
        ws.cell(r, 6, cta["concepto_raw"])
        ws.cell(r, 7, saldo_dia_formula(r, n_dates))
        fila_src = cta["fila_saldos"]
        vals = values_by_fila.get(fila_src, [0] * n_dates)
        for j, v in enumerate(vals):
            cell = ws.cell(r, SALDOS_FIRST_DATE_COL + j, v if v is not None else 0)
            cell.number_format = "#,##0.00"

    ws.freeze_panes = "H2"
    ws.auto_filter.ref = f"A1:{get_column_letter(7 + n_dates)}{len(cuentas) + 1}"
    ws.column_dimensions["A"].width = 36
    ws.column_dimensions["F"].width = 28
    ws.column_dimensions["G"].width = 14

    # ========== Resumen ==========
    wr = wb.create_sheet("Resumen", 1)
    wr["A1"] = "RESUMEN POSICIONES BANCARIAS Y TESORERIA"
    wr["A1"].font = Font(bold=True, size=14)
    wr["E1"] = fecha_pos
    wr["E1"].number_format = "DD/MM/YYYY"
    wr["D1"] = "Fecha"
    wr["D1"].font = Font(bold=True)

    wr["A3"] = "1) Posiciones por Moneda"
    wr["A3"].font = Font(bold=True)

    # PESOS block
    wr["A5"] = "PESOS"
    for col, lab in [("B", "Biyemas"), ("C", "Kandiko"), ("D", "Rebisco"), ("E", "Total"), ("G", "Biyemas Skill ON NET UT")]:
        wr[f"{col}5"] = lab
        wr[f"{col}5"].font = Font(bold=True)

    pesos_rows = [
        (6, "Banco Macro"),
        (7, "Banco ITAU"),
        (8, "Banco Francés"),
        (9, "Banco Provincia"),
        (10, "BNA"),
        (11, "Bi Bank"),
        (12, "Banco Industrial"),
        (13, "Maco"),
        (14, "Banco Ciudad"),
        (15, "Mercado Pago"),
        (16, "Tesorería"),
    ]
    for row, label in pesos_rows:
        wr[f"A{row}"] = label
        for col in ("B", "C", "D", "G"):
            codes = codigo_map.get((row, col))
            if codes:
                wr[f"{col}{row}"] = sumif_codigos(codes)
            elif row == 14 and col in ("B", "C", "D"):
                wr[f"{col}{row}"] = 0
            elif row == 7 and col == "G":
                wr[f"{col}{row}"] = 0
            wr[f"{col}{row}"].number_format = "#,##0.00"
        wr[f"E{row}"] = f"=SUM(B{row}:D{row})"
        wr[f"E{row}"].number_format = "#,##0.00"

    wr["A17"] = "Total"
    wr["A17"].font = Font(bold=True)
    for col in ("B", "C", "D", "E", "G"):
        wr[f"{col}17"] = f"=SUM({col}6:{col}16)"
        wr[f"{col}17"].number_format = "#,##0.00"

    # USD block
    wr["A19"] = "DOLARES"
    for col, lab in [("B", "Biyemas"), ("C", "Kandiko"), ("D", "Rebisco"), ("E", "Total"), ("G", "Biyemas Skill ON NET UT")]:
        wr[f"{col}19"] = lab
        wr[f"{col}19"].font = Font(bold=True)

    usd_rows = [
        (20, "Banco Macro"),
        (21, "Banco ITAU"),
        (22, "Banco Francés"),
        (23, "Banco Provincia"),
        (24, "BNA"),
        (25, "Bi Bank"),
        (26, "Banco Industrial"),
        (27, "Maco"),
        (28, "Tesorería"),
    ]
    for row, label in usd_rows:
        wr[f"A{row}"] = label
        for col in ("B", "C", "D"):
            codes = codigo_map.get((row, col))
            wr[f"{col}{row}"] = sumif_codigos(codes) if codes else 0
            wr[f"{col}{row}"].number_format = "#,##0.00"
        wr[f"G{row}"] = 0
        wr[f"E{row}"] = f"=SUM(B{row}:D{row})"
        wr[f"E{row}"].number_format = "#,##0.00"

    wr["A29"] = "Total"
    wr["A29"].font = Font(bold=True)
    for col in ("B", "C", "D", "E", "G"):
        wr[f"{col}29"] = f"=SUM({col}20:{col}28)"
        wr[f"{col}29"].number_format = "#,##0.00"

    # EUR block (macros en 0 en Excel viejo para bingos; dejamos SUMIF si hay datos)
    wr["A31"] = "EUROS"
    for col, lab in [("B", "Biyemas"), ("C", "Kandiko"), ("D", "Rebisco"), ("E", "Total"), ("G", "Biyemas Skill ON NET UT")]:
        wr[f"{col}31"] = lab
        wr[f"{col}31"].font = Font(bold=True)

    eur_map = {
        32: ("Banco Macro", ["MACRO"]),
        33: ("Banco Francés", ["FRANCES"]),
        34: ("Banco Provincia", ["BAPRO"]),
        35: ("Maco", ["MACO"]),
        36: ("Tesorería", ["TESORERIA"]),
    }
    for row, (label, bancos) in eur_map.items():
        wr[f"A{row}"] = label
        for col, soc in [("B", "BIY"), ("C", "KAN"), ("D", "REB")]:
            codes = [f"{soc}|{b}|EUR|CC" for b in bancos]
            wr[f"{col}{row}"] = sumif_codigos(codes)
            wr[f"{col}{row}"].number_format = "#,##0.00"
        wr[f"G{row}"] = 0
        wr[f"E{row}"] = f"=SUM(B{row}:D{row})"
        wr[f"E{row}"].number_format = "#,##0.00"

    wr["A37"] = "Total"
    for col in ("B", "C", "D", "E", "G"):
        wr[f"{col}37"] = f"=SUM({col}32:{col}36)"
        wr[f"{col}37"].number_format = "#,##0.00"

    # Pesificado
    wr["A39"] = "2) Posiciones Bancarias (Saldos Pesificados)"
    wr["A39"].font = Font(bold=True)
    wr["A41"] = "Cotización Dólar Comprador:"
    wr["B41"] = 1400  # placeholder razonable; operador completa
    wr["A42"] = "Cotización Euro Comprador:"
    wr["B42"] = 1500

    wr["A44"] = "Banco"
    for col, lab in [("B", "Biyemas"), ("C", "Kandiko"), ("D", "Rebisco"), ("E", "Total"), ("G", "Biyemas Skill ON NET UT")]:
        wr[f"{col}44"] = lab
        wr[f"{col}44"].font = Font(bold=True)

    # refs a bloques moneda
    pesificado = [
        (45, "Macro (PESOS)", "B6", "C6", "D6"),
        (46, "ITAU (PESOS)", "B7", "C7", "D7"),
        (47, "Francés (PESOS)", "B8", "C8", "D8"),
        (48, "Provincia (PESOS)", "B9", "C9", "D9"),
        (49, "BNA (PESOS)", "B10", "C10", "D10"),
        (50, "BIND (PESOS)", "B12", "C12", "D12"),
        (51, "Macro (DOLARES)", "B20*$B$41", "C20*$B$41", "D20*$B$41"),
        (52, "ITAU (DOLARES)", "B21*$B$41", "C21*$B$41", "D21*$B$41"),
        (53, "Francés (DOLARES)", "B22*$B$41", "C22*$B$41", "D22*$B$41"),
        (54, "Provincia (DOLARES)", "B23*$B$41", "C23*$B$41", "D23*$B$41"),
        (55, "BNA (DOLARES)", "B24*$B$41", "C24*$B$41", "D24*$B$41"),
        (56, "Macro (EUROS)", "B32*$B$42", "C32*$B$42", "D32*$B$42"),
        (57, "Francés (EUROS)", "B33*$B$42", "C33*$B$42", "D33*$B$42"),
        (58, "Provincia (EUROS)", "B34*$B$42", "C34*$B$42", "D34*$B$42"),
    ]
    for row, label, b, c, d in pesificado:
        wr[f"A{row}"] = label
        wr[f"B{row}"] = f"={b}" if not b.startswith("B") or "*" in b else f"={b}"
        # normalize
        wr[f"B{row}"] = "=" + b if not b.startswith("=") else b
        wr[f"C{row}"] = "=" + c if not c.startswith("=") else c
        wr[f"D{row}"] = "=" + d if not d.startswith("=") else d
        for col in ("B", "C", "D", "E"):
            if col == "E":
                wr[f"E{row}"] = f"=SUM(B{row}:D{row})"
            wr[f"{col}{row}"].number_format = "#,##0.00"
        wr[f"G{row}"] = 0

    wr["A59"] = "Ciudad UT (PESOS)"
    wr["B59"] = 0
    wr["C59"] = 0
    wr["D59"] = 0
    wr["E59"] = "=SUM(B59:D59)"
    wr["G59"] = 0
    wr["A60"] = "Bi Bank (PESOS) ref"
    wr["B60"] = 0
    wr["C60"] = 0
    wr["D60"] = 0
    wr["E60"] = "=SUM(B60:D60)"
    wr["G60"] = "=G16"  # as-is ITAU UT slot reused loosely

    wr["A61"] = "Total"
    wr["A61"].font = Font(bold=True)
    for col in ("B", "C", "D", "E", "G"):
        wr[f"{col}61"] = f"=SUM({col}45:{col}60)"
        wr[f"{col}61"].number_format = "#,##0.00"

    # Inversiones
    wr["A63"] = "3) Resumen Inversiones"
    wr["A63"].font = Font(bold=True)
    wr["A65"] = "Fondo"
    for col, lab in [("B", "Biyemas"), ("C", "Kandiko"), ("D", "Rebisco"), ("E", "Total"), ("G", "Biyemas Skill ON NET UT")]:
        wr[f"{col}65"] = lab
        wr[f"{col}65"].font = Font(bold=True)

    inv_labels = [
        (66, "FCI Pionero Pesos / Goal renta global (slot)"),
        (67, "FCI Pionero Renta Ahorro"),
        (68, "FCI Pellegrini / renta crecimiento (slot)"),
        (69, "FCI Goal Pesos"),
        (70, "FCI acciones / Goal Capital Plus (slot)"),
        (71, "IAM Ahorro Pesos"),
        (72, "Plazo fijo"),
        (73, "Otros"),
    ]
    for row, label in inv_labels:
        wr[f"A{row}"] = label
        for col in ("B", "C", "D", "G"):
            codes = codigo_map.get((row, col))
            wr[f"{col}{row}"] = sumif_codigos(codes) if codes else 0
            wr[f"{col}{row}"].number_format = "#,##0.00"
        wr[f"E{row}"] = f"=SUM(B{row}:D{row})"
        wr[f"E{row}"].number_format = "#,##0.00"

    wr["A74"] = "Total"
    for col in ("B", "C", "D", "E", "G"):
        wr[f"{col}74"] = f"=SUM({col}66:{col}73)"
        wr[f"{col}74"].number_format = "#,##0.00"

    wr["A76"] = "4) Cheques y proyecciones — pendiente Fase 2/3"
    wr["A76"].font = Font(italic=True, color="666666")

    wr["A78"] = "Notas"
    wr["A79"] = (
        "Saldos es el dato base. Cambiar E1 (fecha) actualiza saldo_dia. "
        "Provincia USD Kan/Reb corregidos (ya no apuntan a Francés EUR / Macro tránsito)."
    )
    wr.merge_cells("A79:G80")
    wr["A79"].alignment = Alignment(wrap_text=True)

    wr.column_dimensions["A"].width = 42
    for col in "BCDEG":
        wr.column_dimensions[col].width = 16

    # ========== Mapa (auditoría) ==========
    wm = wb.create_sheet("_MapaCodigos", 2)
    wm["A1"] = "celda_resumen"
    wm["B1"] = "codigos"
    wm["C1"] = "nota"
    ri = 2
    for (row, col), codes in sorted(codigo_map.items()):
        nota = ""
        if (row, col) == (23, "C"):
            nota = "FIX vs Excel viejo (antes fila 84 Francés EUR)"
        if (row, col) == (23, "D"):
            nota = "FIX vs Excel viejo (antes fila 135 Macro tránsito)"
        wm.cell(ri, 1, f"{col}{row}")
        wm.cell(ri, 2, " + ".join(codes))
        wm.cell(ri, 3, nota)
        ri += 1
    wm.column_dimensions["A"].width = 12
    wm.column_dimensions["B"].width = 80
    wm.column_dimensions["C"].width = 50

    # Instrucciones
    wi = wb.create_sheet("_Instrucciones", 3)
    wi["A1"] = "Cómo usar esta plantilla"
    wi["A1"].font = Font(bold=True, size=14)
    lines = [
        "1. Hoja Saldos = dato base. Pegar/importar saldos Interbanking en las columnas de fecha (desde H).",
        "2. Cada fila tiene un codigo estable (col A). No insertar filas a mano sin mantener el codigo.",
        "3. Columna G (saldo_dia) toma el valor de la fecha indicada en Resumen!E1.",
        "4. Resumen no usa HLOOKUP por número de fila: usa SUMIF por codigo.",
        "5. Completar cotizaciones en Resumen!B41 (USD) y B42 (EUR).",
        "6. Cheques y hojas por banco se agregan en Fase 2/3.",
        "7. Generado desde docs/tesoreria/posicion-bancaria-diaria/generar_plantilla_posicion.py",
    ]
    for i, line in enumerate(lines, 3):
        wi[f"A{i}"] = line
    wi.column_dimensions["A"].width = 110

    return wb


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument(
        "--saldos",
        type=Path,
        default=Path("/home/sergio/tmp/Saldos AGOSTO.xlsx"),
    )
    ap.add_argument(
        "--out",
        type=Path,
        default=Path("/home/sergio/tmp/Posicion_Bancos_plantilla.xlsx"),
    )
    ap.add_argument(
        "--fecha",
        default="2026-08-31",
        help="Fecha de posición (YYYY-MM-DD), debe existir como columna en Saldos",
    )
    args = ap.parse_args()

    fechas, by_fila = read_saldos_agosto(args.saldos)
    fecha_pos = datetime.strptime(args.fecha, "%Y-%m-%d")
    # match to fecha in list if same day
    for f in fechas:
        if isinstance(f, datetime) and f.date() == fecha_pos.date():
            fecha_pos = f
            break

    wb = build_workbook(fechas, by_fila, fecha_pos)
    args.out.parent.mkdir(parents=True, exist_ok=True)
    wb.save(args.out)
    print(f"OK → {args.out}")
    print(f"  Saldos: {len(load_cuentas())} cuentas × {len(fechas)} fechas")
    print(f"  Fecha posición: {fecha_pos.date()}")


if __name__ == "__main__":
    main()
