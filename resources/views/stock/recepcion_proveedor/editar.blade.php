@extends("theme.$theme.layout")
@section('titulo')
Recepción {{ $recepcion->numerorecepcion }}
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/consulta_oc.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
@if (config('recepcion_proveedor.modal_articulo_proveedor_habilitado'))
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-truck"></i>
                    Recepción {{ $recepcion->numerorecepcion }}
                    @if($recepcion->estado === 'BORRADOR')
                        <span class="badge badge-secondary ml-2">BORRADOR</span>
                    @elseif($recepcion->estado === 'CONFIRMADA')
                        <span class="badge badge-success ml-2">CONFIRMADA</span>
                    @elseif($recepcion->estado === 'ANULADA')
                        <span class="badge badge-dark ml-2">ANULADA</span>
                    @else
                        <span class="badge badge-info ml-2">{{ $recepcion->estado }}</span>
                    @endif
                    @if($recepcion->fl_precio_diferencia)
                        <span class="badge badge-warning ml-2">Precio distinto a OC</span>
                    @endif
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
                        'recepcionId' => $recepcion->id,
                        'clase' => 'btn btn-danger btn-sm mr-2',
                    ])
                    @if($recepcion->estado === 'BORRADOR' && can('confirmar-recepcion-proveedor', false))
                    <button type="submit" class="btn btn-success btn-sm mr-2" form="form-recepcion-confirmar"
                            id="btn-confirmar-recepcion-proveedor">
                        <i class="fa fa-check"></i> Confirmar
                    </button>
                    @endif
                    <a href="{{ route('recepcion_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            @if($recepcion->estado === 'BORRADOR')
            <form action="{{ route('actualizar_recepcion_proveedor', $recepcion->id) }}" id="form-recepcion-proveedor" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('stock.recepcion_proveedor.form', [
                        'modoEdicion' => true,
                        'recepcion' => $recepcion,
                        'asientoPreview' => $asientoPreview ?? ['activo' => false],
                    ])
                </div>
            </form>
            <form action="{{ route('confirmar_recepcion_proveedor', $recepcion->id) }}" id="form-recepcion-confirmar" method="POST" class="d-none" aria-hidden="true">
                @csrf
            </form>
            <form action="{{ route('eliminar_recepcion_proveedor', ['id' => $recepcion->id]) }}" id="form-recepcion-eliminar" method="POST" class="d-none" aria-hidden="true">
                @csrf
                @method('DELETE')
            </form>
            <div class="card-footer">
                @include('stock.recepcion_proveedor.partials.acciones_borrador')
            </div>
            @else
            <div class="card-body">
                @include('stock.recepcion_proveedor.form', [
                    'modoEdicion' => false,
                    'recepcion' => $recepcion,
                    'asientoPreview' => $asientoPreview ?? ['activo' => false],
                ])
            </div>
            <div class="card-footer">
                @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
                    'recepcionId' => $recepcion->id,
                    'clase' => 'btn btn-danger mr-2 mb-2',
                ])
                @if($recepcion->estado === 'CONFIRMADA' && $recepcion->tipo === 'RECEPCION')
                @can('devolver-recepcion-proveedor')
                <a href="{{ route('crear_devolucion_recepcion_proveedor', $recepcion->id) }}" class="btn btn-warning">
                    <i class="fa fa-undo"></i> Devolución a proveedor
                </a>
                @endcan
                @endif
                @if($recepcion->estado === 'CONFIRMADA')
                @can('anular-recepcion-proveedor')
                <form action="{{ route('anular_recepcion_proveedor', $recepcion->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Anular recepción? Revierte stock, asiento (ctamov) y registros Anita (recepmae/recepmov/recpunica).');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fa fa-ban"></i> Anular recepción
                    </button>
                </form>
                @endcan
                @endif
            </div>
            @endif
        </div>
        @include('stock.recepcion_proveedor.partials.partes_unicas')
    </div>
</div>
@if($recepcion->estado === 'BORRADOR')
@include('stock.recepcion_proveedor.partials.modal_accion_lineas_sin_cantidad')
@endif
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaordencompra_recepcion')
@endsection
