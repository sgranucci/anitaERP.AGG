<div class="factura-pie-bloque">
    @php
        $caiRemito = $caiRemito ?? \App\Support\Ventas\CaiRemitoVigenteSupport::paraVenta($venta);
    @endphp
    @if ($caiRemito && $caiRemito->numero_cai)
        <p class="factura-pie-cai">
            CAI: {{ $caiRemito->numero_cai }}<br>
            Fecha Vencimiento CAI: {{ optional($caiRemito->fecha_vencimiento)->format('d/m/Y') }}
        </p>
    @endif
</div>
