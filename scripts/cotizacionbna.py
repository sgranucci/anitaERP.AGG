#!/usr/bin/env python3
"""Cotización oficial BNA (dólar o euro) para cotizacion:leeapi.

Salida con --json:
  {"venta": float, "compra": float, "fuente": str, "fecha": "YYYY-MM-DD"}
Sin dato (finde/feriado):
  No se pudo obtener cotización
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import date, datetime


DOLARAPI_OFICIAL = "https://dolarapi.com/v1/dolares/oficial"
DOLARAPI_EUR = "https://dolarapi.com/v1/cotizaciones/eur"
ARGENTINADATOS_USD = (
    "https://api.argentinadatos.com/v1/cotizaciones/dolares/oficial/{anio}/{mes:02d}/{dia:02d}"
)
ARGENTINADATOS_EUR = (
    "https://api.argentinadatos.com/v1/cotizaciones/eur/{anio}/{mes:02d}/{dia:02d}"
)
UA = "anitaERP-cotizacionbna/1.0"


def instalar_proxy() -> None:
    host = os.environ.get("COTIZACION_PROXY_HOST", "").strip()
    if host == "":
        return
    port = os.environ.get("COTIZACION_PROXY_PORT", "3128").strip() or "3128"
    user = os.environ.get("COTIZACION_PROXY_USER", "")
    password = os.environ.get("COTIZACION_PROXY_PASSWORD", "")
    auth = ""
    if user != "":
        auth = (
            urllib.parse.quote(user, safe="")
            + ":"
            + urllib.parse.quote(password, safe="")
            + "@"
        )
    proxy_url = f"http://{auth}{host}:{port}"
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({"http": proxy_url, "https": proxy_url})
    )
    urllib.request.install_opener(opener)


def http_json(url: str, timeout: float = 20.0):
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError):
        return None
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None


def parse_fecha(texto: str) -> date:
    return datetime.strptime(texto, "%Y-%m-%d").date()


def payload_cotizacion(data: dict, fecha: date, fuente: str):
    venta = data.get("venta")
    if venta is None:
        return None
    try:
        venta_f = float(venta)
    except (TypeError, ValueError):
        return None
    if venta_f <= 0:
        return None
    compra = data.get("compra")
    try:
        compra_f = float(compra) if compra is not None else 0.0
    except (TypeError, ValueError):
        compra_f = 0.0
    fecha_out = str(data.get("fecha") or fecha.isoformat())
    return {
        "venta": venta_f,
        "compra": compra_f,
        "fuente": fuente,
        "fecha": fecha_out[:10],
    }


def desde_argentinadatos(fecha: date, moneda: str):
    plantilla = ARGENTINADATOS_EUR if moneda == "eur" else ARGENTINADATOS_USD
    fuente = "argentinadatos-eur-oficial" if moneda == "eur" else "argentinadatos-oficial"
    url = plantilla.format(anio=fecha.year, mes=fecha.month, dia=fecha.day)
    data = http_json(url)
    if not isinstance(data, dict):
        return None
    return payload_cotizacion(data, fecha, fuente)


def desde_dolarapi(fecha: date, moneda: str):
    # DolarApi es la cotización vigente; solo sirve para hoy o ayer.
    hoy = date.today()
    if abs((fecha - hoy).days) > 1:
        return None
    if moneda == "eur":
        url = DOLARAPI_EUR
        fuente = "dolarapi-eur-oficial"
    else:
        url = DOLARAPI_OFICIAL
        fuente = "dolarapi-oficial"
    data = http_json(url)
    if not isinstance(data, dict):
        return None
    payload = payload_cotizacion(data, fecha, fuente)
    if payload is None:
        return None
    payload["fecha"] = fecha.isoformat()
    return payload


def obtener(fecha: date, moneda: str = "usd"):
    payload = desde_argentinadatos(fecha, moneda)
    if payload is not None:
        return payload
    return desde_dolarapi(fecha, moneda)


def main() -> int:
    parser = argparse.ArgumentParser(description="Cotización oficial BNA (dólar o euro)")
    parser.add_argument("--json", action="store_true", help="Salida JSON para cotizacion:leeapi")
    parser.add_argument("--fecha", required=True, help="YYYY-MM-DD")
    parser.add_argument("--moneda", default="usd", choices=("usd", "eur"), help="usd (default) o eur")
    args = parser.parse_args()
    instalar_proxy()

    try:
        fecha = parse_fecha(args.fecha)
    except ValueError:
        print(f"Fecha inválida: {args.fecha}", file=sys.stderr)
        return 2

    payload = obtener(fecha, args.moneda)
    if payload is None:
        print("No se pudo obtener cotización")
        return 1

    if args.json:
        print(json.dumps(payload, ensure_ascii=True))
    else:
        print(payload["venta"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
