@php
    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Cierre de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="7" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="7"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="7">{{ $subtitulo }}</td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Código</th>
            <th>Cuenta / Concepto</th>
            <th>Detalle</th>
            <th>Saldo ant. / Imp.</th>
            <th>Ingresos / Cobro</th>
            <th>Egresos / Pago</th>
            <th>Saldo actual</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php
                $tipoFila = $fila['tipo_fila'] ?? 'dato';
                $seccion = $fila['seccion'] ?? '';
            @endphp
            @if ($tipoFila === 'seccion')
                <tr>
                    <td colspan="7">{{ $fila['titulo'] ?? '' }}</td>
                </tr>
            @elseif ($seccion === 'saldos')
                <tr>
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td></td>
                    <td>{{ number_format((float) ($fila['saldo_anterior'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['ingresos'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['egresos'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['saldo_actual'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @elseif ($seccion === 'cobro_pago')
                <tr>
                    <td>{{ $fila['aplicacion'] ?? '' }}</td>
                    <td></td>
                    <td></td>
                    <td>{{ number_format((float) ($fila['cobro'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['pago'] ?? 0), 2, ',', '.') }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['codigo'] ?? ($fila['nro_interno'] ?? ($fila['numerocheque'] ?? '')) }}</td>
                    <td>{{ $fila['nombre'] ?? ($fila['cliente_nombre'] ?? ($fila['cuenta_nombre'] ?? '')) }}</td>
                    <td>{{ $fila['banco'] ?? ($fila['fecha'] ?? '') }}</td>
                    <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
