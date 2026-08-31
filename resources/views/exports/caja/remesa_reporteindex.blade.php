@php
    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Remesas por cuenta de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="9" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="9"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="9">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="9">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ((int) ($resultado['total_movimientos'] ?? 0) > 0)
        <tr>
            <td colspan="9">
                {{ (int) $resultado['total_movimientos'] }} movimientos
                · Origen {{ number_format((float) ($resultado['total_importe_origen'] ?? 0), 2, ',', '.') }}
                · Importe {{ number_format((float) ($resultado['total_importe'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Remesa</th>
            <th>Mon</th>
            <th>Cotizaci&oacute;n</th>
            <th>Importe origen</th>
            <th>Importe</th>
            <th>Estado</th>
            <th>Empr.</th>
            <th>Origen</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
            @if ($tipoFila === 'grupo')
                <tr>
                    <td colspan="9">Cuenta: {{ $fila['cuenta_etiqueta'] ?? '' }}</td>
                </tr>
            @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
                <tr>
                    <td colspan="4">{{ $fila['cuenta_etiqueta'] ?? 'Total' }}</td>
                    <td>{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['fecha'] ?? '' }}</td>
                    <td>{{ $fila['remesa_nro'] ?? '' }}</td>
                    <td>{{ $fila['moneda'] ?? '' }}</td>
                    <td>{{ number_format((float) ($fila['cotizacion'] ?? 0), 4, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $fila['estado'] ?? '' }}</td>
                    <td>{{ $fila['empresa_id'] ?? '' }}</td>
                    <td>{{ $fila['fuente'] ?? '' }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
