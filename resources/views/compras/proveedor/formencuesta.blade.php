@extends('layouts.requisicion-aprobacion-publica')

@section('titulo_pagina', $encuesta->nombre)

@section('portal_nav_subtitulo', 'Encuesta de proveedor')

@push('styles')
<style>
    .encuesta-puntajes .btn {
        min-width: 2.75rem;
    }
    @media (max-width: 575.98px) {
        .encuesta-puntajes .btn {
            flex: 1 1 calc(20% - .5rem);
            min-width: 2.25rem;
        }
    }
</style>
@endpush

@section('content')
<div class="card portal-card mb-3">
    <div class="card-header bg-info">
        <h1 class="card-title mb-0 h5 text-white">{{ $encuesta->nombre }}</h1>
    </div>
    <div class="card-body p-0">
        <dl class="row kv mb-0 px-3 py-3">
            <dt class="col-sm-4">Proveedor</dt>
            <dd class="col-sm-8 text-break">{{ ($proveedor->codigo ?? '').' — '.($proveedor->nombre ?? '—') }}</dd>
            <dt class="col-sm-4">Origen</dt>
            <dd class="col-sm-8 text-break">{{ $origen ?? '—' }}</dd>
        </dl>
    </div>
</div>

<form action="{{ route('guardar_proveedor_encuesta') }}" method="post">
    @csrf
    <input type="hidden" name="proveedor_id" value="{{ $proveedor->id }}">
    <input type="hidden" name="encuesta_id" value="{{ $encuesta->id }}">
    <input type="hidden" name="origen" value="{{ $origen }}">

    @php $numeroPregunta = 1; @endphp
    @foreach ($encuesta->encuesta_preguntas as $pregunta)
    <div class="card portal-card mb-3">
        <div class="card-header">
            <h2 class="card-title mb-0 h6">{{ $numeroPregunta }}. {{ $pregunta->nombre }}</h2>
            <small class="text-muted d-block mt-1">Puntaje de {{ $pregunta->desdepuntaje }} a {{ $pregunta->hastapuntaje }}</small>
        </div>
        <div class="card-body">
            <div class="btn-group-toggle d-flex flex-wrap encuesta-puntajes" data-toggle="buttons" role="group" aria-label="Puntaje pregunta {{ $numeroPregunta }}">
                @for ($puntaje = $pregunta->desdepuntaje; $puntaje <= $pregunta->hastapuntaje; $puntaje++)
                <label class="btn btn-outline-primary btn-sm mb-1 mr-1">
                    <input
                        type="radio"
                        name="puntaje{{ $numeroPregunta }}[]"
                        value="{{ $puntaje }}"
                        autocomplete="off"
                        @if ($puntaje == $pregunta->desdepuntaje)
                            required
                        @endif
                    >
                    {{ $puntaje }}
                </label>
                @endfor
            </div>
            <input type="hidden" name="encuesta_pregunta_ids[]" value="{{ $pregunta->id }}">
        </div>
    </div>
    @php $numeroPregunta++; @endphp
    @endforeach

    <div class="card portal-card card-success">
        <div class="card-header">
            <h2 class="card-title mb-0 h6">Comentarios</h2>
        </div>
        <div class="card-body">
            <div class="form-group mb-0">
                <label for="comentario" class="sr-only">Comentarios</label>
                <textarea
                    name="comentario"
                    id="comentario"
                    class="form-control requerido"
                    rows="5"
                    placeholder="Comentarios…"
                    required
                >{{ old('comentario') }}</textarea>
            </div>
            <button type="submit" class="btn btn-success btn-block btn-lg mt-3">Enviar encuesta</button>
        </div>
    </div>
</form>
@endsection
