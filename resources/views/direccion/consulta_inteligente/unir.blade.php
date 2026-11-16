@extends("theme.$theme.layout")

@section('titulo')
    Join unificado · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Join unificado</h1>
            <p>
                Una grilla con columnas de la entidad origen y de la relacionada
                (solo pares publicados en el catálogo de joins).
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('cruzar_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-columns"></i> Cruce
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    <form method="get" action="{{ route('unir_consulta_inteligente') }}" class="card card-info shadow-sm mb-3">
        <div class="card-body">
            <input type="hidden" name="consultar" value="1">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-8 mb-0">
                    <label class="small text-muted" for="ci-unir-par">Par de entidades</label>
                    <select name="par" id="ci-unir-par" class="form-control" required
                            onchange="(function(sel){var o=sel.options[sel.selectedIndex];document.getElementById('ci-unir-desde').value=o.getAttribute('data-desde')||'';document.getElementById('ci-unir-hacia').value=o.getAttribute('data-hacia')||'';})(this)">
                        <option value="">Elegí un join…</option>
                        @foreach ($pares as $par)
                            @php
                                $sel = ($desde === $par['desde'] && $hacia === $par['hacia']);
                            @endphp
                            <option value="{{ $par['desde'] }}|{{ $par['hacia'] }}"
                                    data-desde="{{ $par['desde'] }}"
                                    data-hacia="{{ $par['hacia'] }}"
                                    @selected($sel)>
                                {{ $par['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="desde" id="ci-unir-desde" value="{{ $desde }}">
                    <input type="hidden" name="hacia" id="ci-unir-hacia" value="{{ $hacia }}">
                </div>
                <div class="form-group col-lg-4 mb-0">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-link"></i> Unir
                    </button>
                </div>
            </div>
            <div class="form-row mt-2">
                <div class="form-group col-md-6 mb-0">
                    <label class="small text-muted" for="ci-unir-valor">Filtro texto origen (opcional)</label>
                    <input type="text" name="filtro_valor" id="ci-unir-valor" class="form-control"
                           value="{{ request('filtro_valor', '') }}"
                           placeholder="Se aplica a la entidad origen">
                </div>
            </div>
        </div>
    </form>

    @if ($consultado)
        @if (! ($resultado['ok'] ?? false))
            <div class="alert alert-warning">{{ $resultado['mensaje'] ?? 'No se pudo unir.' }}</div>
        @else
            <div class="card card-outline card-info shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
                    <strong>
                        {{ $resultado['relacion']['titulo'] ?? 'Join' }}
                        <span class="text-muted font-weight-normal">
                            · {{ $resultado['desde']->nombre() }} → {{ $resultado['hacia']->nombre() }}
                            · {{ number_format($resultado['total']) }} filas origen
                        </span>
                    </strong>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_unir_consulta_inteligente',
                        'queryparams' => $filtrosQuery,
                        'variant' => 'compact',
                    ])
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @foreach ($resultado['columnas'] as $col)
                                    <th>{{ $col['label'] }}</th>
                                @endforeach
                                <th style="width:6rem;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultado['filas'] as $fila)
                                <tr>
                                    @foreach ($resultado['columnas'] as $col)
                                        <td>{!! $fila['celdas'][$col['key']] ?? '' !!}</td>
                                    @endforeach
                                    <td class="text-nowrap">
                                        @if (! empty($fila['url_origen']))
                                            <a href="{{ $fila['url_origen'] }}" class="btn btn-accion-tabla" target="_blank" rel="noopener" title="Origen">
                                                <i class="fa fa-external-link-alt"></i>
                                            </a>
                                        @endif
                                        @if (! empty($fila['url_destino']))
                                            <a href="{{ $fila['url_destino'] }}" class="btn btn-accion-tabla" target="_blank" rel="noopener" title="Destino">
                                                <i class="fa fa-arrow-right"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($resultado['columnas']) + 1 }}" class="text-muted text-center py-3">
                                        Sin filas para el filtro actual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (! empty($resultado['paginator']) && method_exists($resultado['paginator'], 'links'))
                    <div class="card-footer">
                        {{ $resultado['paginator']->appends($filtrosQuery)->links() }}
                    </div>
                @endif
            </div>
        @endif
    @elseif ($pares === [])
        <div class="alert alert-light border">No hay joins publicados para tus entidades autorizadas.</div>
    @endif
</div>
@endsection
