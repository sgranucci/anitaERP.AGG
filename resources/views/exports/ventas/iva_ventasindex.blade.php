@php
    $filaLogo = $reservarFilaLogoExcel ?? false;
    $filaTitulo = $filaLogo ? 2 : 1;
    $filaCabecera = $filaLogo ? 4 : 3;
@endphp
<table>
    @if ($filaLogo)
        <tr>
            <td colspan="16"></td>
        </tr>
    @endif
    <tr>
        <td colspan="16" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'IVA VENTAS' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="16">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <th>Cliente</th>
        <th>Nombre</th>
        <th>CUIT</th>
        <th>Fecha</th>
        <th>PV</th>
        @if (! empty($clasificar_por_host))
            <th>Host</th>
        @endif
        <th>Tipo</th>
        <th>Comprobante</th>
        @foreach ($resultado['columnas'] ?? [] as $col)
            <th>{{ $col['label'] }}</th>
        @endforeach
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['cliente_codigo'] ?? '' }}</td>
            <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
            <td>{{ $fila['cuit'] ?? '' }}</td>
            <td>{{ $fila['fecha_mov'] ?? '' }}</td>
            <td>{{ $fila['puntoventa_codigo'] ?? '' }}</td>
            @if (! empty($clasificar_por_host))
                <td>{{ $fila['host'] ?? '' }}</td>
            @endif
            <td>{{ $fila['tipo'] ?? '' }}</td>
            <td>{{ $fila['comprobante'] ?? '' }}</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ number_format((float) ($fila['columnas'][$col['key']] ?? 0), 2, ',', '.') }}</td>
            @endforeach
        </tr>
    @endforeach
    @if (! empty($resultado['totales_general']))
        <tr>
            <td colspan="{{ empty($clasificar_por_host) ? 7 : 8 }}">TOTAL GENERAL</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ number_format((float) ($resultado['totales_general'][$col['key']] ?? 0), 2, ',', '.') }}</td>
            @endforeach
        </tr>
    @endif
</table>
