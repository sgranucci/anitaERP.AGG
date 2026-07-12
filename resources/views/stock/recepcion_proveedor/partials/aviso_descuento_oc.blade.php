@php
    $descuentoOc = (float) ($descuentoOc ?? 0);
    $visible = $descuentoOc > 0.000001;
@endphp
<div id="rp-aviso-descuento-oc"
     class="alert alert-info py-2 px-3 mb-3 {{ $visible ? '' : 'd-none' }}"
     role="status">
    <i class="fa fa-info-circle mr-1" aria-hidden="true"></i>
    <span id="rp-aviso-descuento-oc-texto">
        @if($visible)
            La orden de compra tiene un descuento general del
            <strong>{{ rtrim(rtrim(number_format($descuentoOc, 2, ',', '.'), '0'), ',') }}%</strong>
            aplicado neto en los precios unitarios de esta recepci&oacute;n (coincide con factura y &uacute;ltima compra).
        @endif
    </span>
</div>
