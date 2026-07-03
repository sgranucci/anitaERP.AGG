@extends("theme.$theme.layout")
@section('titulo')
    Tipos de men&uacute; de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/editar.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda_tipo_menu/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/vianda_tipo_menu/form.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar tipo de men&uacute; de vianda</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_vianda_tipo_menu_gastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('ventas.vianda_tipo_menu.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
