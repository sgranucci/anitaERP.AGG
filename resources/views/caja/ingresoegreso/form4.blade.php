{{-- Solapa retenciones IE. En una OPA de solicitud anticipada se muestran las precalculadas. --}}
@php
    $retencionesIe = collect();
    if (! empty($solicitudpagoOrigen) && \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::esPagoOpa($solicitudpagoOrigen)) {
        $solicitudpagoOrigen->loadMissing('retenciones');
        $retencionesIe = $solicitudpagoOrigen->retenciones ?? collect();
    }
@endphp
<div class="card card-outline card-info form4 mb-0 border-0 shadow-none" style="display: none">
    <div class="card-body">
    @if ($retencionesIe->isNotEmpty())
        <div class="alert alert-info">
            Retenciones tomadas de la solicitud de pago
            <strong>#{{ $solicitudpagoOrigen->codigo }}</strong>.
            Se practican al guardar la OPA; el importe de caja es el monto de la solicitud menos este total.
        </div>
        <table class="table table-sm table-bordered mb-2">
            <thead class="thead-light">
                <tr>
                    <th>Concepto</th>
                    <th>Régimen</th>
                    <th class="text-right">Base</th>
                    <th class="text-right">Alícuota %</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($retencionesIe as $ret)
                    <tr>
                        <td>{{ $ret->etiquetaTipo() }}</td>
                        <td>{{ $ret->codigo_regimen ?: '—' }}</td>
                        <td class="text-right">{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $ret->alicuota, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-weight-bold">
                    <td colspan="4" class="text-right">Total retenciones</td>
                    <td class="text-right">{{ number_format((float) $retencionesIe->sum('importe'), 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="alert alert-info mb-0">
            <strong>Retenciones en Ingresos/Egresos:</strong>
            para pagos a proveedores con retenciones (Ganancias/IVA/SUSS/IIBB) use
            <a href="{{ route('pagoproveedor') }}" class="alert-link" target="_blank" rel="noopener">Órdenes de pago a proveedores</a>
            o el flujo de Propuesta de pagos.
            @if (! empty($solicitudpagoOrigen) && \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::esPagoOpa($solicitudpagoOrigen))
                En una solicitud anticipada las retenciones se calculan en la solicitud, si se activa esa opción.
            @else
                Esta solapa queda como referencia; no carga retenciones en el movimiento de caja.
            @endif
        </div>
    @endif
    </div>
</div>
