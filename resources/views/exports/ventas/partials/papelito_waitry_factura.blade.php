@php $codigoPapelitoWaitry = \App\Support\Ventas\GastronomiaVentaDisplaySupport::waitryDisplayId($venta); @endphp
@if ($codigoPapelitoWaitry !== null)
<strong style="font-size: 18px; letter-spacing: 0.5px;">Papelito monitor: {{ $codigoPapelitoWaitry }}</strong><br>
@endif
