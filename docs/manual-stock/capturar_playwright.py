#!/usr/bin/env python3
"""
Captura pantallas reales del módulo Recuento de inventario para el manual de usuario.

Requisitos:
  pip install playwright
  playwright install chromium

Uso:
  python3 docs/manual-stock/capturar_playwright.py
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "public" / "docs" / "manual-stock" / "img"

PANTALLAS = [
    ("recuento-listado", "/stock/recuento", True),
    ("recuento-crear", "/stock/recuento/crear", True),
    ("recuento-editar", "/stock/recuento/{recuento_id}/editar", True),
    ("recuento-ver", "/stock/recuento/{recuento_id}/ver", True),
    ("recuento-opciones-cierre", "/stock/recuento/{recuento_id}/ver", True),
    ("recuento-movimientos", "/stock/recuento/movimientos-articulo?articulo_id={articulo_id}&deposito_id={deposito_id}", True),
    ("recuento-importar", "/stock/recuento/{recuento_id}/importar", True),
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
    ids: dict[str, int] = {}
    try:
        cmd = [
            "php",
            str(ROOT / "artisan"),
            "tinker",
            "--execute="
            "$r=\\Illuminate\\Support\\Facades\\DB::table('recuento')->orderByDesc('id')->first();"
            "if($r){echo $r->id.'|'.$r->deposito_id.'|';"
            "$i=\\Illuminate\\Support\\Facades\\DB::table('recuento_item')->where('recuento_id',$r->id)->orderByDesc('id')->value('articulo_id');"
            "echo (int)$i;}",
        ]
        out = subprocess.check_output(cmd, cwd=ROOT, text=True, timeout=30).strip()
        parts = out.split("|")
        if len(parts) >= 3 and parts[0].isdigit():
            ids["recuento_id"] = int(parts[0])
            ids["deposito_id"] = int(parts[1] or 0)
            ids["articulo_id"] = int(parts[2] or 0)
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


def main() -> int:
    load_dotenv()
    parser = argparse.ArgumentParser(description="Captura pantallas del manual Recuento")
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
        page = browser.new_page(viewport={"width": 1280, "height": 900})

        for nombre, path_tpl, needs_auth in PANTALLAS:
            path = path_tpl
            for key, val in ids.items():
                path = path.replace("{" + key + "}", str(val))
            if "{" in path:
                print(f"  ⊘ {nombre}: sin ID en BD")
                continue

            if needs_auth:
                login(page, base, args.usuario, args.password)
            else:
                page.context.clear_cookies()

            page.goto(base + path, wait_until="networkidle")
            time.sleep(1.2)

            if nombre == "recuento-opciones-cierre":
                panel = page.query_selector("#panel-opciones-cierre-recuento")
                if panel:
                    panel.scroll_into_view_if_needed()
                    time.sleep(0.4)

            dest = OUT_DIR / f"{nombre}.png"
            page.screenshot(path=str(dest), full_page=False)
            print(f"  ✓ {dest.name}")

        browser.close()

    print(f"\nCapturas en {OUT_DIR}\nEjecute: php docs/manual-stock/generar.php")
    return 0


if __name__ == "__main__":
    sys.exit(main())
