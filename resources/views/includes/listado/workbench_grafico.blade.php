<style>
    #tabla-paginada tr.lw-tono-danger > td { background: #FDEDEC !important; }
    #tabla-paginada tr.lw-tono-warning > td { background: #FEF9E7 !important; }
    #tabla-paginada tr.lw-tono-success > td { background: #E8F8F5 !important; }
</style>
@php
    $seriesPantalla = array_values(array_filter($graficoSeries ?? [], static fn ($serie) => ($serie['labels'] ?? []) !== []));
    if ($seriesPantalla === [] && ($graficoSerie['labels'] ?? []) !== []) {
        $seriesPantalla = [$graficoSerie];
    }
@endphp
@if ($seriesPantalla !== [])
    <div class="px-3 pt-2">
        @if (trim((string) ($filtros['grafico_click'] ?? '')) !== '')
            @php
                $qsSinClick = $filtrosQuery ?? [];
                unset($qsSinClick['grafico_click'], $qsSinClick['grafico_click_dimension']);
            @endphp
            <div class="small mb-1">
                Filtrado por el gráfico: {{ $filtros['grafico_click'] }}.
                <a href="{{ route($rutaListadoVisual, $qsSinClick) }}">Quitar este filtro</a>
            </div>
        @endif
        <div class="d-flex flex-wrap" style="gap:.75rem;">
            @foreach ($seriesPantalla as $iSerie => $seriePantalla)
                <div class="border rounded p-2 mb-2" style="flex:1 1 280px; min-width:280px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="small font-weight-bold mb-1">{{ $seriePantalla['titulo'] ?? 'Gráfico' }}</div>
                        @if ($iSerie === 0)
                            <form method="post" action="{{ route('quitar_grafico_listado', array_merge($filtrosQuery ?? [], ['recurso' => $recursoVisual])) }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ count($seriesPantalla) > 1 ? 'Quitar gráficos' : 'Quitar gráfico' }}</button>
                            </form>
                        @endif
                    </div>
                    <div style="position:relative;height:220px;">
                        <canvas id="lw-grafico-listado-{{ $iSerie }}" data-dimension="{{ $seriePantalla['dimension'] ?? '' }}"></canvas>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }
            var paleta = ['#85C1E9', '#2471A3', '#5DADE2', '#1A5276', '#AED6F1', '#2E86AB', '#7FB3D5', '#1B4F72', '#D4E6F1', '#5499C7', '#1ABC9C', '#E67E22'];
            @json($seriesPantalla).forEach(function (serie, n) {
                var canvas = document.getElementById('lw-grafico-listado-' + n);
                if (!canvas) {
                    return;
                }
                var tipo = serie.tipo || 'barras';
                var chartTipo = tipo === 'linea' ? 'line' : (tipo === 'torta' ? 'pie' : 'bar');
                var series = serie.series || [];
                var datasets = series.map(function (item, i) {
                    return {
                        label: item.nombre || '',
                        data: item.valores || [],
                        backgroundColor: chartTipo === 'pie' ? paleta : paleta[i % paleta.length],
                        borderColor: '#17202A',
                        borderWidth: 1,
                        fill: false
                    };
                });
                canvas.style.cursor = 'pointer';
                var chart = new Chart(canvas.getContext('2d'), {
                    type: chartTipo,
                    data: { labels: serie.labels || [], datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: chartTipo === 'pie' || datasets.length > 1 },
                        onClick: function (evt, items) {
                            if (!items || !items.length) {
                                return;
                            }
                            var idx = items[0]._index;
                            if (idx === undefined) {
                                idx = items[0].index;
                            }
                            var label = chart.data.labels[idx];
                            if (!label) {
                                return;
                            }
                            var valor = String(label);
                            if (chartTipo === 'pie' && valor.indexOf(' · ') !== -1) {
                                valor = valor.split(' · ')[0];
                            }
                            var params = new URLSearchParams(window.location.search);
                            params.set('grafico_click', valor);
                            params.set('grafico_click_dimension', canvas.getAttribute('data-dimension') || '');
                            window.location = window.location.pathname + '?' + params.toString();
                        }
                    }
                });
            });
        })();
    </script>
@endif
