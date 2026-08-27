@extends("theme.$theme.layout")
@section('titulo')
    Ordenes de Venta
@endsection

@section("scripts")
<script>window.REQUIERE_VALIDACION_PADRON_OPERACION = true;</script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/padron-operacion.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/arbolaprobacion/panel_ia.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ordenventa/ordenventa/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/localidad-cascada.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/localidad-cascada.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilio.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/domicilio.js')) ?: time() }}" type="text/javascript"></script>
<script>
    var urlCreaCliente = "{{ route('crear_cliente_remoto', ':id') }}";
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";

</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                @if (!isset($visualizar))
                    <h3 class="card-title">Editar Orden de Venta - Número {{$data->numeroordenventa ?? ''}} - Id {{$data->id}}</h3>
                    <div class="card-tools">
                        @if ($data->estado == 'PENDIENTE' || $data->estado == 'FACTPARCIAL')
                            <button type="button" id="generafactura" onclick="generaFactura()" class="btn btn-primary" data-padron-accion-factura="1">
                                <i class="fa fa-fw fa-print"></i>
                                Facturar
                            </button>
                        @endif        
                        @php
                            $estadosReenvioArbol = ['SOLICITADA', 'RECHAZADA'];
                        @endphp
                        @if (in_array($data->estado ?? '', $estadosReenvioArbol, true))
                            <form action="{{ route('reenviar_arbol_aprobacion_ordenventa', ['id' => $data->id]) }}" method="POST" style="display:inline"
                                onsubmit="return confirm('¿Realmente desea volver a enviar la orden de venta al árbol de aprobación?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Elimina los movimientos previos del árbol y vuelve a disparar el flujo desde el primer nivel">
                                    <i class="fa fa-fw fa-sitemap"></i>
                                    Reenviar al árbol de aprobación
                                </button>
                            </form>
                        @endif
                        <a href="{{route('consulta_ordenventa')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                        @if ($data->estado != "FACTURADA" && $data->estado != "COBRADA")
                            <button type="submit" onclick="anulaOrdenVenta()" id="anulaordenventa" class="btn btn-warning">
                                <i class="fa fa-fw fa-cross"></i>
                                Anular la Orden de Venta
                            </button>
                        @else
                            <input type="hidden" id="anulaordenventa" value="">
                        @endif
                    </div>
                @else
                    <h3 class="card-title">Visualizar Orden de Venta - Número {{$data->numeroordenventa ?? ''}}</h3>
                @endif
            </div>
            <form action="{{route('actualiza_ordenventa', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Cuotas
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>                    
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Arbol aprobación
                    </button>     
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-calculator"></span> Comprobantes
                    </button>                  
                </div>
                <div class="card-body">
                    @include('ordenventa.ordenventa.form')
                    @include('ordenventa.ordenventa.form3')
                    @include('ordenventa.ordenventa.form2')
                    @include('ordenventa.ordenventa.form4')
                    @include('ordenventa.ordenventa.form5')
                    @include('ordenventa.ordenventa.form6')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        @if (!isset($visualizar))
                            <div class="col-lg-6">
                                @include('includes.boton-form-editar')
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('ordenventa.ordenventa.modalfacturaordenventa')

@endsection
