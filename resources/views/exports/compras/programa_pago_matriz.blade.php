@php
    $columnas = $matriz['columnas'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $totales = $matriz['totales_asignacion'] ?? [];
    $cheques = $matriz['cheques']['por_clave'] ?? [];
    $diferencia = $matriz['diferencia'] ?? [];
    $chequesAsignados = $matriz['cheques_asignados'] ?? [];
    $cols = 3 + count($columnas) + 2;
    $fmt = static fn ($n): string => number_format((float) $n, 2, '.', '');
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
            <td><strong>Totales programa</strong></td>
            <td><strong>{{ $fmt($matriz['total_saldo'] ?? 0) }}</strong></td>
            @foreach($columnas as $col)
                <td><strong>{{ $fmt($totales[$col['clave']] ?? 0) }}</strong></td>
            @endforeach
            <td><strong>{{ $fmt($matriz['total_programa'] ?? 0) }}</strong></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td><strong>Cheques en cartera</strong></td>
            <td></td>
            @foreach($columnas as $col)
                <td>
                    @if ($col['anio_mes'] ?? null)
                        <strong>{{ $fmt($cheques[$col['clave']] ?? 0) }}</strong>
                    @else
                        —
                    @endif
                </td>
            @endforeach
            <td><strong>{{ $fmt($matriz['cheques']['total'] ?? 0) }}</strong></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td><strong>Diferencia (CHT − pagos)</strong></td>
            <td></td>
            @foreach($columnas as $col)
                <td><strong>{{ $fmt($diferencia[$col['clave']] ?? 0) }}</strong></td>
            @endforeach
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>
@if (count($chequesAsignados) > 0)
<table>
    <tr>
        <td colspan="8"></td>
    </tr>
    <tr>
        <td colspan="8"><strong>Cheques asignados ({{ count($chequesAsignados) }})</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Mes</th>
            <th>Proveedor</th>
            <th>Nº interno</th>
            <th>Nº cheque</th>
            <th>Fecha pago</th>
            <th>Banco</th>
            <th>Monto cheque</th>
            <th>Programado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($chequesAsignados as $asigCh)
            <tr>
                <td>{{ $asigCh['clave'] }}</td>
                <td>{{ $asigCh['proveedor'] }}</td>
                <td>{{ $asigCh['nro_interno_anita'] ?? '' }}</td>
                <td>{{ $asigCh['numerocheque'] }}</td>
                <td>{{ ! empty($asigCh['fechapago']) ? \Carbon\Carbon::parse($asigCh['fechapago'])->format('d/m/Y') : '' }}</td>
                <td>{{ $asigCh['banco'] }}</td>
                <td>{{ $fmt($asigCh['monto'] ?? 0) }}</td>
                <td>{{ $fmt($asigCh['monto_programado'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
