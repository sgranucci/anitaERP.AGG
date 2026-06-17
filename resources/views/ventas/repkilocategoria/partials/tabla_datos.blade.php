@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerArticulo = $puede_ver_articulo ?? false;
    $puedeVerCategoria = $puede_ver_categoria ?? false;
    $paraPdf = $para_pdf ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
@endphp
<thead>
    <tr>
        <th>Categoría</th>
        <th>Nombre categoría</th>
        <th>Artículo</th>
        <th>Descripción</th>
        <th class="text-right">Piezas</th>
        <th class="text-right">Kilos</th>
        <th class="text-right">Cajas</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'subtotal_categoria')
            <tr class="font-weight-bold" style="background-color: #e9ecef;">
                <td>
                    @if (! $paraPdf && $puedeVerCategoria && (int) ($fila['categoria_id'] ?? 0) > 0)
                        <a href="{{ route('editar_categoria', array_merge(['id' => $fila['categoria_id']], $queryConsulta)) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['codigocategoria'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['codigocategoria'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['nombrecategoria'] ?? '' }}</td>
                <td colspan="2">Subtotal categoría</td>
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @elseif ($tipo === 'total_final')
            <tr class="font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="4">TOTAL FINAL</td>
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @else
            <tr>
                <td></td>
                <td></td>
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
                <td class="text-right">{{ $formatear($fila['total_pieza'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_kilo'] ?? 0) }}</td>
                <td class="text-right">{{ $formatear($fila['total_caja'] ?? 0) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="7" class="text-center text-muted">Sin registros</td>
        </tr>
    @endforelse
</tbody>
