@php
    $puedeVerDetalle = $puedeVerDetalle ?? true;
    $proveedorIdTabla = $proveedorId ?? null;
    $fmtFecha = static function ($v) {
        if (! $v) {
            return '—';
        }
        if ($v instanceof \Carbon\CarbonInterface) {
            return $v->format('d/m/Y');
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $v;
        }
    };
@endphp
<table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Nº OC</th>
            <th>Empresa</th>
            <th>Entrega</th>
            <th>Estado</th>
            <th class="text-right">Monto OC</th>
            <th class="text-right">Facturado</th>
            <th class="text-center">Facturas</th>
            <th class="text-center">Pagos</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ordenes as $oc)
            @php
                $badgeClass = match ($oc->estadoordencompra) {
                    'PENDIENTE' => 'portal-estado-oc-pendiente',
                    'APROBADA' => 'portal-estado-oc-aprobada',
                    'CUMPLIDA' => 'portal-estado-oc-cumplida',
                    'SUSPENDIDA' => 'portal-estado-oc-suspendida',
                    'CERRADA' => 'portal-estado-oc-cerrada',
                    default => 'badge-secondary',
                };
            @endphp
            <tr>
                <td>{{ $fmtFecha($oc->fecha) }}</td>
                <td>
                    @if ($puedeVerDetalle && $proveedorIdTabla)
                        <a class="text-primary"
                           href="{{ route('portal_proveedores_orden', ['id' => $oc->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           target="_blank" rel="noopener">
                            {{ $oc->numeroordencompra }}
                        </a>
                    @else
                        {{ $oc->numeroordencompra }}
                    @endif
                </td>
                <td>{{ $oc->empresas->nombre ?? '' }}</td>
                <td>{{ $fmtFecha($oc->fechaentrega) }}</td>
                <td>
                    <span class="badge {{ $badgeClass }}">{{ $oc->estadoordencompra }}</span>
                </td>
                <td class="text-right">
                    {{ number_format((float) ($oc->monto_lineas ?? 0), 2, ',', '.') }}
                    {{ $oc->monedacabecera_abreviatura ?? '' }}
                </td>
                <td class="text-right">{{ number_format((float) ($oc->monto_facturado ?? 0), 2, ',', '.') }}</td>
                <td class="text-center">{{ (int) ($oc->facturas_count ?? 0) }}</td>
                <td class="text-center">
                    {{ (int) ($oc->pagos_count ?? 0) }}
                    @if ((int) ($oc->precargas_count ?? 0) > 0)
                        <br>
                        <small class="text-muted" title="Precargas pendientes">
                            +{{ (int) $oc->precargas_count }} prec.
                        </small>
                    @endif
                </td>
                <td class="text-nowrap">
                    @if ($puedeVerDetalle && $proveedorIdTabla)
                        <a href="{{ route('portal_proveedores_orden', ['id' => $oc->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           class="btn-accion-tabla tooltipsC" title="Seguimiento OC → facturas → pagos"
                           target="_blank" rel="noopener">
                            <i class="fa fa-search text-primary"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="portal-empty">
                        <i class="fa fa-file-text-o"></i>
                        No hay órdenes de compra en esta vista para el proveedor.
                        <br>
                        <small>Las OC activas (pendientes, aprobadas o cumplidas) aparecerán aquí con el seguimiento de facturas y pagos.</small>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
