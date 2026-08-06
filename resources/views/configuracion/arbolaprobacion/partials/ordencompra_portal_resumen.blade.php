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
        <div class="px-3 pb-3">
            @if($mov && !empty($mov->hashvisualizar))
            <a href="{{ route('visualizar_ordencompra', ['id' => $oc->id, 'hash' => $mov->hashvisualizar]) }}" class="btn btn-outline-secondary btn-sm btn-block mb-2" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-eye"></i> Ver orden de compra (solo lectura)
            </a>
            @endif
        </div>
    </div>
</div>
