@extends("theme.$theme.layout")
@section('titulo')
    @if (request()->input('vista') === 'consulta')
        Consulta de OT
    @else
        Ordenes de trabajo
    @endif
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
@include('includes.ventas.cliente_politica_contexto', ['contextoPoliticaCliente' => 'boleta'])
<script src="{{ asset('assets/pages/scripts/ventas/ordentrabajo/editarferli.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/ordentrabajo/editarferli.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/ordentrabajo/factura_cliente.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/ordentrabajo/factura_cliente.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if (request()->input('vista') === 'consulta')
                        Consulta de OT
                    @else
                        Editar orden de trabajo
                    @endif
                    — {{ str_pad($ordentrabajo->codigo, 4, '0', STR_PAD_LEFT) }}
                </h3>

                <div class="card-tools">
                    @if (request()->input('vista') === 'consulta')
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="window.close()">
                            <i class="fa fa-fw fa-times"></i> Cerrar solapa
                        </button>
                    @else
                        <a href="{{ route('ordentrabajo') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_ordentrabajo', ['id' => $ordentrabajo->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$ordentrabajo->codigo}}" >
        			<input type="hidden" id="ordentrabajoid" name="ordentrabajoid" value="{{$ordentrabajo->id}}" >
                    @include('ventas.ordentrabajo_ferli.formeditar')
                </div>
                <div class="card-footer">
                    <div class="row">
                        
                    </div>
                </div>
            </form>
            @php
                $mostrarQuitarTerminadaStock = can('editar-ordenes-de-trabajo', false)
                    && app(\App\Support\Ventas\QuitarTerminadaStockOtSupport::class)->sePuedeQuitar($ordentrabajo);
            @endphp
            @if ($mostrarQuitarTerminadaStock)
                <form id="form-quitar-terminada-stock" action="{{ route('quitar_terminada_stock_ordentrabajo', ['id' => $ordentrabajo->id]) }}" method="POST" class="d-none" onsubmit="return confirm('¿Quitar Terminada stock? La OT vuelve a figurar en fabricación.');">
                    @csrf
                </form>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultacliente')
@endsection
