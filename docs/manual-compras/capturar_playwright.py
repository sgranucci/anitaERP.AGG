#!/usr/bin/env python3
"""
Captura pantallas reales del módulo Compras para el manual de usuario.

Requisitos:
  pip install playwright
  playwright install chromium

Variables de entorno (o .env del proyecto):
  APP_URL, APP_CARPETA, MANUAL_CAPTURE_USER, MANUAL_CAPTURE_PASSWORD

Uso:
  python3 docs/manual-compras/capturar_playwright.py
  python3 docs/manual-compras/capturar_playwright.py --base http://localhost/anitaERP/public
"""

from __future__ import annotations

import argparse
import os
import re
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "public" / "docs" / "manual-compras" / "img"

PANTALLAS = [
    ("login", "/seguridad/login", False),
    ("proveedor-listado", "/compras/proveedor", True),
    ("proveedor-edicion", "/compras/proveedor/{proveedor_id}/editar", True),
    ("requisicion-listado", "/compras/requisicion", True),
    ("requisicion-edicion", "/compras/requisicion/soloconsulta/{requisicion_id}", True),
    ("presupuestos-tab", "/compras/requisicion/soloconsulta/{requisicion_id}", True),
    ("listaprecio-proveedor", "/compras/listaprecio_proveedor", True),
    ("ordencompra-listado", "/compras/ordencompra", True),
    ("ordencompra-edicion", "/compras/ordencompra/{ordencompra_id}/editar", True),
    ("tablas-maestras", "/compras/condicionpago", True),
]


def load_dotenv() -> None:
    env_path = ROOT / ".env"
    if not env_path.is_file():
        return
    for line in env_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        val = val.strip().strip("'\"")
        os.environ.setdefault(key.strip(), val)


def base_url(cli_base: str | None) -> str:
    if cli_base:
        return cli_base.rstrip("/")
    app_url = os.environ.get("APP_URL", "http://localhost").rstrip("/")
    carpeta = os.environ.get("APP_CARPETA", "") or ""
    if carpeta and not carpeta.startswith("/"):
        carpeta = "/" + carpeta
    return (app_url + carpeta).rstrip("/")


def resolve_ids() -> dict[str, int]:
    """Intenta leer IDs recientes vía artisan tinker (opcional)."""
    ids: dict[str, int] = {}
    try:
        import subprocess

        for table, key in [
            ("proveedor", "proveedor_id"),
            ("requisicion", "requisicion_id"),
            ("ordencompra", "ordencompra_id"),
        ]:
            cmd = [
                "php",
                str(ROOT / "artisan"),
                "tinker",
                "--execute=print(\\Illuminate\\Support\\Facades\\DB::table('"
                + table
                + "')->orderByDesc('id')->value('id') ?? 0);",
            ]
            out = subprocess.check_output(cmd, cwd=ROOT, text=True, timeout=30).strip()
            if out.isdigit() and int(out) > 0:
                ids[key] = int(out)
    except Exception:
        pass
    return ids


def login(page, base: str, user: str, password: str) -> None:
    page.goto(base + "/seguridad/login", wait_until="networkidle")
    page.fill('input[name="usuario"]', user)
    page.fill('input[name="password"]', password)
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")
    time.sleep(1)
    modal_btn = page.query_selector(
        "#modal-seleccionar-rol .btn-primary, #modal-seleccionar-rol button[type='submit']"
    )
    if modal_btn:
        modal_btn.click()
        page.wait_for_load_state("networkidle")
        time.sleep(0.8)


def capture_presupuestos_tab(page) -> None:
    btn = page.query_selector("#boton-solapa-presupuesto-requisicion")
    if btn:
        btn.click()
        time.sleep(0.6)


def main() -> int:
    load_dotenv()
    parser = argparse.ArgumentParser(description="Captura pantallas del manual Compras")
    parser.add_argument("--base", help="URL base del ERP")
    parser.add_argument("--usuario", default=os.environ.get("MANUAL_CAPTURE_USER", "admin"))
    parser.add_argument("--password", default=os.environ.get("MANUAL_CAPTURE_PASSWORD", ""))
    args = parser.parse_args()

    if not args.password:
        print("Defina MANUAL_CAPTURE_PASSWORD en .env o use --password", file=sys.stderr)
        return 1

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("Instale: pip install playwright && playwright install chromium", file=sys.stderr)
        return 1

    base = base_url(args.base)
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    ids = resolve_ids()

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1280, "height": 800})

        for nombre, path_tpl, needs_auth in PANTALLAS:
            path = path_tpl
            for key, val in ids.items():
                path = path.replace("{" + key + "}", str(val))
            if "{" in path:
                print(f"  ⊘ {nombre}: sin ID en BD")
                continue

            url = base + path.split("#")[0]
            hash_frag = path.split("#")[1] if "#" in path else ""

            if needs_auth:
                login(page, base, args.usuario, args.password)
            else:
                page.context.clear_cookies()

            page.goto(url, wait_until="networkidle")
            time.sleep(1.2)

            if nombre == "presupuestos-tab" or hash_frag == "presupuestos":
                capture_presupuestos_tab(page)

            dest = OUT_DIR / f"{nombre}.png"
            page.screenshot(path=str(dest), full_page=False)
            print(f"  ✓ {dest.name}")

        browser.close()

    print(f"\nCapturas en {OUT_DIR}\nEjecute: php docs/manual-compras/generar.php")
    return 0


if __name__ == "__main__":
    sys.exit(main())
