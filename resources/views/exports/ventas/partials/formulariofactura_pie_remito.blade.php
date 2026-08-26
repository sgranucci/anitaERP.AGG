<div class="factura-pie-bloque">
    @php
        $caiRemito = $caiRemito ?? \App\Support\Ventas\CaiRemitoVigenteSupport::paraVenta($venta);
        $fechaVtoCai = $caiRemito?->fecha_vencimiento
            ? \Illuminate\Support\Carbon::parse($caiRemito->fecha_vencimiento)->format('d/m/Y')
            : '';
    @endphp
    <p class="factura-valor-asegurado">
        <strong>Valor asegurado: {{ number_format($valorAsegurado ?? 0, 2) }}</strong>
    </p>
    @if ($caiRemito && $caiRemito->numero_cai)
        <p class="factura-pie-cai">
            CAI: {{ $caiRemito->numero_cai }}<br>
            Fecha Vencimiento CAI: {{ $fechaVtoCai }}
        </p>
    @endif
</div>
