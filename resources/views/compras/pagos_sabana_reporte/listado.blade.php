<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Pagos' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        h1 { font-size: 12px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; margin-bottom: 6px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; vertical-align: top; }
        table.data thead th { background: #85C1E9; color: #17202A; font-weight: bold; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; white-space: nowrap; }
        .logos img { max-height: 36px; margin-right: 8px; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filas ?? [])->map(function ($f) {
        return (object) ['nombreempresa' => $f['nombreempresa'] ?? ''];
    }));
@endphp
<div class="logos">
    @foreach ($logos as $logo)
        @if (! empty($logo['uri']))
            <img src="{{ $logo['uri'] }}" alt="">
        @endif
    @endforeach
</div>
<h1>{{ $titulo ?? 'Pagos x Fecha de Movimiento' }}</h1>
<div class="meta">
    Generado {{ date('d/m/Y H:i') }}
    @if (! empty($subtitulo))
        <br>{{ $subtitulo }}
    @endif
    <br>Registros: {{ (int) ($totales['cantidad'] ?? 0) }}
    &middot; Total pago: {{ number_format((float) ($totales['total_pago'] ?? 0), 2, ',', '.') }}
</div>

@include('compras.pagos_sabana_reporte.partials.tabla_datos', [
    'filas' => $filas ?? [],
    'columnas' => $columnas ?? [],
    'totales' => $totales ?? [],
    'para_export' => true,
    'puede_ver_proveedor' => false,
    'puede_ver_pagoproveedor' => false,
    'puede_ver_ingresoegreso' => false,
    'puede_ver_comprobante' => false,
    'puede_ver_ordencompra' => false,
    'puede_ver_solicitudpago' => false,
])
</body>
</html>
