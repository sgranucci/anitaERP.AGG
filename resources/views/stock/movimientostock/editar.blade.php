@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Stock
@endsection

@section("scripts")
<script>
    window.movimientoStockModoFerli = @json($movimientoStockModoFerli ?? false);
    window.movimientoStockPreviewConversionFormulaUrl = @json(route('preview_conversion_formula_movimientostock'));
    window.movimientoStockSaldoOrigenUrl = @json(route('movimientostock_saldo_articulo'));
    window.movimientoStockPrecioLineaUrl = @json(route('movimientostock_precio_linea'));
</script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/movimientostock/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-items.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-asiento.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-tipo-transaccion.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-transferencia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/atajos-consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-formula-conversion.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-saldo-origen.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/proceso-overlay.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-grabando.js') }}" type="text/javascript"></script>
@if(\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-kardex-linea.js') }}" type="text/javascript"></script>
@endif

<script>
	function sub()
	{
        $('#formgeneral').submit();
    }
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Movimiento de Stock</h3>
				&nbsp;- ID: {{ $movimientostock->id }} - Movimiento: {{$movimientostock->codigo}}
                <div class="card-tools">
                    @include('stock.movimientostock.partials.boton_imprimir_com_pdf', [
                        'movimientoStockId' => $movimientostock->id,
                    ])
                    <a href="{{route('movimientostock')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_movimientostock', ['id' => $movimientostock->id])}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                data-preview-url="{{ route('preview_asiento_movimientostock', ['id' => $movimientostock->id]) }}"
                data-tiene-asiento-grabado="{{ (int) ($movimientostock->asiento_id ?? 0) > 0 ? '1' : '0' }}">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$movimientostock->codigo}}" >
        			<input type="hidden" id="movimientostockid" name="movimientostockid" value="{{$movimientostock->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('stock.movimientostock.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                            @include('stock.movimientostock.partials.boton_imprimir_com_pdf', [
                                'movimientoStockId' => $movimientostock->id,
                            ])
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso-overlay-pedido')
@endsection
