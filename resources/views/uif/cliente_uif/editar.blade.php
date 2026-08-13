@extends("theme.$theme.layout")
@section('titulo')
    Clientes UIF
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/domicilionacimiento.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/actividad_uif/consulta.js")}}" type="text/javascript"></script>
@include('uif.cliente_uif.partials.sexo_aprendizaje_script')
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/arca-padron.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/crear.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/uif/cliente_uif/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/imprimirHtml.js")}}" type="text/javascript"></script>

<!-- Bootstrap Date-Picker Plugin -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
<script>
    function sub()
	{
		$('#form-general').trigger('submit');
	}
</script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consulta_cliente_uif', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if (!empty($soloSolapaPremios))
                        {{ esSoloVisualizacionClienteUif() ? 'Ver' : 'Gestionar' }} premios — ID {{ $data->id }} {{ $data->nombre }}
                    @else
                        {{ esSoloVisualizacionClienteUif() ? 'Ver' : 'Editar' }} Cliente UIF
                    @endif
                </h3>
                @if (empty($soloSolapaPremios))
                    &nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}
                @endif
                <div class="card-tools">
                    @if (empty($soloSolapaPremios))
					<button type="button" id="botonestado" class="btn btn-info btn-sm">
                        <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                    </button>
                    @endif
                    @if (empty($ocultarVolver ?? false))
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualiza_cliente_uif', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                @if (!empty($ocultarVolver))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="d-flex align-items-center flex-wrap" style="margin: 5px;">
                    @if (empty($soloSolapaPremios))
                    <div class="d-flex flex-wrap justify-content-center align-items-center py-1 w-100" style="gap: 0.45rem;">
                        <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                            <i class="fa fa-user"></i> Datos principales
                        </button>
                        <button type="button" id="botonform2" class="btn btn-primary btn-sm">
                            <i class="fa fa-user"></i> Datos UIF
                        </button>
                        <button type="button" id="botonform3" class="btn btn-primary btn-sm">
                            <span class="fa fa-copy"></span> Premios
                        </button>
                        <button type="button" id="botonform4" class="btn btn-info btn-sm">
                            <span class="fa fa-copy"></span> Riesgo
                        </button>
                        <button type="button" id="botonform5" class="btn btn-info btn-sm">
                            <span class="fa fa-copy"></span> Archivos asociados
                        </button>
                        <button type="button" id="btn-consulta-arca-padron-crear" class="btn btn-outline-secondary btn-sm" title="Ingresá el CUIT y consultá el padrón ARCA">
                            <i class="fa fa-search"></i> Consulta padrón ARCA
                        </button>
                    </div>
                    @endif
                </div>
                @if (empty($soloSolapaPremios))
                    @include('uif.cliente_uif.partials.banner_cumplimiento')
                @endif
                <div id="tab2" class="d-none" data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}" aria-hidden="true"></div>
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('uif.cliente_uif.form1')
                    @include('uif.cliente_uif.form2')
                    @include('uif.cliente_uif.form3')
                    @include('uif.cliente_uif.form4')
                    @include('uif.cliente_uif.form5')
                </div>
                <div class="card-footer" style="padding-top: 0">
                	@if (empty($soloSolapaPremios))
                	<div class="row">
                   		<div class="col-lg-4">
                        	@if (can('actualizar-cliente-uif', false))
                        	<button type="button" onclick="sub()" class="btn btn-success">Actualizar</button>
                        	@else
                        	<span class="text-muted"><i class="fa fa-lock"></i> Solo visualización</span>
                        	@endif
                    	</div>
            		</div>
            		@endif
            	</div>
            </form>
            @include('compras.proveedor.arca-cuit-entry-modal')
            @include('compras.proveedor.arca-padron-modals')
        </div>
    </div>
</div>
@endsection
