@extends("theme.$theme.layout")
@section('titulo')
    Comprobante de Ventas
@endsection

@section("scripts")
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
<script>window.REQUIERE_VALIDACION_PADRON_OPERACION = true;</script>
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@include('includes.ventas.preferencias_facturacion_scripts')
<script src="{{asset("assets/pages/scripts/ventas/factura/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vendedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>

<script>
	function sub()
	{
        if (typeof window.formularioVentasBloqueadoPorPadron === 'function' && window.formularioVentasBloqueadoPorPadron()) {
            if (typeof window.notificarBloqueoPadronCliente === 'function') {
                window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede operar con este cliente.');
            } else {
                alert('Problemas en ARCA: no puede operar con este cliente.');
            }
            return false;
        }

        $('#formgeneral').submit();
    }
    $(function () {
        $("#cliente_id").change(function(){
            var cliente_id = $(this).val();
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, true);
            var $link = $('#link-editar-cliente-factura');
            if ($link.length) {
                if (parseInt(cliente_id, 10) > 0) {
                    $link.attr('href', carpetaBase + '/ventas/cliente/' + cliente_id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
                } else {
                    $link.attr('href', '#').addClass('d-none');
                }
            }
            setTimeout(() => {
                muestraTipoSuspension();			
            }, 1500);
        });

		$("#divlugar").show();
		$("#divcodigoentrega").hide();

        var cliente_id = $("#cliente_id").val();
		if (cliente_id > 0)
        	completarCliente_Entrega(cliente_id);
	  });

</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                @if (isset($flGeneraNotaDeCredito))
                    <h3 class="card-title">Generar nota de cr&eacute;dito</h3>
                @else
                    <h3 class="card-title">Editar comprobante de venta</h3>
                @endif
                <span class="ml-2">ID {{ $data->id }} — {{ $data->codigo }}</span>
                <div class="card-tools">
                    @if (can('listar-factura', false))
                        <a href="{{route('lista_una_factura', ['id' => $data->id])}}" class="btn btn-outline-light btn-sm" title="Listar el comprobante">
                            <i class="fa fa-print"></i> Listar comprobante
                        </a>
                    @endif
                    <a href="{{ isset($urlOrigen) ? 'javascript:history.back()' : route('factura') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> {{ isset($urlOrigen) ? 'Volver atrás' : 'Volver al listado' }}
                    </a>
                </div>
            </div>
            <form action="{{route('grabar_comprobante')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" onsubmit="return typeof validarPadronOperacionAntesSubmitForm === 'function' ? validarPadronOperacionAntesSubmitForm(event) : true;">
                @csrf @method("put")
                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas px-3 pt-2">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" id="botonform1" role="tab">
                                <i class="fa fa-file-invoice"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="botonform2" role="tab">
                                <i class="fa fa-copy"></i> Asiento contable
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$data->codigo}}" >
        			<input type="hidden" id="venta_id" name="venta_id" value="{{$data->id??''}}" >
                    @php $datos = ['funcion' => 'editar', 'consultaFacturasDia' => $consultaFacturasDia ?? false]; @endphp
                    @include('ventas.factura.form', $datos)
                    @include('includes.contable.formasientoexterno')
                </div>
                <div class="card-footer">
                    @if (isset($flGeneraNotaDeCredito))
                        <button type="submit" onclick="sub()" class="btn btn-success factura-carga-bloqueable" data-padron-accion-factura="1">
                            <i class="fa fa-undo"></i> Generar nota de cr&eacute;dito
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@endsection
