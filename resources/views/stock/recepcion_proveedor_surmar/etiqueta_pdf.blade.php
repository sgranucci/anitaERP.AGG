<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta Surmar #{{ $etiquetaId }}</title>
    <style>
        @page { margin: 8px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #17202A;
            margin: 0;
            padding: 6px;
        }
        .wrap { position: relative; width: 100%; }
        .qr {
            position: absolute;
            top: 0;
            right: 0;
            width: 90px;
            height: 90px;
        }
        .qr img { width: 90px; height: 90px; }
        .art { font-size: 14px; font-weight: bold; padding-right: 100px; }
        .prov { margin: 6px 0 8px; padding-right: 100px; }
        .desc { font-size: 15px; font-weight: bold; margin-bottom: 10px; min-height: 2.2em; clear: both; }
        .pesos { width: 100%; margin-bottom: 8px; }
        .pesos td { vertical-align: top; width: 50%; }
        .pesos .lbl { font-size: 9px; color: #555; }
        .pesos .val { font-size: 18px; font-weight: bold; }
        .separa { font-weight: bold; margin: 6px 0; }
        .barcode { margin-top: 14px; text-align: center; }
        .barcode img { max-width: 100%; height: 52px; }
        .id { margin-top: 4px; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
@php
    $p = $preview ?? [];
    $prov = trim((string) ($p['proveedor'] ?? ''));
    $qrB64 = (string) ($p['qr_png_base64'] ?? '');
    $bcB64 = (string) ($p['barcode_png_base64'] ?? '');
@endphp
<div class="wrap">
    @if ($qrB64 !== '')
        <div class="qr">
            <img src="data:image/png;base64,{{ $qrB64 }}" alt="QR">
        </div>
    @endif
    <div class="art">Articulo: {{ $p['codigo_articulo'] ?? '—' }}</div>
    <div class="prov">Prov: {{ $prov !== '' ? $prov : '—' }}</div>
    <div class="desc">{{ $p['descripcion'] ?? '—' }}</div>
    <table class="pesos">
        <tr>
            <td>
                <div class="lbl">PESO BRUTO</div>
                <div class="val">{{ $p['peso_bruto'] ?? '0.00' }}</div>
            </td>
            <td>
                <div class="lbl">PESO NETO</div>
                <div class="val">{{ $p['peso_neto'] ?? '0.00' }}</div>
            </td>
        </tr>
    </table>
    <div>PIEZAS: {{ $p['cant_pieza'] ?? '0.00' }}
        @if (! empty($p['peso_promedio']))
            &nbsp;&nbsp;Prom: {{ $p['peso_promedio'] }}
        @endif
    </div>
    <div class="separa">{{ $p['linea_separa'] ?? '—' }}</div>
    <div>Fecha : {{ $p['fecha'] ?? '—' }}</div>
    <div>F.Vto.: {{ $p['fecha_vto'] ?? '—' }}</div>
    <div>Lote Nro.: {{ $p['lote'] ?? '—' }}</div>
    @if ($bcB64 !== '')
        <div class="barcode">
            <img src="data:image/png;base64,{{ $bcB64 }}" alt="Barcode">
        </div>
    @endif
    <div class="id">ID: {{ $p['id'] ?? $etiquetaId }}</div>
</div>
</body>
</html>
