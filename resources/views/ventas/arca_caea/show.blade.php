@extends("theme.$theme.layout")
@section('titulo')
    Detalle CAEA #{{ $registro->id }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $registro->etiqueta_quincena }}</h3>
                <div class="card-tools">
                    <a href="{{ route('arca_caea') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Empresa</dt>
                    <dd class="col-sm-9">{{ $registro->empresa->nombre ?? '—' }} (CUIT {{ $registro->cuit }})</dd>

                    <dt class="col-sm-3">CAEA</dt>
                    <dd class="col-sm-9"><code>{{ $registro->nro_caea ?? '—' }}</code></dd>

                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9">{{ $registro->estado }}</dd>

                    <dt class="col-sm-3">Vigencia</dt>
                    <dd class="col-sm-9">
                        @if ($registro->fecha_vigencia_desde && $registro->fecha_vigencia_hasta)
                            {{ $registro->fecha_vigencia_desde->format('d/m/Y') }}
                            al {{ $registro->fecha_vigencia_hasta->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-sm-3">Tope informe comprobantes</dt>
                    <dd class="col-sm-9">{{ $registro->fecha_tope_informe?->format('d/m/Y') ?? '—' }}</dd>

                    <dt class="col-sm-3">Fecha proceso ARCA</dt>
                    <dd class="col-sm-9">{{ $registro->fecha_proceso?->format('d/m/Y H:i') ?? '—' }}</dd>

                    <dt class="col-sm-3">Origen</dt>
                    <dd class="col-sm-9">{{ $registro->origen }}</dd>

                    <dt class="col-sm-3">Solicitado por</dt>
                    <dd class="col-sm-9">{{ $registro->solicitadoPor->nombre ?? '—' }}</dd>

                    @if ($registro->mensaje_error)
                        <dt class="col-sm-3">Error</dt>
                        <dd class="col-sm-9 text-danger">{{ $registro->mensaje_error }}</dd>
                    @endif

                    @if ($registro->observaciones)
                        <dt class="col-sm-3">Observaciones ARCA</dt>
                        <dd class="col-sm-9">{{ $registro->observaciones['texto'] ?? json_encode($registro->observaciones) }}</dd>
                    @endif

                    <dt class="col-sm-3">Última actualización</dt>
                    <dd class="col-sm-9">{{ $registro->updated_at?->format('d/m/Y H:i:s') }}</dd>
                </dl>

                @if (can('solicitar-arca-caea', false) && ! $registro->estaAutorizado())
                    <form method="post" action="{{ route('arca_caea_reintentar', $registro->id) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-warning">Reintentar solicitud</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
