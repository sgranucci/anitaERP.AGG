@php
    $filasLista = $filas ?? [];
    if ($filasLista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasLista = $filasLista->items();
    }
    $enPantalla = ! empty($en_pantalla);
@endphp
@if (empty($solo_thead_tbody))
<table class="table table-bordered table-sm mb-0">
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Ticket</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Proveedor / Visitante</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>OC</th>
            <th>Requisición</th>
            <th>COM</th>
            <th>Factura</th>
            <th>Orden de pago</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasLista as $fila)
            <tr>
                <td>
                    @if ($enPantalla && ($puede_ver_ticket ?? false) && ! empty($fila['ticket_id']))
                        <a href="{{ route('editar_ingreso_proveedor', ['id' => $fila['ticket_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">{{ $fila['ticket_id'] }}</a>
                    @else
                        {{ $fila['ticket_id'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>
                    @if (! empty($fila['proveedor_codigo']))
                        {{ $fila['proveedor_codigo'] }} —
                    @endif
                    {{ $fila['proveedor_nombre'] ?? '' }}
                </td>
                <td>{{ $fila['motivo'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>
                    @if ($enPantalla && ($puede_ver_oc ?? false) && ! empty($fila['oc_url']))
                        <a href="{{ $fila['oc_url'] }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['oc_etiqueta'] ?: ($fila['oc_numero'] ?? '') }}</a>
                    @else
                        {{ $fila['oc_etiqueta'] ?: ($fila['oc_numero'] ?? '') }}
                    @endif
                </td>
                <td>
                    @if ($enPantalla && ($puede_ver_requisicion ?? false) && ! empty($fila['requisicion_url']))
                        <a href="{{ $fila['requisicion_url'] }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['requisicion'] ?? '' }}</a>
                    @else
                        {{ $fila['requisicion'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($enPantalla && ($puede_ver_com ?? false) && ! empty($fila['coms_detalle']))
                        @foreach ($fila['coms_detalle'] as $i => $com)
                            @if ($i > 0), @endif
                            @if (! empty($com['url_ver']))
                                <a href="{{ $com['url_ver'] }}" class="text-primary" target="_blank" rel="noopener">{{ $com['etiqueta'] ?? '' }}</a>
                            @else
                                {{ $com['etiqueta'] ?? '' }}
                            @endif
                        @endforeach
                    @else
                        {{ $fila['coms'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($enPantalla && ($puede_ver_factura ?? false) && ! empty($fila['facturas_detalle']))
                        @foreach ($fila['facturas_detalle'] as $i => $fac)
                            @if ($i > 0), @endif
                            @if (! empty($fac['url_ver']))
                                <a href="{{ $fac['url_ver'] }}" class="text-primary" target="_blank" rel="noopener">{{ $fac['etiqueta'] ?? '' }}</a>
                            @else
                                {{ $fac['etiqueta'] ?? '' }}
                            @endif
                        @endforeach
                    @else
                        {{ $fila['facturas'] ?? '' }}
                    @endif
                </td>
                <td>
                    @if ($enPantalla && ($puede_ver_op ?? false) && ! empty($fila['ops_detalle']))
                        @foreach ($fila['ops_detalle'] as $i => $op)
                            @if ($i > 0), @endif
                            @if (! empty($op['url_ver']))
                                <a href="{{ $op['url_ver'] }}" class="text-primary" target="_blank" rel="noopener">{{ $op['etiqueta'] ?? '' }}</a>
                            @else
                                {{ $op['etiqueta'] ?? '' }}
                            @endif
                        @endforeach
                    @else
                        {{ $fila['ordenes_pago'] ?? '' }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="12" class="text-center text-muted">Sin visitas para los filtros</td></tr>
        @endforelse
    </tbody>
@if (empty($solo_thead_tbody))
</table>
@endif
