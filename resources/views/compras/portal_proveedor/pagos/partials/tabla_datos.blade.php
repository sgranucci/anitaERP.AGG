@php
    $puedeVerDetalle = $puedeVerDetalle ?? true;
    $proveedorIdTabla = $proveedorId ?? null;
@endphp
<table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Orden de pago</th>
            <th>Empresa</th>
            <th class="text-right">Monto</th>
            <th class="text-right">Retenciones</th>
            <th class="text-right">Neto</th>
            <th>Estado</th>
            <th class="text-center">Cert.</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pagos as $pago)
            @php
                $ret = (float) ($pago->total_retenciones ?? $pago->pagoproveedor_retenciones->sum('importe'));
                $neto = (float) $pago->monto - $ret;
                $badgeClass = match ($pago->estado) {
                    'CONFIRMADA' => 'portal-estado-confirmada',
                    'REVERTIDA' => 'portal-estado-revertida',
                    'BAJA' => 'portal-estado-baja',
                    default => 'badge-secondary',
                };
            @endphp
            <tr>
                <td>{{ optional($pago->fecha)->format('d/m/Y') }}</td>
                <td>
                    @if ($puedeVerDetalle && $proveedorIdTabla)
                        <a class="text-primary"
                           href="{{ route('portal_proveedores_pago', ['id' => $pago->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           target="_blank" rel="noopener">
                            {{ $pago->etiquetaComprobante() }}
                        </a>
                    @else
                        {{ $pago->etiquetaComprobante() }}
                    @endif
                </td>
                <td>{{ $pago->empresas->nombre ?? '' }}</td>
                <td class="text-right">
                    {{ number_format((float) $pago->monto, 2, ',', '.') }}
                    {{ $pago->monedas->abreviatura ?? '' }}
                </td>
                <td class="text-right">{{ number_format($ret, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($neto, 2, ',', '.') }}</td>
                <td>
                    <span class="badge {{ $badgeClass }}">{{ $pago->estado }}</span>
                </td>
                <td class="text-center">{{ (int) ($pago->pagoproveedor_retenciones_count ?? $pago->pagoproveedor_retenciones->count()) }}</td>
                <td class="text-nowrap">
                    @if ($puedeVerDetalle && $proveedorIdTabla)
                        <a href="{{ route('portal_proveedores_pago', ['id' => $pago->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           class="btn-accion-tabla tooltipsC" title="Consultar pago"
                           target="_blank" rel="noopener">
                            <i class="fa fa-search text-primary"></i>
                        </a>
                        <a href="{{ route('portal_proveedores_pago_pdf', ['id' => $pago->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           class="btn-accion-tabla tooltipsC" title="Descargar PDF de la OP"
                           target="_blank" rel="noopener">
                            <i class="fa fa-file-pdf-o text-danger"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">
                    <div class="portal-empty">
                        <i class="fa fa-money"></i>
                        Todavía no hay pagos registrados para este proveedor.
                        <br>
                        <small>Cuando se confirmen órdenes de pago en el ERP, aparecerán aquí con sus certificados de retención.</small>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
