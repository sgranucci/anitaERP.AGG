<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; margin-bottom: 8px; }
        table.data th, table.data td { padding: 2px 3px; border: 1px solid #cccccc; }
        table.data th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $empresaNombre = $empresa->nombre ?? '';
    $titulo = $titulo ?? 'Consolidated Income';
@endphp
<h1>{{ $titulo }}</h1>
<p class="meta">
    {{ $empresaNombre }} &mdash; {{ $fecha }}
    @if(!empty($through_day))
        &mdash; Through day: {{ $through_day }}
    @endif
    <br>
    Generado {{ date('d/m/Y H:i') }}
    @if(!empty($es_historico) && !empty($cantidad_dias))
        &mdash; {{ $cantidad_dias }} d&iacute;a(s) con datos
    @endif
</p>

@include('caja.flash.partials.tabla_consolidated_income', [
    'reporte' => [
        'budget_mes' => $budget_mes ?? [],
        'filas_diarias' => $filas_diarias ?? [],
        'total_final' => $total_final ?? null,
        'mtd_average' => $mtd_average ?? null,
        'mtd_resta_season' => $mtd_resta_season ?? null,
        'mtd_resta_budget' => $mtd_resta_budget ?? null,
        'comparativo_mes_ant' => $comparativo_mes_ant ?? null,
        'comparativo_anio_ant' => $comparativo_anio_ant ?? null,
        'cantidad_dias' => $cantidad_dias ?? count($filas_diarias ?? []),
        'through_day' => $through_day ?? null,
        'con_season' => $con_season ?? true,
    ],
    'modo_pdf' => true,
])

@if(!empty($flash->comentario) && empty($es_historico))
    <p class="meta">Comentario: {{ $flash->comentario }}</p>
@endif
</body>
</html>
