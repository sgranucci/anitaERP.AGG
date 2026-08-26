@extends("theme.$theme.layout")
@section('titulo')
    Remitos de clientes
@endsection

@section("scripts")
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/zonavta/consulta.js")}}" type="text/javascript"></script>
@include('includes.ventas.preferencias_facturacion_scripts')
@include('includes.ventas.cliente_despacho_js')
<script src="{{ asset('assets/pages/scripts/ventas/remito/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/remito/crear.js')) ?: time() }}" type="text/javascript"></script>
<script>
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
	function sub()
	{
        if (window.AnitaGrabacion && window.AnitaGrabacion.enCurso()) {
            return false;
        }

        if ($('#formgeneral').hasClass('pedido-bloqueado-padron')) {
            if (typeof window.notificarBloqueoPadronCliente === 'function') {
                window.notificarBloqueoPadronCliente('Problemas en ARCA: no puede guardar el remito con este cliente.');
            } else {
                alert('Problemas en ARCA: no puede guardar el remito con este cliente.');
            }
            return false;
        }

        // Cuenta los articulos para validar cantidad maxima
        var cantidadArticulo = 0;

        $("#tbody-tabla .articulo").each(function(index) {
            cantidadArticulo = cantidadArticulo + 1;
        });

        if (typeof validarLugarEntregaAntesGuardar === 'function' && !validarLugarEntregaAntesGuardar()) {
            return false;
        }

        if (typeof validarListaprecioLineasFormularioVentas === 'function'
            && !validarListaprecioLineasFormularioVentas('#tbody-tabla tr')) {
            return false;
        }

        if (cantidadArticulo > 42)
        {
            alert("No puede generar remitos con mas de 42 ítems");
            return false;
        }

        var form = document.getElementById('formgeneral');
        $('.remito-carga-bloqueable').filter('button, input[type="submit"]').prop('disabled', true);
        if (window.AnitaGrabacion && typeof window.AnitaGrabacion.enviar === 'function') {
            window.AnitaGrabacion.enviar(form);
        } else if (form) {
            form.submit();
        }
        return false;
	}

    $(function () {
        $("#cliente_id").change(function(){
            var cliente_id = $(this).val();
            completarCliente_Entrega(cliente_id);
            asignaDatosCliente(cliente_id, true);
            setTimeout(() => {
                muestraTipoSuspension();			
            }, 1500);
        });

		$("#divlugar").show();

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
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Remitos de clientes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-warning btn-sm" onclick="abrirAsignarKilosRemito()" title="F5 Anita: asigna kilos por reparto">
                        <i class="fa fa-fw fa-balance-scale"></i> Asignar kilos (F5)
                    </button>
                    <a href="{{route('remito')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_remito')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" data-mensaje-grabacion="Grabando remito…" onsubmit="return typeof validarLugarEntregaAntesGuardar !== 'function' || validarLugarEntregaAntesGuardar();">
                @csrf
                <div class="card-body">
                    @php $datos = ["funcion" => "crear"]; @endphp
                    @include('ventas.remito.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="return sub()" class="btn btn-success remito-carga-bloqueable">Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@endsection
