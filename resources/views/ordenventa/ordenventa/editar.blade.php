@extends("theme.$theme.layout")
@section('titulo')
    Ordenes de Venta
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ordenventa/ordenventa/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilio.js")}}" type="text/javascript"></script>
<script>
    var urlCreaCliente = "{{ route('crear_cliente_remoto', ':id') }}";
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
                    <h3 class="card-title">Editar Orden de Venta - Número {{$data->numeroordenventa ?? ''}}</h3>
                    <div class="card-tools">
                        @if ($data->estado == 'SOLICITADA')
                            <button type="submit" onclick="generaFactura()" class="btn btn-primary">
                                <i class="fa fa-fw fa-print"></i>
                                Factura
                            </button>
                        @endif                   
                        @if ($data->estado == 'FACTURADA')
                            <button type="submit" onclick="cobraFactura()" class="btn btn-primary">
                                <i class="fa fa-fw fa-cash-register"></i>
                                Cobra
                            </button>
                        @endif    
                        <a href="{{route('consulta_ordenventa')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
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
                </div>
                <div class="card-body">
                    @include('ordenventa.ordenventa.form')
                    @include('ordenventa.ordenventa.form3')
                    @include('ordenventa.ordenventa.form2')
                    @include('ordenventa.ordenventa.form4')
                    @include('ordenventa.ordenventa.form5')
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
@endsection
