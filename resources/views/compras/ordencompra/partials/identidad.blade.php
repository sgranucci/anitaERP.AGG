@php
    $tot = $oc_totales_resumen ?? [];
    $numero = (isset($data) && $data)
        ? ($data->numeroordencompra ?? '—')
        : ($proximoNumeroordencompra ?? 'Nueva');
    $estado = (isset($data) && $data)
        ? ($data->estadoordencompra ?? '—')
        : \App\Support\Compras\OrdencompraEstados::PENDIENTE;
    $proveedor = (isset($data) && $data)
        ? (optional($data->proveedores)->nombre ?? 'Sin proveedor')
        : 'Sin proveedor';
    $sector = (isset($data) && $data)
        ? (optional($data->sector_legajocompras)->nombre ?? '—')
        : '—';
    $fecha = (isset($data) && $data && $data->fecha)
        ? date('d/m/Y', strtotime($data->fecha))
        : date('d/m/Y');
    $moneda = trim((string) ($tot['moneda_abrev'] ?? ''));
    $total = (float) ($tot['total'] ?? 0);
@endphp
<div class="oc-identidad">
    <div>
        <div class="oc-id-kicker">Orden de compra</div>
        <div class="oc-id-valor">N.º {{ $numero }}</div>
        <div class="oc-id-sub">{{ $fecha }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Estado</div>
        <div class="oc-id-valor">
            @include('compras.ordencompra.partials.estado_badge', ['estado' => $estado])
        </div>
        <div class="oc-id-sub">{{ $sector }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Proveedor</div>
        <div class="oc-id-valor">{{ $proveedor }}</div>
        <div class="oc-id-sub">{{ isset($data) && $data ? (optional($data->empresas)->nombre ?? '') : '' }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Neto</div>
        <div class="oc-id-valor">{{ $moneda }} {{ number_format((float) ($tot['neto_sin_iva'] ?? 0), 2, ',', '.') }}</div>
        <div class="oc-id-sub">IVA {{ number_format((float) ($tot['iva_total'] ?? 0), 2, ',', '.') }}</div>
    </div>
    <div>
        <div class="oc-id-kicker">Total</div>
        <div class="oc-id-valor oc-id-total">{{ $moneda }} {{ number_format($total, 2, ',', '.') }}</div>
        @if (!empty($wizardRequisicionId))
            <div class="oc-id-sub">Desde requisición #{{ (int) $wizardRequisicionId }}</div>
        @endif
    </div>
</div>
