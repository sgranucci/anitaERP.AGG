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
<script src="{{asset("assets/pages/scripts/admin/localidad-cascada.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/localidad-cascada.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/domicilio.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/domicilio.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilioentrega.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/domicilioentrega.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/localidad/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vendedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cobrador/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/distribuidor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/listaprecio/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
@php
    $clienteModalesAbmJs = public_path('assets/pages/scripts/ventas/cliente/consultas-modales-abm.js');
    $arcaPadronJs = public_path('assets/pages/scripts/ventas/cliente/arca-padron.js');
    $arcaPadronAsyncJs = public_path('assets/pages/scripts/compras/arca-padron-validacion-async.js');
    $arcaValidacionAbmJs = public_path('assets/pages/scripts/ventas/cliente/arca-validacion-abm.js');
    $clienteCrearJs = public_path('assets/pages/scripts/ventas/cliente/crear.js');
@endphp
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consultas-modales-abm.js') }}?v={{ file_exists($clienteModalesAbmJs) ? filemtime($clienteModalesAbmJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/arca-padron.js') }}?v={{ file_exists($arcaPadronJs) ? filemtime($arcaPadronJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-padron-validacion-async.js') }}?v={{ file_exists($arcaPadronAsyncJs) ? filemtime($arcaPadronAsyncJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/arca-validacion-abm.js') }}?v={{ file_exists($arcaValidacionAbmJs) ? filemtime($arcaValidacionAbmJs) : time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/crear.js') }}?v={{ file_exists($clienteCrearJs) ? filemtime($clienteCrearJs) : time() }}" type="text/javascript"></script>
<script>
$( "#botonform0" ).click(function() {
  $( "#form-general" ).submit();
});
</script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('cliente', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @include('ventas.cliente.partials.arca_impuestos_alerta')
        @include('ventas.cliente.partials.cuit_duplicado_alerta')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center">
                <h3 class="card-title mb-0">
                    Crear Cliente
                    @if ($tipoalta == 'P')
                        Provisorio
                    @endif
                </h3>
                @include('ventas.cliente.partials.codigo_barra')
                <div class="card-tools">
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta
                        </a>
                    @else
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            @if ($tipoalta == 'P')
                <form action="{{ route('guardar_cliente_provisorio', $filtrosQuery ?? []) }}" id="form-general" data-consultas-modales-abm="1" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @else
                <form action="{{ route('guardar_cliente', $filtrosQuery ?? []) }}" id="form-general" data-consultas-modales-abm="1" class="form-horizontal form--label-right" method="POST" autocomplete="off">
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
                        @include('ventas.cliente.form10')
                    </div>
                    @include('ventas.cliente.partials.arca_padron_support')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="button" id="botonform0" class="btn botonsubmit btn-success">
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
