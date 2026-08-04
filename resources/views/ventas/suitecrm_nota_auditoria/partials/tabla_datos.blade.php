@php
    $modo = $modo ?? 'pantalla';
    $esExcel = $modo === 'excel';
    $esPantalla = $modo === 'pantalla';
    $colspan = $esPantalla ? 7 : 6;
@endphp
<thead>
    <tr style="background-color: #85C1E9; color: #17202A;">
        @if ($esPantalla)
            <th>Fecha</th>
        @endif
        <th>Vendedor</th>
        <th>Empresa</th>
        <th>Tipo</th>
        <th>Cód.</th>
        <th>Asunto</th>
        <th>Nota</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            @if ($esPantalla)
                <td>{{ $fila['fecha_display'] ?? '' }}</td>
            @endif
            <td>{{ $fila['vendedor'] ?? '' }}</td>
            <td>{{ $fila['relacionado'] ?? '' }}</td>
            <td>{{ $fila['tipo'] ?? '' }}</td>
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
            <td colspan="{{ $colspan }}" class="text-center text-muted">Sin notas para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
