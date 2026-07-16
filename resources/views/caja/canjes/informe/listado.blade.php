<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Informe de canjes' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; margin-bottom: 8px; color: #444; }
        .logos img { height: 36px; margin-right: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background-color: #85C1E9;
            color: #17202A;
            border: 1px solid #cccccc;
            padding: 3px 4px;
            text-align: left;
        }
        table.data td {
            border: 1px solid #cccccc;
            padding: 2px 4px;
        }
        table.data tr:nth-child(even) td { background-color: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $tot = $totales ?? [];
@endphp
@if (! empty($logos))
<div class="logos">
    @foreach ($logos as $logo)
        <img src="{{ is_array($logo) ? ($logo['uri'] ?? '') : $logo }}" alt="logo">
    @endforeach
</div>
@endif
<h1>{{ $titulo ?? 'Informe de Datos de Ventas / Canjes' }}</h1>
<div class="meta">
    Generado {{ date('d/m/Y H:i') }}
    @if (! empty($subtitulo))
        — {{ $subtitulo }}
    @endif
    — {{ (int) ($tot['cantidad'] ?? count($filas)) }} tickets
    — Venta ${{ number_format((float) ($tot['monto_venta'] ?? 0), 2, ',', '.') }}
    — Ticket ${{ number_format((float) ($tot['monto_ticket'] ?? 0), 2, ',', '.') }}
</div>
@include('caja.canjes.informe.partials.tabla_datos', ['filas' => $filas, 'es_export' => true])
</body>
</html>
