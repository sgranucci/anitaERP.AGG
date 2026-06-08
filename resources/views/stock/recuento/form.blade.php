{{-- Compatibilidad: incluye cabecera + ítems (usar solapas desde crear/editar) --}}
@include('stock.recuento.partials.form_cabecera')
@include('stock.recuento.partials.form_items')

<input type="hidden" id="recuento-saldo-articulo-url" value="{{ route('recuento_saldo_articulo') }}">
<input type="hidden" id="recuento-aleatorio-url" value="{{ route('recuento_aleatorio') }}">
<input type="hidden" id="recuento-csrf" value="{{ csrf_token() }}">

@include('includes.stock.modalconsultaarticulo')
