@php
    $esTotal = ($tipolistado ?? ($filtros['tipolistado'] ?? 'TOTAL')) === 'TOTAL';
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerPedido = $puede_ver_pedido ?? false;
    $puedeVerCliente = $puede_ver_cliente ?? false;
    $puedeVerArticulo = $puede_ver_articulo ?? false;
    $puedeVerTransporte = $puede_ver_transporte ?? false;
    $paraPdf = $para_pdf ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
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
        <th class="text-right">Piezas</th>
        <th class="text-right">Kilos teóricos</th>
        <th class="text-right">Kilos pesados</th>
        <th class="text-right">Cajas</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'subtotal_reparto')
            <tr class="font-weight-bold" style="background-color: #e9ecef;">
                <td>
                    @if (! $paraPdf && $puedeVerTransporte && (int) ($fila['transporte_id'] ?? 0) > 0)
                        <a href="{{ route('editar_transporte', array_merge(['id' => $fila['transporte_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['codigotransporte'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['codigotransporte'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['nombretransporte'] ?? '' }}</td>
                <td colspan="{{ $esTotal ? 6 : 2 }}">Subtotal reparto</td>
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @elseif ($tipo === 'total_final')
            <tr class="font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="{{ $esTotal ? 8 : 4 }}">TOTAL FINAL</td>
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @else
            <tr>
                <td></td>
                <td></td>
                @if ($esTotal)
                    <td>
                        @if (! $paraPdf && $puedeVerCliente && (int) ($fila['cliente_id'] ?? 0) > 0)
                            <a href="{{ route('editar_cliente', array_merge(['id' => $fila['cliente_id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['codigocliente'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['codigocliente'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['nombrecliente'] ?? '' }}</td>
                    <td>
                        @if (! $paraPdf && $puedeVerPedido && (int) ($fila['pedido_id'] ?? 0) > 0)
                            <a href="{{ route('editar_pedido', array_merge(['id' => $fila['pedido_id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['codigopedido'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['codigopedido'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['fechaentrega'] ?? '' }}</td>
                    <td>{{ $fila['nombrelocalidad'] ?? '' }}</td>
                    <td>{{ $fila['nombreprovincia'] ?? '' }}</td>
                @else
                    <td>
                        @if (! $paraPdf && $puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                            <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="text-primary">
                                {{ $fila['sku'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['sku'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                @endif
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_pesada'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="{{ $esTotal ? 12 : 8 }}" class="text-center text-muted">Sin registros</td>
        </tr>
    @endforelse
</tbody>
