@extends("theme.$theme.layout")
@section('titulo')
    Foto del jugador — Premio UIF
@endsection

@section('styles')
    @include('uif.cliente_premio_uif.partials.foto_estilos')
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0 flex-grow-1">
                    Foto del jugador — Premio #{{ $data->id }}
                </h3>
                <div class="card-tools ml-auto">
                    @if (! empty($referer))
                        <a href="{{ $referer }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-arrow-left"></i> Volver
                        </a>
                    @else
                        <a href="{{ route('consulta_cliente_premio_uif') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body premio-foto-viewer-body">
                <p class="text-muted mb-3">
                    <strong>{{ $data->clientes_uif->nombre ?? '' }}</strong>
                    @if (! empty($data->clientes_uif->numerodocumento ?? null))
                        · Doc. {{ $data->clientes_uif->numerodocumento }}
                    @endif
                </p>
                @if (! empty($data->foto))
                    @php $fotoUrl = asset('storage/imagenes/fotos_uif/'.$data->foto); @endphp
                    <figure class="premio-foto-viewer-figure">
                        <img
                            src="{{ $fotoUrl }}"
                            alt="Foto del jugador — {{ $data->clientes_uif->nombre ?? 'Premio '.$data->id }}"
                            class="premio-foto-viewer-img"
                        >
                    </figure>
                    <div class="mt-3">
                        <a href="{{ $fotoUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-external-link-alt"></i> Abrir imagen original
                        </a>
                    </div>
                @else
                    <div class="text-muted py-5">
                        <i class="fa fa-image fa-3x mb-3 d-block opacity-50"></i>
                        <p class="mb-0">Este premio no tiene foto cargada.</p>
                    </div>
                @endif
            </div>
            <div class="card-footer">
                @if (! empty($referer))
                    <a href="{{ $referer }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-arrow-left"></i> Volver
                    </a>
                @else
                    <a href="{{ route('consulta_cliente_premio_uif') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                @endif
                @if (can('editar-cliente-premio-uif', false))
                    <a href="{{ route('edita_cliente_premio_uif', ['id' => $data->id]) }}" class="btn btn-outline-primary btn-sm ml-1">
                        <i class="fa fa-edit"></i> Editar premio
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
