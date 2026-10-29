@php
    $paraExcel = (bool) ($para_excel ?? false);
    $puedePedido = (bool) ($puede_ver_pedido ?? false);
    $puedeCliente = (bool) ($puede_ver_cliente ?? false);
    $puedeArticulo = (bool) ($puede_ver_articulo ?? false);
    $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $fmt = static function ($valor, int $decimales = 2) use ($paraExcel): string {
        if ($valor === null || $valor === '') {
            return '';
        }

        return $paraExcel
            ? number_format((float) $valor, $decimales, '.', '')
            : number_format((float) $valor, $decimales, ',', '.');
    };
@endphp
@forelse ($filas as $fila)
    @php
        $tipo = $fila['tipo_fila'] ?? 'detalle';
        $esCorte = $tipo !== 'detalle';
        $fondo = $tipo === 'total' ? '#AED6F1' : ($tipo === 'subtotal' ? '#D6EAF8' : '');
    @endphp
    <tr @if ($esCorte) class="font-weight-bold" style="background:{{ $fondo }};" @endif>
        <td>
            @if (! $paraExcel && ! $esCorte && $puedeCliente && (int) ($fila['cliente_id'] ?? 0) > 0)
                <a href="{{ route('editar_cliente', array_merge(['id' => $fila['cliente_id']], $consulta)) }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['cliente_codigo'] }}</a>
            @else
                {{ $fila['cliente_codigo'] }}
            @endif
        </td>
        <td>{{ $esCorte ? ($fila['etiqueta'] ?: $fila['cliente_nombre']) : $fila['cliente_nombre'] }}</td>
        <td>
            @if (! $paraExcel && ! $esCorte && $puedeArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $consulta)) }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['articulo_sku'] }}</a>
            @else
                {{ $fila['articulo_sku'] }}
            @endif
        </td>
        <td>{{ $fila['articulo_desc'] }}</td>
        <td>{{ $fila['tipo'] }}</td>
        <td>
            @if (! $paraExcel && ! $esCorte && $puedePedido && (int) ($fila['pedido_id'] ?? 0) > 0)
                <a href="{{ route('editar_pedido', array_merge(['id' => $fila['pedido_id']], $consulta)) }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['numero'] }}</a>
            @else
                {{ $fila['numero'] }}
            @endif
        </td>
        <td>{{ $fila['fecha_pedido'] }}</td>
        <td>{{ $fila['fecha_entrega'] }}</td>
        <td class="text-right">{{ $fila['atraso'] === null ? '' : $fila['atraso'] }}</td>
        <td class="text-right">{{ $fmt($fila['cant_uni']) }}</td>
        <td class="text-right">{{ $fmt($fila['cant_kg']) }}</td>
        <td>{{ $fila['umd'] }}</td>
        <td class="text-right">{{ $fmt($fila['cant_entregada']) }}</td>
        <td class="text-right">{{ $fmt($fila['cant_pendiente']) }}</td>
        <td>{{ $fila['moneda'] }}</td>
        <td class="text-right">{{ $fmt($fila['precio'], 4) }}</td>
        <td class="text-right">{{ $fmt($fila['virgen']) }}</td>
        <td class="text-right">{{ $fmt($fila['fason']) }}</td>
        <td>{{ $fila['estado'] }}</td>
        <td>{{ $fila['motivo'] }}</td>
        <td class="text-right">{{ $fmt($fila['subtotal']) }}</td>
        <td class="text-right">{{ $fmt($fila['descuento']) }}</td>
        <td class="text-right">{{ $fmt($fila['total']) }}</td>
        <td class="text-right">{{ $fmt($fila['importe_pendiente']) }}</td>
    </tr>
@empty
    <tr>
        <td colspan="24" class="text-center text-muted">No hay movimientos para estos criterios.</td>
    </tr>
@endforelse
