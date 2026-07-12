<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 16px; margin: 0 0 6px 0; }
        h2 { font-size: 11px; margin: 12px 0 4px 0; color: #1A5276; }
        .meta { font-size: 8px; margin-bottom: 10px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data th { background: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #cccccc; }
        table.data td { padding: 3px 4px; border: 1px solid #cccccc; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .text-right { text-align: right; }
        .totales { font-weight: bold; font-size: 10px; margin-top: 8px; }
    </style>
</head>
<body>
@php
    $empresaNombre = $empresa->nombre ?? '';
    $titulo = !empty($es_historico) ? 'Flash Report (histórico)' : 'Flash Report';
@endphp
<h1>{{ $titulo }}</h1>
<p class="meta">
    {{ $empresaNombre }} &mdash; {{ $fecha }}<br>
    Generado {{ date('d/m/Y H:i') }}
    @if(!empty($es_historico) && !empty($cantidad_dias))
        &mdash; {{ $cantidad_dias }} d&iacute;a(s) con datos
    @endif
</p>

@include('caja.flash.partials.contenido_reporte')

@if(!empty($flash->comentario) && empty($es_historico))
    <p class="meta">Comentario: {{ $flash->comentario }}</p>
@endif
</body>
</html>
