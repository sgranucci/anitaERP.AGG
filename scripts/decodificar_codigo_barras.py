#!/usr/bin/env python3
"""Lee códigos de barras 1D/2D de una foto. Salida JSON a stdout."""

from __future__ import annotations

import json
import sys
from pathlib import Path

_VENDOR = Path(__file__).resolve().parent / "py_zxing"
if _VENDOR.is_dir():
    sys.path.insert(0, str(_VENDOR))


def _fail(mensaje: str, code: int = 1) -> int:
    print(json.dumps({"ok": False, "mensaje": mensaje, "codigos": []}, ensure_ascii=False))
    return code


def _agregar(vistos: dict[str, float], texto: str, y: float) -> None:
    codigo = "".join(str(texto or "").split())
    if not codigo:
        return
    if codigo not in vistos:
        vistos[codigo] = y


def _y_posicion(resultado) -> float:
    pos = getattr(resultado, "position", None)
    if pos is None:
        return 0.0
    for attr in ("top_left", "topLeft", "bottom_left", "bottomLeft"):
        p = getattr(pos, attr, None)
        if p is not None and hasattr(p, "y"):
            return float(p.y)
    return 0.0


def _leer(zxingcpp, imagen, vistos: dict[str, float]) -> None:
    for r in zxingcpp.read_barcodes(imagen):
        _agregar(vistos, getattr(r, "text", ""), _y_posicion(r))


def main(argv: list[str]) -> int:
    if len(argv) < 2:
        return _fail("Falta la ruta de la imagen.", 2)

    path = Path(argv[1])
    if not path.is_file():
        return _fail("No se encontró la imagen.")

    try:
        import zxingcpp
        from PIL import Image, ImageOps, ImageFilter
    except ImportError as exc:
        return _fail("Falta el lector de códigos en el servidor: " + str(exc))

    try:
        original = Image.open(path)
        original = ImageOps.exif_transpose(original)
        rgb = original.convert("RGB")
        gris = ImageOps.grayscale(rgb)
    except Exception as exc:
        return _fail("No se pudo abrir la foto: " + str(exc))

    vistos: dict[str, float] = {}
    _leer(zxingcpp, rgb, vistos)
    _leer(zxingcpp, gris, vistos)

    contrastada = ImageOps.autocontrast(gris)
    _leer(zxingcpp, contrastada, vistos)
    ancha, alta = gris.size
    if max(ancha, alta) < 1600:
        _leer(zxingcpp, gris.resize((ancha * 2, alta * 2), Image.Resampling.BICUBIC), vistos)
    # Franjas horizontales: varios códigos en un ticket
    for i in range(6):
        y0 = int(alta * i / 6)
        y1 = min(alta, int(alta * (i + 2) / 6))
        if y1 - y0 < 40:
            continue
        franja = gris.crop((0, y0, ancha, y1))
        _leer(zxingcpp, franja, vistos)

    if not vistos:
        nitida = gris.filter(ImageFilter.SHARPEN)
        _leer(zxingcpp, ImageOps.autocontrast(nitida), vistos)

    ordenados = [c for c, _y in sorted(vistos.items(), key=lambda item: item[1])]
    print(json.dumps({"ok": True, "codigos": ordenados, "cantidad": len(ordenados)}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
