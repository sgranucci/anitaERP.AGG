{{-- Encabezados de columnas de mercadería según listado_columnas_cantidad --}}
@php
    use App\Support\Ventas\VentasListadoEtiquetasSupport;
    $claseNum = $claseNum ?? 'text-right';
    $etiqCantidad = $etiqCantidad ?? VentasListadoEtiquetasSupport::etiquetaCantidad();
@endphp
@if (VentasListadoEtiquetasSupport::muestraCajaUnidad())
    <th class="{{ $claseNum }}">Cajas</th>
    <th class="{{ $claseNum }}">Unidades</th>
@endif
<th class="{{ $claseNum }}">{{ $etiqCantidad }}</th>
