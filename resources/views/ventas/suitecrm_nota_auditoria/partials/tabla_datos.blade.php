@php
    $modo = $modo ?? 'pantalla';
    $esExcel = $modo === 'excel';
@endphp
<thead>
    <tr style="background-color: #85C1E9; color: #17202A;">
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Relacionado</th>
        <th>Empresa potencial</th>
        <th>Cód. ERP</th>
        <th>Cliente ERP</th>
        <th>Vendedor</th>
        <th>Asunto</th>
        <th>Nota</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['fecha_display'] ?? '' }}</td>
            <td>{{ $fila['tipo'] ?? '' }}</td>
            <td>{{ $fila['relacionado'] ?? '' }}</td>
            <td>{{ $fila['empresa_potencial'] ?? '' }}</td>
            <td>
                @if (($mostrarLinks ?? false) && ($puede_ver_cliente ?? false) && ! empty($fila['cliente_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                       href="{{ route('editar_cliente', ['id' => $fila['cliente_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['codigo_anita'] ?? '' }}
                    </a>
                @else
                    {{ $fila['codigo_anita'] ?? '' }}
                @endif
            </td>
            <td>
                @if (($mostrarLinks ?? false) && ($puede_ver_cliente ?? false) && ! empty($fila['cliente_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                       href="{{ route('editar_cliente', ['id' => $fila['cliente_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['cliente_anita'] ?? '' }}
                    </a>
                @else
                    {{ $fila['cliente_anita'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['vendedor'] ?? '' }}</td>
            <td>{{ $fila['asunto'] ?? '' }}</td>
            <td>
                @if ($esExcel)
                    {{ $fila['nota'] ?? '' }}
                @else
                    {!! nl2br(e($fila['nota'] ?? '')) !!}
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="9" class="text-center text-muted">Sin notas para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
