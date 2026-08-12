@extends("theme.$theme.layout")
@section('titulo')
    Configuración propuesta de pagos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Configuración — Propuesta de pagos</h3>
                <div class="card-tools">
                    <a href="{{ route('propuesta_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a propuestas
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('actualizar_configuracion_propuesta_pago') }}" class="form-horizontal">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-4">
                            <select name="empresa_id" id="empresa_id" class="form-control" onchange="window.location='{{ route('configuracion_propuesta_pago') }}?empresa_id='+this.value">
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int)$empresa_id === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @include('compras.configuracion_propuesta_pago.partials.modo_selector')
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
