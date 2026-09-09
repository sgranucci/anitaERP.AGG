@extends("theme.$theme.layout")
@section('titulo')
Nuevo pedido Interforming
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
@include('includes.tabs-activas-estilos')
@php
    $volverListadoUrl = route('pedido', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo pedido (Interforming)</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="tabs-activas">
                    <ul class="nav nav-tabs" id="tabs-pedido-interforming" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-pedido-if-datos-link" data-toggle="tab"
                               href="#tab-pedido-if-datos" role="tab" aria-controls="tab-pedido-if-datos" aria-selected="true">
                                <i class="fa fa-info-circle"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-pedido-if-arbol-link" data-toggle="tab"
                               href="#tab-pedido-if-arbol" role="tab" aria-controls="tab-pedido-if-arbol" aria-selected="false">
                                <i class="fa fa-sitemap"></i> Árbol aprobación
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tab-pedido-if-datos" role="tabpanel"
                         aria-labelledby="tab-pedido-if-datos-link">
                        <form action="{{ route('guardar_pedido') }}" method="POST" id="form-pedido-interforming"
                              autocomplete="off" data-mensaje-grabacion="Grabando pedido…">
                            @csrf
                            @include('ventas.pedido.interforming.form')
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-pedido-if-arbol" role="tabpanel"
                         aria-labelledby="tab-pedido-if-arbol-link">
                        @include('ventas.pedido.interforming.partials.solapa_arbol', ['pedido' => $pedido])
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" form="form-pedido-interforming"
                        onclick="return window.pedidoInterformingValidarSubmit();">
                    <i class="fa fa-save"></i> Guardar
                </button>
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
