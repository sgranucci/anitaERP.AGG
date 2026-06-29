@extends("theme.$theme.layout")
@section('titulo')
    Clientes
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilioentrega.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/localidad/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vendedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/distribuidor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/arca-padron.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/crear.js")}}" type="text/javascript"></script>
@if (config('suitecrm.habilitado'))
@php($suitecrmNotasJs = public_path('assets/pages/scripts/ventas/cliente/suitecrm-notas.js'))
<script src="{{ asset('assets/pages/scripts/ventas/cliente/suitecrm-notas.js') }}?v={{ file_exists($suitecrmNotasJs) ? filemtime($suitecrmNotasJs) : time() }}" type="text/javascript"></script>
@endif
<script src="{{asset("assets/pages/scripts/admin/imprimirHtml.js")}}" type="text/javascript"></script>
<script>
    $(function () {
        $("#botontipoalta").click(function(){
                var tipoalta = 'D';
                
                $("#tipoalta").val(tipoalta);
                $("#botontipoalta").css('visibility', 'hidden');
        });
    });
    function sub()
	{
        
		$('#form-general').submit();
	}
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @include('ventas.cliente.partials.arca_impuestos_alerta')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Cliente </h3>&nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}
                
                @if ($tipoalta == config('cliente.tipoalta')['PROVISORIO'][0])
                    &nbsp; CLIENTE PROVISORIO
                @endif

                <div class="card-tools">
                    @if (can('modifica-emite-nota-de-credito', false))
                        <button type="button" id="botonemitenc" class="btn btn-info btn-sm border btn-danger">
                            <i id="iconoemitenc" class="fa fa-times"></i> Emite NC
                        </button>
                    @endif
                    @if ($tipoalta == config('cliente.tipoalta')['PROVISORIO'][0])
                        <button type="button" id="botontipoalta" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Cambia a DEFINITIVO
                        </button>
                    @endif
                   	@if (can('suspender-clientes', false))
                        <button type="button" id="botonestado" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                        </button>
                    @else
                        <button type="button" id="_" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                        </button>
                    @endif
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                        </a>
                    @else
                        <a href="{{route('cliente')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                    @if (can('listar-cuentacorriente-cliente', false))
                        <a href="{{route('listar_cuentacorriente_cliente', ['id' => $data->id])}}" target="_blank" class="btn btn-secondary btn-sm" title="Cuenta Corriente">
                            <i class="fa fa-folder-open">Cuenta Corriente</i>
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_cliente', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off"
                data-cliente-id="{{ $data->id }}"
                data-arca-validar-url="{{ route('validar_cliente_arca_padron', ['id' => $data->id]) }}"
            >
                @csrf @method("put")
                <div class="card-body pt-0 pb-0">
                    <input type="hidden" id="emitenotadecredito" name="emitenotadecredito" value="{{old('emitenotadecredito', $data->emitenotadecredito ?? '')}}" >
                    @include('ventas.cliente.partials.tabs_header', ['mostrarSuitecrm' => config('suitecrm.habilitado')])
                    <div class="tab-content pt-3 px-1">
                        @include('ventas.cliente.form1')
                        @include('ventas.cliente.form2')
                        @include('ventas.cliente.form3')
                        @include('ventas.cliente.form4')
                        @include('ventas.cliente.form5')
                        @include('ventas.cliente.form6')
                        @include('ventas.cliente.form7')
                        @include('ventas.cliente.form8')
                        @if (config('suitecrm.habilitado'))
                            @include('ventas.cliente.form9')
                        @endif
                        @include('ventas.cliente.suspensionmodal')
                    </div>
                    @include('ventas.cliente.partials.arca_padron_support')
                </div>
                <div class="card-footer" style="padding-top: 0">
                	<div class="row">
                   		<div class="col-lg-4">
                        	<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                    	</div>
            		</div>
            	</div>
            </form>
            @include('compras.proveedor.arca-cuit-entry-modal')
        </div>
    </div>
    @if (config('suitecrm.habilitado'))
        @include('ventas.cliente.suitecrm-nota-modal')
    @endif
</div>
@endsection
