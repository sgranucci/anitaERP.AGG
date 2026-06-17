@php
    $esTotal = ($filtros['tipolistado'] ?? 'TOTAL') === 'TOTAL';
    $colspan = $esTotal ? 12 : 8;
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <h2 style="margin: 0; font-size: 16pt; font-weight: bold;">{{ $titulo ?? 'Kilos pedidos' }}</h2>
                @if (!empty($subtitulo))
                    <div style="font-size: 10pt; color: #444;">{{ $subtitulo }}</div>
                @endif
            </td>
        </tr>
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
