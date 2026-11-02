@php
    $paraPantalla = $paraPantalla ?? false;
    $filas = $filas ?? [];
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:8%">Severidad</th>
                <th style="width:22%">Tipo</th>
                <th style="width:24%">Referencia</th>
                <th>Detalle</th>
                <th class="text-right" style="width:10%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                @php
                    $fila = is_array($fila) ? $fila : (array) $fila;
                    $sev = (string) ($fila['severidad'] ?? '');
                @endphp
                <tr>
                    <td>
                        @if ($sev === 'error')
                            <span class="badge badge-danger">Error</span>
                        @else
                            <span class="badge badge-warning">Aviso</span>
                        @endif
                    </td>
                    <td>{{ $fila['tipo_label'] ?? $fila['tipo'] ?? '' }}</td>
                    <td>
                        @if ($paraPantalla && ! empty($fila['asiento_id']) && can('editar-asiento', false))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_asiento', ['id' => (int) $fila['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                {{ $fila['referencia'] ?? '' }}
                            </a>
                        @elseif ($paraPantalla && ! empty($fila['venta_id']) && can('editar-factura', false))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_factura', ['id' => (int) $fila['venta_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                {{ $fila['referencia'] ?? '' }}
                            </a>
                        @elseif ($paraPantalla && ! empty($fila['sucursal']) && can('editar-cuentas-automaticas-contables', false))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('cuentas_automaticas_contables', ['empresa_id' => 1, 'pv' => (int) $fila['sucursal'], 'solo_transferencia_pv' => 1]) }}">
                                {{ $fila['referencia'] ?? '' }}
                            </a>
                        @else
                            {{ $fila['referencia'] ?? '' }}
                        @endif
                    </td>
                    <td class="small">{{ $fila['detalle'] ?? '' }}</td>
                    <td class="text-right">
                        @if ($fila['importe'] !== null)
                            {{ number_format((float) $fila['importe'], 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">Sin incongruencias.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
