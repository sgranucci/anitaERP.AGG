@extends("theme.$theme.layout")
@section('titulo')
Editar pedido Interforming
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vendedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/transporte/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pedido {{ $pedido->codigo }} (Interforming)</h3>
            </div>
            <form action="{{ route('actualizar_pedido', $pedido->id) }}" method="POST" id="form-pedido-interforming" autocomplete="off"
                  @if (!($puedeActualizarPedido ?? true) || ($soloConsulta ?? false)) class="pe-none" @endif>
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.pedido.interforming.form')
                </div>
            </form>
            <div class="card-footer">
                @if (($puedeActualizarPedido ?? false) && !($soloConsulta ?? false))
                    <button type="submit" class="btn btn-primary" form="form-pedido-interforming" onclick="return window.pedidoInterformingValidarSubmit();">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                @endif
                @if (!($ocultarVolver ?? false))
                    <a href="{{ route('pedido') }}" class="btn btn-secondary">Volver</a>
                @else
                    <button type="button" class="btn btn-secondary" onclick="window.close();">Cerrar solapa</button>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultavendedor')
@include('includes.ventas.modalconsultatransporte')
@include('includes.ventas.modalseleccionclienteentrega')
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@endsection
