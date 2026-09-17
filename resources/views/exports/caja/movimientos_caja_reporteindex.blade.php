@php
    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Movimientos de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="11" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="11"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="11">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="11">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ((int) ($resultado['total_registros'] ?? 0) > 0)
        <tr>
            <td colspan="11">
                {{ (int) $resultado['total_registros'] }} registros
                · Ingresos {{ number_format((float) ($resultado['total_ingreso'] ?? 0), 2, ',', '.') }}
                · Egresos {{ number_format((float) ($resultado['total_egreso'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Nro.</th>
            <th>Tip</th>
            <th>Código</th>
            <th>Cliente / Proveedor</th>
            <th>Concepto</th>
            <th>Detalle</th>
            <th>Estado</th>
            <th>Ingreso</th>
            <th>Egreso</th>
            <th>Empresa</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
            @if ($tipoFila === 'grupo')
                <tr>
                    <td colspan="11">Cuenta: {{ $fila['cuenta_etiqueta'] ?? '' }}</td>
                </tr>
            @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
                <tr>
                    <td colspan="8">{{ $fila['cuenta_etiqueta'] ?? ($fila['clipro_nombre'] ?? 'Total') }}</td>
                    <td>{{ number_format((float) ($fila['ingreso'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['egreso'] ?? 0), 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['fecha'] ?? '' }}</td>
                    <td>{{ $fila['numero'] ?? '' }}</td>
                    <td>{{ $fila['tipo_abrev'] ?? '' }}</td>
                    <td>{{ $fila['clipro_codigo'] ?? '' }}</td>
                    <td>{{ $fila['clipro_nombre'] ?? '' }}</td>
                    <td>{{ $fila['concepto'] ?? '' }}</td>
                    <td>{{ $fila['detalle'] ?? '' }}</td>
                    <td>{{ $fila['estado_nombre'] ?? '' }}</td>
                    <td>{{ (float) ($fila['ingreso'] ?? 0) > 0 ? number_format((float) $fila['ingreso'], 2, ',', '.') : '' }}</td>
                    <td>{{ (float) ($fila['egreso'] ?? 0) > 0 ? number_format((float) $fila['egreso'], 2, ',', '.') : '' }}</td>
                    <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
