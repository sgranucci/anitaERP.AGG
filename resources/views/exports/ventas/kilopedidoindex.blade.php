@php
    $esTotal = ($filtros['tipolistado'] ?? 'TOTAL') === 'TOTAL';
    $colspan = $esTotal ? 12 : 8;
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Kilos pedidos' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
        @if (! empty($totales))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Piezas: {{ $formatear($totales['total_pieza'] ?? 0) }}
                    &middot; Kilos te&oacute;ricos: {{ $formatear($totales['total_kilo'] ?? 0) }}
                    &middot; Kilos pesados: {{ $formatear($totales['total_pesada'] ?? 0) }}
                    &middot; Cajas: {{ $formatear($totales['total_caja'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (($total_lineas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    L&iacute;neas detalle: {{ (int) $total_lineas }}
                </td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>Reparto</th>
            <th>Nombre reparto</th>
            @if ($esTotal)
                <th>Cliente</th>
                <th>Nombre</th>
                <th>Pedido</th>
                <th>Fecha entrega</th>
                <th>Localidad</th>
                <th>Provincia</th>
            @else
                <th>Artículo</th>
                <th>Descripción</th>
            @endif
            <th>Piezas</th>
            <th>Kilos teóricos</th>
            <th>Kilos pesados</th>
            <th>Cajas</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
            @if ($tipo === 'subtotal_reparto')
                <tr style="font-weight: bold; background-color: #e9ecef;">
                    <td>{{ $fila['codigotransporte'] ?? '' }}</td>
                    <td>{{ $fila['nombretransporte'] ?? '' }}</td>
                    <td colspan="{{ $esTotal ? 6 : 2 }}">Subtotal reparto</td>
                    <td>{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_caja'] ?? 0) }}</td>
                </tr>
            @elseif ($tipo === 'total_final')
                <tr style="font-weight: bold; background-color: #d6eaf8;">
                    <td colspan="{{ $esTotal ? 8 : 4 }}">TOTAL FINAL</td>
                    <td>{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_caja'] ?? 0) }}</td>
                </tr>
            @else
                <tr>
                    <td></td>
                    <td></td>
                    @if ($esTotal)
                        <td>{{ $fila['codigocliente'] ?? '' }}</td>
                        <td>{{ $fila['nombrecliente'] ?? '' }}</td>
                        <td>{{ $fila['codigopedido'] ?? '' }}</td>
                        <td>{{ $fila['fechaentrega'] ?? '' }}</td>
                        <td>{{ $fila['nombrelocalidad'] ?? '' }}</td>
                        <td>{{ $fila['nombreprovincia'] ?? '' }}</td>
                    @else
                        <td>{{ $fila['sku'] ?? '' }}</td>
                        <td>{{ $fila['descripcion'] ?? '' }}</td>
                    @endif
                    <td>{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                    <td>{{ $formatear($fila['total_caja'] ?? 0) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
