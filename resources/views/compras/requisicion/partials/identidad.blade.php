@php
    $numero = (isset($data) && $data)
        ? ($data->numerorequisicion ?? '—')
        : 'Nueva';
    $estado = (isset($data) && $data)
        ? ($data->estado ?? '—')
        : (!empty($modo_provisorio) ? ($estado_provisorio ?? 'PROVISORIO') : 'PENDIENTE');
    $proveedor = (isset($data) && $data)
        ? (optional($data->proveedores)->nombre ?? ($data->nombreproveedor ?? 'Sin proveedor'))
        : 'Sin proveedor';
    $solicitante = (isset($data) && $data)
        ? (optional($data->usuarios)->nombre ?? '')
        : (auth()->user()->nombre ?? '');
    $fecha = (isset($data) && $data && $data->fecha)
        ? date('d/m/Y', strtotime($data->fecha))
        : date('d/m/Y');
    $empresa = (isset($data) && $data)
        ? (optional($data->empresas)->nombre ?? '')
        : '';
    $cc = (isset($data) && $data)
        ? trim((string) (optional($data->centrocostos)->codigo ?? '').' '.(optional($data->centrocostos)->nombre ?? ''))
        : '';
    $moneda = trim((string) ((isset($data) && $data) ? ($data->monedacabecera_abreviatura ?? '') : ''));
    $total = (float) ((isset($data) && $data) ? ($data->monto ?? 0) : 0);
@endphp
<div class="oc-identidad">
    <div>
        <div class="oc-id-kicker">Requisición</div>
        <div class="oc-id-valor">N.º {{ $numero }}</div>
        <div class="oc-id-sub">{{ $fecha }}@if (isset($data) && $data) · ID {{ $data->id }}@endif</div>
    </div>
    <div>
        <div class="oc-id-kicker">Estado</div>
        <div class="oc-id-valor">
            @include('compras.requisicion.partials.estado_badge', ['estado' => $estado])
        </div>
        <div class="oc-id-sub">{{ $cc !== '' ? $cc : '—' }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Solicitante</div>
        <div class="oc-id-valor">{{ $solicitante !== '' ? $solicitante : '—' }}</div>
        <div class="oc-id-sub">{{ $empresa }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Proveedor</div>
        <div class="oc-id-valor">{{ $proveedor }}</div>
        <div class="oc-id-sub">{{ !empty($modo_provisorio) ? 'Alta en modo provisorio' : '' }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Total</div>
        <div class="oc-id-valor oc-id-total">{{ $moneda }} {{ number_format($total, 2, ',', '.') }}</div>
        @if (!empty($tiene_ordencompra_asociada))
            <div class="oc-id-sub">Con orden de compra vinculada</div>
        @endif
    </div>
</div>
