@php
    $filas = $filas ?? [];
    $modoExport = ! empty($modoExport);
@endphp
<table class="table table-sm table-bordered table-striped {{ $modoExport ? 'data' : '' }}" @if (! $modoExport) id="tabla-paginada" @endif>
    <thead @if (! $modoExport) style="background:#85C1E9;color:#17202A;" @endif>
        @if ($modoExport)
            {{-- thead PDF se completa en listado --}}
        @endif
        <tr @if ($modoExport) class="columnas" @endif>
            <th>Fecha</th>
            <th>Sala</th>
            <th>Rubro</th>
            <th>Cuenta</th>
            <th class="text-right">Flash</th>
            <th class="text-right">Ventas</th>
            <th class="text-right">Contab. ERP</th>
            <th class="text-right">Contab. Anita</th>
            <th class="text-right">Dif. Ventas</th>
            <th class="text-right">Dif. ERP</th>
            <th class="text-right">Dif. Anita</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $estado = (string) ($fila['estado'] ?? '');
                $claseEstado = match ($estado) {
                    'OK' => 'text-success font-weight-bold',
                    'DIF' => 'text-danger font-weight-bold',
                    'PEND' => 'text-warning font-weight-bold',
                    'SIN_FLASH' => 'text-info font-weight-bold',
                    'SIN_CFG' => 'text-secondary font-weight-bold',
                    default => '',
                };
            @endphp
            <tr>
                <td>{{ $fila['fecha_fmt'] ?? $fila['fecha'] }}</td>
                <td>{{ $fila['sala_codigo'] }}</td>
                <td>{{ $fila['rubro_label'] ?? $fila['rubro'] }}</td>
                <td>{{ $fila['cuenta_codigo'] ?: '—' }}</td>
                <td class="text-right">{{ number_format((float) $fila['flash'], 2, ',', '.') }}</td>
                <td class="text-right">
                    @if (! $modoExport && ! empty($fila['venta_id']))
                        <a class="text-primary"
                            href="{{ route('editar_factura', ['id' => $fila['venta_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            target="_blank" rel="noopener">
                            {{ number_format((float) $fila['ventas'], 2, ',', '.') }}
                        </a>
                    @else
                        {{ number_format((float) $fila['ventas'], 2, ',', '.') }}
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $fila['erp'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $fila['anita'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $fila['dif_ventas'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $fila['dif_erp'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $fila['dif_anita'], 2, ',', '.') }}</td>
                <td class="{{ $claseEstado }}">{{ $estado }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-center text-muted">Sin filas para el rango consultado.</td>
            </tr>
        @endforelse
    </tbody>
</table>
