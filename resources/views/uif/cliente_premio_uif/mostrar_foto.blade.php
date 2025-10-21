@extends("theme.$theme.layout")
@section('titulo')
    Muestra Foto Premios UIF
@endsection

@section("styles")
<link href="{{asset("assets/js/bootstrap-fileinput/css/fileinput.min.css")}}" rel="stylesheet" type="text/css"/>
@endsection

@section("scriptsPlugins")
<script src="{{asset("assets/js/bootstrap-fileinput/js/fileinput.min.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/js/locales/es.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/themes/fas/theme.min.js")}}" type="text/javascript"></script>
@endsection


@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_premio_uif/crear.js")}}" type="text/javascript"></script>
<!-- Bootstrap Date-Picker Plugin -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Muestra Foto Premio UIF &nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->clientes_uif->nombre}}&nbsp;Doc.: {{$data->clientes_uif->numerodocumento}}</h3>
                <div class="card-tools">
                    @if (isset($referer))
                        <a href="{{URL::previous()}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al cliente
                        </a>
                    @else
                        <a href="{{route('consulta_cliente_premio_uif')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualiza_cliente_premio_uif', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    <img src="{{ asset("storage/imagenes/fotos_uif/$data->foto") }}" alt="">
                </div>
                <div class="card-footer" style="padding-top: 5px;">
                	<div class="row">
                   		<div class="col-lg-4">
                            <a href="{{URL::previous()}}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Salir
                            </a>
                    	</div>
            		</div>
            	</div>
            </form>
        </div>
    </div>
</div>
@endsection
