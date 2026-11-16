@extends("theme.$theme.layout")

@section('titulo')
    Snapshot #{{ $snap->id }} · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
@php
    $keys = [];
    if (! empty($filas[0]) && is_array($filas[0])) {
        $keys = array_keys($filas[0]);
    } elseif (is_array($columnas) && $columnas !== [] && ! isset($columnas[0])) {
        $keys = array_keys($columnas);
    } else {
        $keys = array_keys($filas[0] ?? []);
    }
    $labels = is_array($columnas) ? $columnas : [];
@endphp
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Snapshot · {{ $snap->entidad }}</div>
            <h1>{{ $snap->titulo }}</h1>
            <p>
                {{ $snap->filas_count }} filas ·
                creado {{ optional($snap->created_at)->format('d/m/Y H:i') }} ·
                vence {{ optional($snap->expires_at)->format('d/m/Y H:i') }}
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('snapshots_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Snapshots
            </a>
            <a href="{{ route('explorar_consulta_inteligente', ['entidad' => $snap->entidad, 'consultar' => 1]) }}"
               class="btn btn-light btn-sm">
                Reabrir explorador
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    <div class="card card-info shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        @if (is_array($labels) && array_is_list($labels) && count($labels) === count($keys))
                            @foreach ($labels as $lab)
                                <th>{{ $lab }}</th>
                            @endforeach
                        @else
                            @foreach ($keys as $k)
                                <th>{{ is_string($labels[$k] ?? null) ? $labels[$k] : $k }}</th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr>
                            @foreach ($keys as $k)
                                <td>{{ is_array($fila) ? ($fila[$k] ?? '') : '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted text-center py-3">Sin filas en el snapshot.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
