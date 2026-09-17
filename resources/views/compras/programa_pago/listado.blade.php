@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object)[
        'nombreempresa' => $data->empresas->nombre ?? '',
    ]]));
    $columnas = $matriz['columnas'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $totales = $matriz['totales_asignacion'] ?? [];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 2px; }
        table.data td { border: 1px solid #cccccc; padding: 2px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
        .num { text-align: right; }
        .logos img { height: 32px; margin-right: 6px; }
    </style>
</head>
<body>
    <div class="logos">
        @foreach($logos as $logo)
            <img src="{{ $logo }}">
        @endforeach
    </div>
    <h2>{{ $data->titulo ?: ('Programa #'.$data->id) }}</h2>
    <p>
        Generado {{ date('d/m/Y H:i') }} ·
        Empresa {{ $data->empresas->nombre ?? '' }} ·
        Fecha base {{ optional($data->fecha_base)->format('d/m/Y') }} ·
        {{ count($filas) }} proveedores
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>Proveedor</th>
                <th class="num">Saldo</th>
                @foreach($columnas as $col)
                    <th class="num">{{ $col['etiqueta'] }}</th>
                @endforeach
                <th class="num">Total</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }} {{ $fila['nombre'] }}</td>
                    <td class="num">{{ number_format($fila['saldo_adeudado'], 2, ',', '.') }}</td>
                    @foreach($columnas as $col)
                        <td class="num">{{ number_format($fila['asignaciones'][$col['clave']] ?? 0, 2, ',', '.') }}</td>
                    @endforeach
                    <td class="num">{{ number_format($fila['total_fila'], 2, ',', '.') }}</td>
                    <td>{{ $fila['observacion'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Totales</strong></td>
                <td class="num"><strong>{{ number_format($matriz['total_saldo'] ?? 0, 2, ',', '.') }}</strong></td>
                @foreach($columnas as $col)
                    <td class="num"><strong>{{ number_format($totales[$col['clave']] ?? 0, 2, ',', '.') }}</strong></td>
                @endforeach
                <td class="num"><strong>{{ number_format($matriz['total_programa'] ?? 0, 2, ',', '.') }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
