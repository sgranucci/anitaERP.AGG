@extends("theme.$theme.layout")
@section('titulo')
Recepción {{ $recepcion->numerorecepcion }}
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-{{ $recepcion->estado === 'CONFIRMADA' ? 'success' : ($recepcion->estado === 'ANULADA' ? 'secondary' : 'warning') }}">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-truck"></i>
                    Recepción {{ $recepcion->numerorecepcion }}
                    @if($recepcion->fl_precio_diferencia)
                        <span class="badge badge-warning ml-2">Precio distinto a OC</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            @if($recepcion->estado === 'BORRADOR')
            <form action="{{ route('actualizar_recepcion_proveedor', $recepcion->id) }}" id="form-recepcion-proveedor" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('stock.recepcion_proveedor.form', ['modoEdicion' => true, 'recepcion' => $recepcion])
                </div>
                <div class="card-footer">
                    @can('actualizar-recepcion-proveedor')
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
                    @endcan
                    @can('confirmar-recepcion-proveedor')
                    <form action="{{ route('confirmar_recepcion_proveedor', $recepcion->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Confirmar recepción? Generará movimiento de stock y asiento contable.');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check"></i> Confirmar recepción
                        </button>
                    </form>
                    @endcan
                </div>
            </form>
            @else
            <div class="card-body">
                @include('stock.recepcion_proveedor.form', ['modoEdicion' => false, 'recepcion' => $recepcion])
            </div>
            <div class="card-footer">
                @can('listar-recepcion-proveedor')
                <a href="{{ route('recepcion_proveedor_com_pdf', $recepcion->id) }}" class="btn btn-danger" target="_blank">
                    <i class="fa fa-file-pdf-o"></i> Imprimir COM (PDF)
                </a>
                @endcan
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
@include('includes.stock.modalconsultadeposito')
@endsection
