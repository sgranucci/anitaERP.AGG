@php
    $fmtImp = static function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
@endphp
<thead>
    <tr class="columnas" style="background:#85C1E9;color:#17202A;">
        <th>SKU</th>
        <th>Descripción</th>
        <th>Marca</th>
        <th>Canal</th>
        <th>Estado</th>
        <th class="text-right">Precio fábrica</th>
        <th class="text-right">Costo local</th>
        <th>Aviso</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr style="{{ ! empty($fila['sin_precio']) ? 'background:#FDEBD0;' : '' }}">
            <td>
                @if (! empty($puede_ver_articulo) && (int) ($fila['articulo_id'] ?? 0) > 0)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['sku'] ?? '' }}
                    </a>
                @else
                    {{ $fila['sku'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['marca'] ?? '' }}</td>
            <td>{{ $fila['canal'] ?? '' }}</td>
            <td>{{ $fila['estado'] ?? '' }}</td>
            <td class="text-right">{{ $fmtImp($fila['precio_fabrica'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['costo'] ?? 0) }}</td>
            <td>
                @if (! empty($fila['sin_precio']))
                    Precio en 0
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="8" class="text-center text-muted">No hay artículos para los filtros aplicados.</td>
        </tr>
    @endforelse
</tbody>
