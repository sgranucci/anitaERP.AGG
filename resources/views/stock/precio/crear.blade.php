@extends("theme.$theme.layout")
@section('titulo')
    Precios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/precio/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear precio</h3>
                <div class="card-tools">
                    @if (!empty($retornoArticuloPrecios))
                        <a href="{{ $retornoArticuloPrecios['url_volver'] }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta de precios
                        </a>
                    @else
                        <a href="{{route('precio')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('guardar_precio')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @if (!empty($retornoArticuloPrecios))
                    <input type="hidden" name="retorno_articulo_id" value="{{ $retornoArticuloPrecios['articulo_id'] }}">
                    <input type="hidden" name="retorno_origen" value="{{ $retornoArticuloPrecios['origen'] }}">
                    <input type="hidden" name="retorno_fecha_referencia" value="{{ $retornoArticuloPrecios['fecha_referencia'] }}">
                    @if (!empty($retornoArticuloPrecios['listado_qs']))
                        <input type="hidden" name="retorno_listado_qs" value="{{ $retornoArticuloPrecios['listado_qs'] }}">
                    @endif
                @endif
                <div class="card-body">
                    @include('stock.precio.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultalistaprecio')
@endsection
