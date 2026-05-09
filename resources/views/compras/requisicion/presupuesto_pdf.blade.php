<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Presupuesto requisición {{ $req->numerorequisicion }} #{{ $detalle['id'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .cabecera td { width: 25%; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .pdf-cabecera { margin-bottom: 10px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa { max-width: 200px; max-height: 90px; }
        .items th, .items td { font-size: 10px; padding: 4px 5px; word-wrap: break-word; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .cen { text-align: center; }
        .bloque-texto { white-space: pre-wrap; word-wrap: break-word; max-width: 100%; }
        .subtotal { font-weight: bold; background: #f5f5f5; }
    </style>
</head>
<body>
@include('compras.requisicion.partials.presupuesto_documento')
</body>
</html>
