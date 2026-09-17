@php
    $columnas = $matriz['columnas'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $totales = $matriz['totales_asignacion'] ?? [];
    $cols = 3 + count($columnas) + 2;
@endphp
<table>
    <tr>
        <td colspan="{{ $cols }}"><strong>{{ $data->titulo ?: ('Programa #'.$data->id) }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $cols }}">
            Generado {{ date('d/m/Y H:i') }} ·
            {{ $data->empresas->nombre ?? '' }} ·
            Fecha base {{ optional($data->fecha_base)->format('d/m/Y') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Código</th>
            <th>Proveedor</th>
            <th>Saldo</th>
            @foreach($columnas as $col)
                <th>{{ $col['etiqueta'] }}</th>
            @endforeach
            <th>Total</th>
            <th>Obs.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td>{{ $fila['saldo_adeudado'] }}</td>
                @foreach($columnas as $col)
                    <td>{{ $fila['asignaciones'][$col['clave']] ?? 0 }}</td>
                @endforeach
                <td>{{ $fila['total_fila'] }}</td>
                <td>{{ $fila['observacion'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td>Totales</td>
            <td>{{ $matriz['total_saldo'] ?? 0 }}</td>
            @foreach($columnas as $col)
                <td>{{ $totales[$col['clave']] ?? 0 }}</td>
            @endforeach
            <td>{{ $matriz['total_programa'] ?? 0 }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
