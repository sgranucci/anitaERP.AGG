<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir — Presupuesto req. {{ $req->numerorequisicion }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #222; margin: 16px; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .cabecera td { width: 25%; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .muted { color: #555; font-size: 9px; }
        .pdf-cabecera { margin-bottom: 10px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa { max-width: 200px; max-height: 90px; }
        .items th, .items td { font-size: 10px; padding: 4px 5px; word-wrap: break-word; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .cen { text-align: center; }
        .bloque-texto { white-space: pre-wrap; word-wrap: break-word; max-width: 100%; }
        .subtotal { font-weight: bold; background: #f5f5f5; }
        .barra-acciones { margin-bottom: 16px; padding: 10px; background: #f4f4f4; border: 1px solid #ccc; }
        .barra-acciones button, .barra-acciones a.btn-link {
            margin-right: 8px; padding: 6px 12px; cursor: pointer;
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 8px; }
        }
    </style>
</head>
<body>
<div class="barra-acciones no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="{{ $urlPdf }}" class="btn-link">Descargar PDF</a>
    <a href="javascript:window.close()" class="btn-link">Cerrar</a>
</div>

@include('compras.requisicion.partials.presupuesto_documento')

</body>
</html>
