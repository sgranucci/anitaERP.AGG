@extends("theme.$theme.layout")

@section('titulo')
    Alertas · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Configurar alertas</h1>
            <p>Umbrales y flags de las señales del catálogo. Sin hardcode: se guardan en parámetros del sistema.</p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('diccionario_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-book"></i> Diccionario NL
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    <form method="post" action="{{ route('guardar_configuracion_consulta_inteligente') }}" class="card card-primary shadow-sm" data-enter-campos="1">
        @csrf
        <div class="card-body">
            @foreach ($definiciones as $clave => $def)
                <div class="form-group row">
                    <label class="col-lg-4 col-form-label control-label text-right pr-2" for="ci-cfg-{{ $clave }}">
                        {{ $def['etiqueta'] }}
                    </label>
                    <div class="col-lg-6">
                        @if (($def['tipo'] ?? '') === 'boolean')
                            <div class="custom-control custom-switch mt-2">
                                <input type="checkbox" class="custom-control-input" id="ci-cfg-{{ $clave }}"
                                       name="{{ $clave }}" value="1" @checked(! empty($valores[$clave]))>
                                <label class="custom-control-label" for="ci-cfg-{{ $clave }}">Activa</label>
                            </div>
                        @else
                            <input type="number" class="form-control" id="ci-cfg-{{ $clave }}"
                                   name="{{ $clave }}" min="0" step="1"
                                   value="{{ (int) ($valores[$clave] ?? 0) }}">
                        @endif
                        <div class="small text-muted mt-1">{{ $def['ayuda'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-save"></i> Guardar
            </button>
        </div>
    </form>
</div>
@endsection
