<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Control CC vs mayor Anita' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #444; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 4px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; }
        .resumen td { padding: 2px 6px; }
    </style>
</head>
<body>
@if (!empty($mostrarCabecera))
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Generado {{ date('d/m/Y H:i') }}
        · sistema subdiario {{ $filtros['sistema_subdiario'] ?? '' }}
        · {{ count($filas ?? []) }} filas
    </div>
    @php $r = $resumen ?? []; $fmt = static fn ($n) => number_format((float)$n, 2, ',', '.'); @endphp
    <table class="resumen" style="margin-bottom:8px;">
        <tr>
            <td>Neto CC: <strong>{{ $fmt($r['cc_neto'] ?? 0) }}</strong></td>
            <td>Neto mayor: <strong>{{ $fmt($r['mayor_neto'] ?? 0) }}</strong></td>
            <td>Diff neto: <strong>{{ $fmt($r['diff_neto'] ?? 0) }}</strong></td>
            <td>Problemas: {{ (int)($r['filas_problema'] ?? 0) }}</td>
        </tr>
    </table>
@endif
@include('contable.cc_vs_mayor_anita.partials.tabla_datos', ['filas' => $filas ?? [], 'es_export' => true])
</body>
</html>
