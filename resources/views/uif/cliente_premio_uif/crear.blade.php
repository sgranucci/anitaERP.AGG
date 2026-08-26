@extends("theme.$theme.layout")
@section('titulo')
    Premios UIF
@endsection

@section("styles")
<link href="{{asset("assets/js/bootstrap-fileinput/css/fileinput.min.css")}}" rel="stylesheet" type="text/css"/>
@include('uif.cliente_premio_uif.partials.foto_estilos')
@endsection

@section("scriptsPlugins")
<script src="{{asset("assets/js/bootstrap-fileinput/js/fileinput.min.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/js/locales/es.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/themes/fas/theme.min.js")}}" type="text/javascript"></script>
@endsection


@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_premio_uif/crear.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/uif/cliente_premio_uif/crear.js')) ?: time() }}" type="text/javascript"></script>
<!-- Bootstrap Date-Picker Plugin -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
@endsection

@section('contenido')
@php
    $cumplimientoUif = $cumplimientoUif ?? ['items' => []];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                @if (config('app.empresa') == 'AGG')
                    <img src="{{ asset('storage/imagenes/logos/AGG.png') }}" alt="AGG" class="mr-3 mb-2 mb-md-0 align-middle" style="max-height: 44px;">
                @endif
                <h3 class="card-title mb-0 flex-grow-1">Crear Premio UIF - {{$nombrecliente}} - {{$numerodocumento}}</h3>
                <div class="card-tools ml-auto">
                    @if (!empty($volverAClienteUif) && !empty($referer))
                        <a href="{{ $referer }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-arrow-left"></i> Volver atrás
                        </a>
                    @else
                        <a href="{{ route('consulta_cliente_premio_uif') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('guarda_cliente_premio_uif')}}" id="form-general" class="form-horizontal form--label-right" method="POST"  enctype="multipart/form-data"  autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                @include('uif.cliente_uif.partials.banner_cumplimiento', ['cumpEsExterno' => true])
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('uif.cliente_premio_uif.form1')
                    @include('uif.cliente_premio_uif.form2')
                </div>
                <div class="card-footer">
                	<div class="row">
                   		<div class="col-lg-4">
							<button type="button" id="botonform0" class="btn btn-success">
						   	<i class="fa fa-save"></i> Guardar
							</button>
                    	</div>
            		</div>
            	</div>
            </form>
        </div>
    </div>
</div>
@include('uif.cliente_uif.partials.modal_cumplimiento', [
    'cumpEsExterno' => true,
    'cumpHrefFicha' => ($cumplimientoUif['urlsTab']['2'] ?? ($cumplimientoUif['urlsTab']['1'] ?? '#')),
])
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'premio-uif-guardando-overlay',
    'tituloId' => 'premio-uif-guardando-titulo',
    'subtituloId' => 'premio-uif-guardando-subtitulo',
    'titulo' => 'Guardando premio…',
    'subtitulo' => 'Por favor espere. No cierre ni recargue la página.',
])
@endsection
