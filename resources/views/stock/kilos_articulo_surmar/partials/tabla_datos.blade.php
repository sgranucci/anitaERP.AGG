@php
    $modo = $modo ?? ($filtros['tipo'] ?? 'todos');
    $presentacion = $presentacion ?? 'pantalla';
    $puedeVer = ! empty($puede_ver_articulo) && $presentacion === 'pantalla';
    $fmt = static function ($valor) use ($presentacion) {
        $numero = (float) $valor;
        if ($presentacion === 'excel') {
            return number_format($numero, 2, '.', '');
        }

        return number_format($numero, 2, ',', '.');
    };
    $columnas = match ($modo) {
        'rem', 'ap' => [
            ['key' => 'entrada', 'label' => 'Entrada'],
            ['key' => 'salida', 'label' => 'Salida'],
            ['key' => $modo === 'rem' ? 'kilos_rem' : 'kilos_ap', 'label' => 'Kilos'],
        ],
        'com' => [
            ['key' => 'piezas_com', 'label' => 'Cantidad'],
            ['key' => 'kilos_com', 'label' => 'Kilos'],
            ['key' => 'rechazo', 'label' => 'Rechazo'],
        ],
        default => [
            ['key' => 'kilos_rem', 'label' => 'Kilos remito'],
            ['key' => 'kilos_ap', 'label' => 'Kilos alta de producción'],
            ['key' => 'piezas_com', 'label' => 'Piezas recepción'],
            ['key' => 'kilos_com', 'label' => 'Kilos recepción'],
        ],
    };
    $filasTabla = $filas instanceof \Illuminate\Pagination\LengthAwarePaginator ? $filas->items() : ($filas ?? []);
@endphp
@if (($parte ?? 'completa') !== 'cuerpo')
    @if (($parte ?? 'completa') === 'completa')
        <thead>
    @endif
    <tr class="columnas" style="background:#85C1E9;color:#17202A;">
        <th>Código</th>
        <th>Descripción</th>
        @foreach ($columnas as $columna)
            <th class="text-right">{{ $columna['label'] }}</th>
        @endforeach
    </tr>
    @if (($parte ?? 'completa') === 'completa')
        </thead>
    @endif
@endif
@if (($parte ?? 'completa') !== 'columnas')
<tbody>
    @forelse ($filasTabla as $fila)
        <tr>
            <td>
                @if ($puedeVer)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['sku'] }}
                    </a>
                @else
                    {{ $fila['sku'] }}
                @endif
            </td>
            <td>{{ $fila['descripcion'] }}</td>
            @foreach ($columnas as $columna)
                <td class="text-right">{{ $fmt($fila[$columna['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ 2 + count($columnas) }}">No hay movimientos en el período.</td>
        </tr>
    @endforelse
    @if (! empty($totales) && count($filasTabla) > 0)
        <tr class="font-weight-bold" style="background:#D6EAF8;">
            <td colspan="2">Total</td>
            @foreach ($columnas as $columna)
                <td class="text-right">{{ $fmt($totales[$columna['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endif
</tbody>
@endif
