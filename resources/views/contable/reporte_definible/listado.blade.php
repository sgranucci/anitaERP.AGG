<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reporte->nombre ?? 'Reporte definible' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { margin-bottom: 10px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 4px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .negrita { font-weight: bold; }
        .right { text-align: right; }
        .notas { margin-top: 10px; font-size: 7px; color: #17202A; }
        .notas h2 { font-size: 9px; margin: 0 0 3px; }
        .notas table { width: 100%; border-collapse: collapse; }
        .notas td { padding: 1px 3px; vertical-align: top; }
        .notas td.marca { width: 14px; font-weight: bold; }
        sup { font-size: 6px; font-weight: bold; }
    </style>
</head>
<body>
@php
    $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object)['nombreempresa'=>'']]));
    $columnas = $resultado['columnas'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $notas = $resultado['notas'] ?? [];
    $marcasNota = $resultado['notas_marcas'] ?? [];
@endphp
@if (!empty($logos))
    <div style="margin-bottom:8px;">
        @foreach ($logos as $logo)
            @if (!empty($logo['uri']))
                <img src="{{ $logo['uri'] }}" style="height:40px;margin-right:8px;">
            @endif
        @endforeach
    </div>
@endif
<h1>{{ $reporte->titulo1 ?: $reporte->nombre }}</h1>
@if ($reporte->titulo2)
    <div>{{ $reporte->titulo2 }}</div>
@endif
<div class="meta">
    Generado {{ date('d/m/Y H:i') }} · Período {{ $periodo_texto ?? '' }} · {{ count($filas) }} líneas
    @if (!empty($resultado['fuente']))
        · Fuente {{ $resultado['fuente'] }}
    @endif
</div>
<table class="data">
    <thead>
        <tr>
            <th>Código</th>
            <th>Concepto</th>
            @foreach ($columnas as $col)
                <th class="right">{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php
                $marca = ($fila['kind'] ?? 'rubro') === 'cuenta'
                    ? null
                    : ($marcasNota[strtoupper(trim((string) ($fila['codigo'] ?? '')))]
                        ?? ($marcasNota['#'.(int) ($fila['rubro_id'] ?? 0)] ?? null));
            @endphp
            <tr class="{{ !empty($fila['negrita']) ? 'negrita' : '' }}">
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>
                    <span style="padding-left:{{ (int)($fila['depth'] ?? 0) * 8 }}px"></span>
                    {{ $fila['nombre'] ?? '' }}@if ($marca)<sup>{{ $marca }}</sup>@endif
                </td>
                @if ($fila['saldos'] === null)
                    @foreach ($columnas as $col)
                        <td></td>
                    @endforeach
                @else
                    @foreach ($columnas as $col)
                        @php
                            $key = $col['key'] ?? '';
                            $v = $fila['saldos'][$key] ?? null;
                            $esPct = ($col['tipo'] ?? '') === 'var_pct';
                        @endphp
                        <td class="right">
                            @if ($v === null)
                            @elseif ($esPct)
                                {{ number_format((float)$v, 1, ',', '.') }}%
                            @else
                                {{ abs((float)$v) < 0.005 ? '' : number_format((float)$v, 2, ',', '.') }}
                            @endif
                        </td>
                    @endforeach
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
@if (!empty($notas))
    <div class="notas">
        <h2>Notas</h2>
        <table>
            @foreach ($notas as $nota)
                <tr>
                    <td class="marca">{{ (int) $nota['marca'] }}</td>
                    <td>
                        @if (!empty($nota['codigo_linea'])){{ $nota['codigo_linea'] }} — @endif{{ $nota['texto'] }}
                        @if (!empty($nota['vigencia_texto']) && $nota['vigencia_texto'] !== 'Siempre')
                            ({{ $nota['vigencia_texto'] }})
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
</body>
</html>
