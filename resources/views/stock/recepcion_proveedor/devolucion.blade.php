@extends("theme.$theme.layout")
@section('titulo')
Devolución — {{ $recepcion->numerorecepcion }}
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/form.js')) ?: time() }}" type="text/javascript"></script>
@if (config('recepcion_proveedor.modal_articulo_proveedor_habilitado'))
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/modal_articulo_proveedor.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-undo"></i> Devolución a proveedor</h3>
                <div class="card-tools">
                    <a href="{{ url('stock/recepcion-proveedor/'.$recepcion->id.'/editar') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a recepción origen
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_devolucion_recepcion_proveedor', $recepcion->id) }}" id="form-recepcion-proveedor" method="POST">
                @csrf
                <input type="hidden" name="ordencompra_id" value="{{ $recepcion->ordencompra_id }}">
                <input type="hidden" name="tipo" value="DEVOLUCION">
                <div class="card-body">
                    <div class="alert alert-info">
                        Devolución contra recepción <strong>{{ $recepcion->numerorecepcion }}</strong>.
                        Las cantidades vienen precargadas con lo recepcionado (devolución total habitual).
                        Ajuste solo las líneas que correspondan; no pueden superar lo recepcionado.
                    </div>
                    @include('stock.recepcion_proveedor.form', ['modoEdicion' => true, 'recepcion' => $recepcion, 'modoDevolucion' => true])
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-undo"></i> Registrar devolución
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
