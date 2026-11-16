<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consulta Inteligente — pregunta</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; }
        .logos { margin-bottom: 8px; }
        .logos img { height: 36px; margin-right: 8px; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { color: #566573; margin-bottom: 10px; }
        .box { border: 1px solid #85C1E9; background: #f4f9fc; padding: 8px 10px; margin-bottom: 10px; border-radius: 3px; }
        .kicker { font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #2471A3; font-weight: bold; }
        .narrativa { font-size: 11px; font-weight: bold; margin-top: 4px; }
        ul { margin: 4px 0 0 14px; padding: 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #85C1E9; color: #17202A; font-weight: bold; border: 1px solid #7FB3D5; padding: 4px 5px; text-align: left; }
        table.data td { border: 1px solid #cccccc; padding: 3px 5px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .foot { margin-top: 10px; color: #566573; font-size: 8px; }
    </style>
</head>
<body>
    @if (! empty($logos))
        <div class="logos">
            @foreach ($logos as $logo)
                @if (! empty($logo['uri']))
                    <img src="{{ $logo['uri'] }}" alt="">
                @endif
            @endforeach
        </div>
    @endif

    <h1>Consulta Inteligente — pregunta</h1>
    <div class="meta">Generado {{ $generado }} · {{ $entidadNombre }}</div>

    <div class="box">
        <div class="kicker">Pregunta</div>
        <div>{{ $pregunta }}</div>
        @if (! empty($narrativa['texto']))
            <div class="kicker" style="margin-top:8px;">Respuesta</div>
            <div class="narrativa">{{ $narrativa['texto'] }}</div>
        @endif
        @if ($explicacion !== '')
            <div class="kicker" style="margin-top:8px;">QBE</div>
            <div>{{ $explicacion }}</div>
        @endif
        @if (! empty($qbeLegible))
            <ul>
                @foreach ($qbeLegible as $linea)
                    <li>{{ $linea }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($filas !== [])
        <div class="kicker">Muestra ({{ $totalMostrado }} filas)</div>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columnas as $col)
                        <th>{{ $etiquetas[$col] ?? $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        @foreach ($columnas as $col)
                            <td>{{ $fila[$col] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Sin filas en la muestra (el filtro puede estar vacío o sin permiso de detalle).</p>
    @endif

    @if ($urlExplorar !== '')
        <div class="foot">Explorador: {{ $urlExplorar }}</div>
    @endif
</body>
</html>
