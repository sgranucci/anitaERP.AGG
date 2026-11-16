@extends("theme.$theme.layout")

@section('titulo')
    Tablero · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('scripts')
{{-- Chart.js 4 solo en tablero CI (AdminLTE sigue en v2 en el resto del ERP) --}}
<script src="{{ asset('assets/js/vendor/chart.umd.min.js') }}?v={{ filemtime(public_path('assets/js/vendor/chart.umd.min.js')) }}"></script>
@php
    $seriesJson = $tablero['series'] ?? [];
@endphp
<script>
window.CI_TABLERO_SERIES = @json($seriesJson);
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || !window.CI_TABLERO_SERIES) {
        return;
    }
    var paleta = ['#85C1E9', '#2471A3', '#5DADE2', '#1A5276', '#AED6F1', '#117A65', '#E67E22', '#C0392B'];
    window.CI_TABLERO_SERIES.forEach(function (serie, n) {
        var canvas = document.getElementById('ci-tablero-chart-' + n);
        if (!canvas) {
            return;
        }
        var tipo = serie.tipo === 'linea' ? 'line' : (serie.tipo === 'torta' ? 'pie' : 'bar');
        var colores = (serie.labels || []).map(function (_, i) { return paleta[i % paleta.length]; });
        var urls = serie.urls || [];
        var opciones = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: tipo === 'pie' }
            },
            onHover: function (evt, elements) {
                evt.native.target.style.cursor = (elements && elements.length && urls.length) ? 'pointer' : 'default';
            },
            onClick: function (evt, elements) {
                if (!elements || !elements.length || !urls.length) {
                    return;
                }
                var idx = elements[0].index;
                if (urls[idx]) {
                    window.location.href = urls[idx];
                }
            }
        };
        if (tipo !== 'pie') {
            opciones.scales = {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            };
        }
        new Chart(canvas.getContext('2d'), {
            type: tipo,
            data: {
                labels: serie.labels || [],
                datasets: [{
                    label: serie.titulo || '',
                    data: serie.valores || [],
                    backgroundColor: tipo === 'pie' ? colores : (serie.color || '#2471A3'),
                    borderColor: serie.color || '#2471A3',
                    fill: tipo === 'line' ? false : true
                }]
            },
            options: opciones
        });
    });
});
</script>
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Tablero</h1>
            <p>KPIs, gráficos y atajos propios, más los que compartieron colegas (solo lectura).</p>
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

    @if (! empty($alertas))
        <div class="ci-atajos mb-3">
            @foreach ($alertas as $alerta)
                <a href="{{ $alerta['url'] }}" class="ci-atajo" title="{{ $alerta['detalle'] }}">
                    <span class="ci-atajo-dot" style="background:{{ $alerta['color'] }}"></span>
                    {{ $alerta['titulo'] }}
                </a>
            @endforeach
        </div>
    @endif

    @if (($tablero['kpis'] ?? []) !== [])
        <div class="ci-grid mb-3">
            @foreach ($tablero['kpis'] as $kpi)
                <div class="ci-card" style="border-left-color: {{ $kpi['color'] }}; min-height:auto;">
                    <div class="ci-card-mod">KPI</div>
                    <a href="{{ $kpi['url'] }}" style="text-decoration:none;color:inherit;">
                        <h2 style="font-size:1rem;">{{ $kpi['titulo'] }}</h2>
                        <div style="font-size:1.8rem;font-weight:700;color:{{ $kpi['color'] }};">{{ $kpi['valor'] }}</div>
                        @if (! empty($kpi['delta']))
                            @php
                                $dir = $kpi['delta_dir'] ?? 'flat';
                                $deltaColor = $dir === 'up' ? '#117A65' : ($dir === 'down' ? '#C0392B' : '#566573');
                            @endphp
                            <div class="small mt-1" style="color:{{ $deltaColor }};font-weight:600;">{{ $kpi['delta'] }}</div>
                        @endif
                        @if (! empty($kpi['narrativa']))
                            <div class="ci-kpi-narrativa mt-2">{{ $kpi['narrativa'] }}</div>
                        @endif
                    </a>
                    @if (! empty($kpi['compartido']) && empty($kpi['propio']))
                        <span class="badge badge-info mt-2">Compartido</span>
                    @endif
                    @if (! empty($kpi['widget_id']))
                        <form method="post" action="{{ route('unpin_tablero_consulta_inteligente', ['widgetId' => $kpi['widget_id']]) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Quitar</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-light border mb-3">
            Sin KPIs visibles con tus permisos. Fijá una consulta como tipo <strong>KPI</strong> desde el explorador.
        </div>
    @endif

    @if (($tablero['series'] ?? []) !== [])
        <div class="row mb-3">
            @foreach ($tablero['series'] as $i => $serie)
                <div class="col-lg-4 mb-3">
                    <div class="card card-outline card-info h-100 shadow-sm">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong class="small">
                                {{ $serie['titulo'] }}
                                @if (! empty($serie['compartido']) && empty($serie['propio']))
                                    <span class="badge badge-info">Compartido</span>
                                @endif
                            </strong>
                            @if (! empty($serie['widget_id']))
                                <form method="post" action="{{ route('unpin_tablero_consulta_inteligente', ['widgetId' => $serie['widget_id']]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-xs btn-sm py-0">Quitar</button>
                                </form>
                            @endif
                        </div>
                        <div class="card-body">
                            <div style="position:relative;height:220px;">
                                <canvas id="ci-tablero-chart-{{ $i }}"></canvas>
                            </div>
                            <div class="small text-muted mt-1">Clic en una barra/punto/porción para abrir el explorador filtrado.</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-light border mb-3">
            Sin gráficos. Fijá una consulta como <strong>barras / línea / torta</strong> desde el explorador (respetan los filtros de la consulta).
        </div>
    @endif

    <div class="card card-info shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Atajos fijados</strong>
            <span class="small text-muted">Máx. {{ \App\Support\ConsultaInteligente\ConsultaInteligenteTableroSupport::MAX_WIDGETS }} propios</span>
        </div>
        <div class="card-body">
            @if (($tablero['widgets'] ?? []) === [])
                <p class="ci-hint mb-0">
                    Todavía no fijaste consultas. Abrí el explorador y usá <strong>Fijar en tablero</strong>.
                </p>
            @else
                <div class="ci-grid">
                    @foreach ($tablero['widgets'] as $w)
                        <article class="ci-card" style="border-left-color: {{ $w['color'] }}; min-height:auto;">
                            <div class="ci-card-mod">
                                {{ $w['modulo'] ?? '' }}
                                @if (! empty($w['compartido']) && empty($w['propio']))
                                    · compartido
                                @elseif (! empty($w['compartido']))
                                    · publicado
                                @endif
                            </div>
                            <div class="ci-card-head">
                                <div class="ci-card-ico" style="background: {{ $w['color'] }}">
                                    <i class="fas {{ $w['icono'] ?? 'fa-bookmark' }}"></i>
                                </div>
                                <h2 style="font-size:1rem;">{{ $w['titulo'] }}</h2>
                            </div>
                            <div class="ci-card-actions">
                                <a href="{{ $w['url'] }}" class="btn btn-info btn-sm">Abrir</a>
                                @if (! empty($w['propio']))
                                    <form method="post" action="{{ route('unpin_tablero_consulta_inteligente', ['widgetId' => $w['id']]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Quitar</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
