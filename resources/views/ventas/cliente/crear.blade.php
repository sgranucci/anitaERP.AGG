@extends("theme.$theme.layout")
@section('titulo')
    Clientes
@endsection

@section("styles")

input:invalid {
  background-color: pink;
}

@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilioentrega.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/localidad/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vendedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/distribuidor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/listaprecio/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
@php($arcaPadronJs = public_path('assets/pages/scripts/ventas/cliente/arca-padron.js'))
<script src="{{ asset('assets/pages/scripts/ventas/cliente/arca-padron.js') }}?v={{ file_exists($arcaPadronJs) ? filemtime($arcaPadronJs) : time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/crear.js")}}" type="text/javascript"></script>
<script>
$( "#botonform0" ).click(function() {
  $( "#form-general" ).submit();
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @include('ventas.cliente.partials.arca_impuestos_alerta')
        @include('ventas.cliente.partials.cuit_duplicado_alerta')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Cliente @if ($tipoalta == 'P') Provisorio @endif</h3>
                <div class="card-tools">
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta
                        </a>
                    @else
                        <a href="{{route('cliente')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            @if ($tipoalta == 'P')
                <form action="{{route('guardar_cliente_provisorio')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @else
                <form action="{{route('guardar_cliente')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @endif
                @csrf
                <input type="hidden" id="emitenotadecredito" name="emitenotadecredito" value="{{old('emitenotadecredito', $data->emitenotadecredito ?? '')}}" >
                <div class="card-body pt-0 pb-0">
                    <input type="hidden" id="emitenotadecredito" name="emitenotadecredito" value="{{old('emitenotadecredito', $data->emitenotadecredito ?? '')}}" >
                    @include('ventas.cliente.partials.tabs_header', ['mostrarSuitecrm' => false])
                    <div class="tab-content pt-3 px-1">
                        @include('ventas.cliente.form1')
                        @include('ventas.cliente.form2')
                        @include('ventas.cliente.form3')
                        @include('ventas.cliente.form4')
                        @include('ventas.cliente.form5')
                        @include('ventas.cliente.form6')
                        @include('ventas.cliente.form7')
                        @include('ventas.cliente.form8')
                    </div>
                    @include('ventas.cliente.partials.arca_padron_support')
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
            @include('compras.proveedor.arca-cuit-entry-modal')
        </div>
    </div>
</div>
@endsection
