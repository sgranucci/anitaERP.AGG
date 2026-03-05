@extends("theme.$theme.layout")
@section('titulo')
    Ordenes de trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script>
    var tareaEmpaque = "{{ config('consprod.TAREA_EMPAQUE') }}";
    var tareaFacturada = "{{ config('consprod.TAREA_FACTURADA') }}";
    var tareaTerminada = "{{ config('consprod.TAREA_TERMINADA') }}";
    var tareaTerminadaStock = "{{ config('consprod.TAREA_TERMINADA_STOCK') }}";
    var PROFORMA = "{{ config('cliente.PROFORMA') }}";
    var MOROSO = "{{ config('cliente.MOROSO') }}";
    var NO_FACTURAR = "{{ config('cliente.NO_FACTURAR') }}";
    var CLIENTE_STOCK_ID = "{{ config('cliente.CLIENTE_STOCK_ID') }}";
</script>
<script src="{{asset("assets/pages/scripts/ventas/ordentrabajo/editar.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Orden de trabajo</h3>
				&nbsp;- ID: {{ $ordentrabajo->id }} - Orden de trabajo: {{$ordentrabajo->codigo}}

                <div class="card-tools">
                    <a href="{{route('ordentrabajo')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_ordentrabajo', ['id' => $ordentrabajo->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$ordentrabajo->codigo}}" >
        			<input type="hidden" id="ordentrabajoid" name="ordentrabajoid" value="{{$ordentrabajo->id}}" >
                    @include('ventas.ordentrabajo.formeditar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
