@php
    $puedeVerDetalle = $puedeVerDetalle ?? true;
    $proveedorIdTabla = $proveedorId ?? null;
@endphp
<table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha pago</th>
            <th>Orden de pago</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Certificado</th>
            <th class="text-right">Base</th>
            <th class="text-right">Alícuota</th>
            <th class="text-right">Importe</th>
            <th>Provincia / régimen</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($retenciones as $ret)
            @php $pago = $ret->pagoproveedores; @endphp
            <tr>
                <td>{{ optional(optional($pago)->fecha)->format('d/m/Y') }}</td>
                <td>
                    @if ($puedeVerDetalle && $proveedorIdTabla && $pago)
                        <a class="text-primary"
                           href="{{ route('portal_proveedores_pago', ['id' => $pago->id, 'proveedor_id' => $proveedorIdTabla]) }}"
                           target="_blank" rel="noopener">
                            {{ $pago->etiquetaComprobante() }}
                        </a>
                    @else
                        {{ $pago ? $pago->etiquetaComprobante() : '—' }}
                    @endif
                </td>
                <td>{{ optional(optional($pago)->empresas)->nombre }}</td>
                <td>{{ $ret->etiquetaTipo() }}</td>
                <td>{{ $ret->nro_certificado ?: '—' }}</td>
                <td class="text-right">{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $ret->alicuota, 4, ',', '.') }}%</td>
                <td class="text-right">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                <td>
                    {{ optional($ret->provincias)->nombre }}
                    @if ($ret->codigo_regimen)
                        {{ optional($ret->provincias)->nombre ? '·' : '' }} {{ $ret->codigo_regimen }}
                    @endif
                </td>
                <td class="text-nowrap">
                    @if ($puedeVerDetalle && $proveedorIdTabla && $pago)
                        <a href="{{ route('portal_proveedores_retencion_pdf', [
                                'id' => $pago->id,
                                'retencionId' => $ret->id,
                                'proveedor_id' => $proveedorIdTabla,
                            ]) }}"
                           class="btn-accion-tabla tooltipsC"
                           title="Descargar certificado PDF"
                           target="_blank" rel="noopener">
                            <i class="fa fa-file-pdf-o text-danger"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    <div class="portal-empty">
                        <i class="fa fa-percent"></i>
                        No hay retenciones en el período consultado.
                        <br>
                        <small>Los certificados se generan al confirmar órdenes de pago con retenciones impositivas.</small>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
