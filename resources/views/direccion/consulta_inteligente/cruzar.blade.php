@extends("theme.$theme.layout")

@section('titulo')
    Cruce multi-entidad · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Cruce multi-entidad</h1>
            <p>
                Misma búsqueda y período sobre hasta {{ $maxEntidades }} entidades a la vez.
                Cada panel muestra las primeras filas; abrí el explorador para el detalle completo.
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('tablero_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-th-large"></i> Tablero
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    <form method="get" action="{{ route('cruzar_consulta_inteligente') }}" class="card card-info shadow-sm mb-3">
        <div class="card-body">
            <input type="hidden" name="consultar" value="1">
            <div class="form-row">
                <div class="form-group col-lg-5">
                    <label class="small text-muted">Entidades (máx. {{ $maxEntidades }})</label>
                    <div class="ci-multi-entidades">
                        @foreach ($entidadesDisponibles as $ent)
                            @php $checked = in_array($ent->clave(), $filtros['entidades'] ?? [], true); @endphp
                            <label class="ci-multi-check" style="border-left-color: {{ $ent->color() }}">
                                <input type="checkbox" name="entidades[]" value="{{ $ent->clave() }}"
                                       @checked($checked) class="ci-multi-ent-cb">
                                <i class="fas {{ $ent->icono() }}"></i>
                                {{ $ent->nombre() }}
                                <span class="text-muted small">{{ $ent->modulo() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-1">Marcá hasta {{ $maxEntidades }}. El resto se ignora.</div>
                </div>
                <div class="form-group col-lg-4">
                    <label class="small text-muted" for="ci-multi-valor">Texto / número</label>
                    <input type="text" name="filtro_valor" id="ci-multi-valor" class="form-control"
                           value="{{ $filtros['filtro_valor'] ?? '' }}"
                           placeholder="Se aplica a cada entidad">
                </div>
                <div class="form-group col-lg-3">
                    <label class="small text-muted" for="ci-multi-periodo">Período</label>
                    <select name="periodo" id="ci-multi-periodo" class="form-control">
                        @foreach ($periodos as $key => $label)
                            <option value="{{ $key }}" @selected(($filtros['periodo'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="small text-muted mt-1">Clientes/proveedores no tienen fecha: se listan sin ese filtro.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-info btn-sm">
                <i class="fa fa-search"></i> Consultar
            </button>
        </div>
    </form>

    @if (! $consultado)
        <div class="ci-hint text-center p-4">
            Elegí entidades, opcionalmente texto y período, y consultá.
        </div>
    @elseif (($paneles ?? []) === [])
        <div class="alert alert-warning">No hay entidades válidas seleccionadas o sin permiso.</div>
    @else
        <div class="ci-multi-grid">
            @foreach ($paneles as $panel)
                <section class="ci-multi-panel card shadow-sm" style="border-top: 3px solid {{ $panel['color'] }}">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <div>
                            <i class="fas {{ $panel['icono'] }}" style="color:{{ $panel['color'] }}"></i>
                            <strong>{{ $panel['nombre'] }}</strong>
                            <span class="badge badge-light ml-1">{{ $panel['total'] }}</span>
                            @if (! empty($panel['sin_periodo']))
                                <span class="badge badge-secondary ml-1" title="Sin campo fecha">sin período</span>
                            @endif
                        </div>
                        <a href="{{ $panel['url_explorar'] }}" class="btn btn-sm btn-outline-info">Explorar</a>
                    </div>
                    <div class="card-body p-0">
                        @if (($panel['filas'] ?? []) === [])
                            <div class="p-3 text-muted small">Sin registros.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0 ci-tabla">
                                    <thead>
                                        <tr>
                                            @foreach ($panel['columnas'] as $col)
                                                <th>{{ $panel['etiquetas'][$col] ?? $col }}</th>
                                            @endforeach
                                            <th style="width:2.5rem;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($panel['filas'] as $fila)
                                            <tr>
                                                @foreach ($panel['columnas'] as $col)
                                                    <td>{!! $fila['celdas'][$col] ?? '' !!}</td>
                                                @endforeach
                                                <td class="text-center">
                                                    @if (! empty($fila['url']))
                                                        <a href="{{ $fila['url'] }}" class="text-primary" target="_blank" rel="noopener">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var max = {{ (int) $maxEntidades }};
    var boxes = document.querySelectorAll('.ci-multi-ent-cb');
    boxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var n = document.querySelectorAll('.ci-multi-ent-cb:checked').length;
            if (n > max) {
                cb.checked = false;
            }
        });
    });
});
</script>
@endsection
