@extends("theme.$theme.layout")
@section('titulo')
    Aprobar asiento {{ $data->numeroasiento }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Asiento {{ $data->numeroasiento }} — pendiente</h3>
                <a href="{{ route('imprimir_pdf_asiento', ['id' => $data->id]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf"></i> PDF
                </a>
            </div>
            <div class="card-body">
                @include('contable.aprobacion_asiento.partials.detalle_asiento', ['data' => $data])

                @if (can('aprobar-asiento-pendiente', false))
                    <form action="{{ route('aprobar_asiento_pendiente', ['id' => $data->id]) }}" method="POST" class="mt-3" onsubmit="return confirm('¿Confirmar aprobación y sincronización con contabilidad?');">
                        @csrf
                        <div class="form-group">
                            <label>Observaciones (opcional)</label>
                            <textarea name="observaciones" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Aprobar</button>
                    </form>
                @endif

                @if (can('rechazar-asiento-pendiente', false))
                    <form action="{{ route('rechazar_asiento_pendiente', ['id' => $data->id]) }}" method="POST" class="mt-3" onsubmit="return confirm('¿Rechazar este asiento? No se sincronizará con contabilidad.');">
                        @csrf
                        <div class="form-group">
                            <label>Motivo del rechazo</label>
                            <textarea name="motivo_rechazo" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Rechazar</button>
                    </form>
                @endif
            </div>
            <div class="card-footer">
                <a href="{{ route('aprobacion_asientos') }}" class="btn btn-default">Volver al listado</a>
            </div>
        </div>
    </div>
</div>
@endsection
