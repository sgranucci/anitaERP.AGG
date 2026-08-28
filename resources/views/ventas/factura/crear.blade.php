@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de venta
@endsection

@section("scripts")
<script>window.VALIDACION_PADRON_POST_CARGA = true;</script>
<script>window.REQUIERE_VALIDACION_PADRON_OPERACION = true;</script>
@include('includes.ventas.cliente_despacho_js', ['clienteDespachoNoFacturar' => true])
@php
    $requiereValidacionApocOperacion = filter_var(config('arca_wsapoc.validar_factura_cliente', true), FILTER_VALIDATE_BOOLEAN)
        && filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<script>window.REQUIERE_VALIDACION_APOC_OPERACION = @json($requiereValidacionApocOperacion);</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@php
    $layoutItemsPedido = $layoutItemsPedido ?? facturaUsaLayoutItemsPedido();
@endphp
<script>
    window.FL_FACTURA_LAYOUT_PEDIDO = @json($layoutItemsPedido);
    window.FACTURA_URLS = {
        preferencias: @json(route('factura_preferencias'))
    };
</script>
@include('includes.ventas.preferencias_facturacion_scripts')
@include('ventas.partials.aviso_deposito_facturacion')
<script src="{{asset("assets/pages/scripts/ventas/factura/crear.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/factura/crear.js')) ?: time() }}" type="text/javascript"></script>
@if ($layoutItemsPedido)
<script src="{{asset("assets/pages/scripts/ventas/factura/crear-bierzo-items.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/factura/crear-bierzo-items.js')) ?: time() }}" type="text/javascript"></script>
@endif
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vendedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>

<script>
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
                <h3 class="card-title">Crear comprobante de venta</h3>
                <div class="card-tools">
                    @include('includes.ventas.link_mi_impresora')
                    <a href="{{route('factura')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_factura')}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-articulo-solo-facturable="1" data-factura-proceso="factura" data-sin-bloqueo-grabacion="1" data-factura-redirect="{{ route('factura') }}" onsubmit="return typeof validarSubmitFacturaConOverlay === 'function' ? validarSubmitFacturaConOverlay(event) : (typeof validarPadronOperacionAntesSubmitForm === 'function' ? validarPadronOperacionAntesSubmitForm(event) : true);">
                @csrf
                <div class="card-body">
                    @php $datos = ["funcion" => "crear", "layoutItemsPedido" => $layoutItemsPedido]; @endphp
                    @include('ventas.factura.form', $datos)
                </div>
                <div class="card-footer">
                    <button type="button" onclick="subm()" class="btn btn-success factura-carga-bloqueable" data-padron-accion-factura="1">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.arca_apoc_validacion_modal')
@include('includes.proceso-overlay-factura')
@endsection
