@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([(object)[
        'nombreempresa' => $data->empresas->nombre ?? '',
    ]]));
    $columnas = $matriz['columnas'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $totales = $matriz['totales_asignacion'] ?? [];
    $cheques = $matriz['cheques']['por_clave'] ?? [];
    $diferencia = $matriz['diferencia'] ?? [];
    $chequesAsignados = $matriz['cheques_asignados'] ?? [];
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
        .neg { color: #c0392b; }
        .logos img { height: 32px; margin-right: 6px; }
        h3 { font-size: 10px; margin: 10px 0 4px; }
    </style>
</head>
<body>
    <div class="logos">
        @foreach($logos as $logo)
            <img src="{{ $logo['uri'] ?? '' }}" alt="{{ $logo['nombre'] ?? '' }}">
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
                <td><strong>Totales programa</strong></td>
                <td class="num"><strong>{{ number_format($matriz['total_saldo'] ?? 0, 2, ',', '.') }}</strong></td>
                @foreach($columnas as $col)
                    <td class="num"><strong>{{ number_format($totales[$col['clave']] ?? 0, 2, ',', '.') }}</strong></td>
                @endforeach
                <td class="num"><strong>{{ number_format($matriz['total_programa'] ?? 0, 2, ',', '.') }}</strong></td>
                <td></td>
            </tr>
            <tr>
                <td><strong>Cheques en cartera</strong></td>
                <td></td>
                @foreach($columnas as $col)
                    <td class="num">
                        @if ($col['anio_mes'] ?? null)
                            <strong>{{ number_format($cheques[$col['clave']] ?? 0, 2, ',', '.') }}</strong>
                        @else
                            —
                        @endif
                    </td>
                @endforeach
                <td class="num"><strong>{{ number_format($matriz['cheques']['total'] ?? 0, 2, ',', '.') }}</strong></td>
                <td></td>
            </tr>
            <tr>
                <td><strong>Diferencia (CHT − pagos)</strong></td>
                <td></td>
                @foreach($columnas as $col)
                    @php $dif = (float) ($diferencia[$col['clave']] ?? 0); @endphp
                    <td class="num {{ $dif < 0 ? 'neg' : '' }}">
                        <strong>{{ number_format($dif, 2, ',', '.') }}</strong>
                    </td>
                @endforeach
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @if (count($chequesAsignados) > 0)
        <h3>Cheques asignados ({{ count($chequesAsignados) }})</h3>
        <table class="data">
            <thead>
                <tr class="columnas">
                    <th>Mes</th>
                    <th>Proveedor</th>
                    <th>Nº interno</th>
                    <th>Nº cheque</th>
                    <th>Fecha pago</th>
                    <th>Banco</th>
                    <th class="num">Monto cheque</th>
                    <th class="num">Programado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($chequesAsignados as $asigCh)
                    <tr>
                        <td>{{ $asigCh['clave'] }}</td>
                        <td>{{ $asigCh['proveedor'] }}</td>
                        <td>{{ $asigCh['nro_interno_anita'] ?? '—' }}</td>
                        <td>{{ $asigCh['numerocheque'] }}</td>
                        <td>{{ ! empty($asigCh['fechapago']) ? \Carbon\Carbon::parse($asigCh['fechapago'])->format('d/m/Y') : '' }}</td>
                        <td>{{ $asigCh['banco'] }}</td>
                        <td class="num">{{ number_format($asigCh['monto'] ?? 0, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($asigCh['monto_programado'] ?? 0, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
