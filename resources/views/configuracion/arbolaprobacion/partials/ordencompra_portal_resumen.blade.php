@php
    $oc = $ordencompra;
    $mov = $movimiento ?? null;
@endphp
<div class="card portal-card mb-3">
    <div class="card-header bg-danger">
        <h1 class="card-title mb-0 h5 text-white">Orden de compra {{ $oc->numeroordencompra ?? '—' }}</h1>
        <small class="text-white-50 d-block pl-3 mt-1">Nivel de aprobación: {{ $mov->nivel ?? '—' }}</small>
    </div>
    <div class="card-body p-0">
        <dl class="row kv mb-0 px-3 py-3">
            <dt class="col-sm-4">Empresa</dt>
            <dd class="col-sm-8">{{ $oc->empresas->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Solicitante</dt>
            <dd class="col-sm-8">{{ optional($oc->usuarios)->nombre ?? '—' }}</dd>
            <dt class="col-sm-4">Centro de costo</dt>
            <dd class="col-sm-8">{{ trim(($oc->centrocostos->codigo ?? '').' '.($oc->centrocostos->nombre ?? '')) ?: '—' }}</dd>
            <dt class="col-sm-4">Fecha / entrega</dt>
            <dd class="col-sm-8">
                {{ $oc->fecha ? date('d/m/Y', strtotime($oc->fecha)) : '—' }}
                /
                {{ $oc->fechaentrega ? date('d/m/Y', strtotime($oc->fechaentrega)) : '—' }}
            </dd>
            <dt class="col-sm-4">Estado actual</dt>
            <dd class="col-sm-8">{{ $oc->estadoordencompra ?? '—' }}</dd>
            <dt class="col-sm-4">Monto ítems</dt>
            <dd class="col-sm-8">{{ $moneda_abrev_items ?? '—' }} {{ number_format((float) ($monto_items ?? 0), 2, ',', '.') }}</dd>
            <dt class="col-sm-4">Proveedor</dt>
            <dd class="col-sm-8">@if($oc->proveedores){{ ($oc->proveedores->codigo ?? '').' — '.($oc->proveedores->nombre ?? '') }}@else—@endif</dd>
            @if(($modoPortal ?? '') !== 'rechazo')
            <dt class="col-sm-4">Tras aprobar este paso</dt>
            <dd class="col-sm-8">@if(!empty($estado_tras_aprobar))<strong>{{ $estado_tras_aprobar }}</strong>@else<span class="text-muted">Sin cambio de estado definido en el árbol (solo avanza el flujo).</span>@endif</dd>
            @endif
            <dt class="col-sm-4">Comentario</dt>
            <dd class="col-sm-8">{{ $oc->comentario !== null && $oc->comentario !== '' ? $oc->comentario : '—' }}</dd>
            @php
                $comentarioEnvioArbolOc = trim((string) (($mov->observacion ?? '') ?: ''));
            @endphp
            @if ($comentarioEnvioArbolOc !== '')
            <dt class="col-sm-4">Comentario al enviar</dt>
            <dd class="col-sm-8 text-break">{{ $comentarioEnvioArbolOc }}</dd>
            @endif
            <dt class="col-sm-4">Detalle</dt>
            <dd class="col-sm-8 text-break">{{ $oc->detalle !== null && $oc->detalle !== '' ? $oc->detalle : '—' }}</dd>
        </dl>
        @php
            $paquete = $paquete_legajo ?? ['factura' => null, 'recepciones' => [], 'url_pdf_oc' => null];
            $facturaLegajo = $paquete['factura'] ?? null;
            $recepcionesLegajo = $paquete['recepciones'] ?? [];
            $urlPdfOc = $paquete['url_pdf_oc'] ?? null;
        @endphp
        <div class="px-3 pb-3">
            <h3 class="h6 text-muted mb-2">Factura del legajo</h3>
            @if ($facturaLegajo)
                <dl class="row kv mb-2">
                    <dt class="col-sm-4">Número</dt>
                    <dd class="col-sm-8">{{ $facturaLegajo['numero'] }}</dd>
                    <dt class="col-sm-4">Fecha</dt>
                    <dd class="col-sm-8">{{ $facturaLegajo['fecha'] ?? '—' }}</dd>
                    @if ($facturaLegajo['total'] !== null)
                    <dt class="col-sm-4">Importe</dt>
                    <dd class="col-sm-8">{{ number_format((float) $facturaLegajo['total'], 2, ',', '.') }}</dd>
                    @endif
                </dl>
                @if (!empty($facturaLegajo['url_pdf']))
                    <a href="{{ $facturaLegajo['url_pdf'] }}" class="btn btn-outline-primary btn-sm btn-block mb-3" target="_blank" rel="noopener noreferrer">
                        <i class="fa fa-file-pdf-o"></i> Ver PDF de la factura
                    </a>
                @endif
            @else
                <p class="text-muted small mb-3">No hay factura PDF asociada al legajo.</p>
            @endif

            <h3 class="h6 text-muted mb-2">Recepción (COM)</h3>
            @if (count($recepcionesLegajo) > 0)
                <ul class="list-unstyled mb-3">
                    @foreach ($recepcionesLegajo as $com)
                        <li class="mb-2">
                            <strong>{{ $com['numero'] }}</strong>
                            — {{ $com['fecha'] ?? '—' }}
                            <span class="text-muted">({{ $com['estado'] }})</span>
                            @if (!empty($com['diferencias']))
                                <br><span class="text-danger">Diferencias: {{ implode(', ', $com['diferencias']) }}</span>
                            @endif
                            @if (!empty($com['resumen_diferencias']))
                                <br><span class="small">{{ $com['resumen_diferencias'] }}</span>
                            @endif
                            @if (!empty($com['url_pdf']))
                                <a href="{{ $com['url_pdf'] }}" class="btn btn-outline-primary btn-sm btn-block mt-2" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-file-pdf-o"></i> Ver PDF {{ $com['numero'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted small mb-3">No hay recepción COM confirmada en este legajo.</p>
            @endif

            @if (!empty($urlPdfOc))
            <a href="{{ $urlPdfOc }}" class="btn btn-outline-primary btn-sm btn-block mb-2" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-file-pdf-o"></i> Ver PDF de la orden de compra
            </a>
            @endif
            @if($mov && !empty($mov->hashvisualizar))
            <a href="{{ route('visualizar_ordencompra', ['id' => $oc->id, 'hash' => $mov->hashvisualizar]) }}?form=1" class="btn btn-outline-secondary btn-sm btn-block mb-2" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-eye"></i> Ver formulario OC
            </a>
            @endif
        </div>
    </div>
</div>
