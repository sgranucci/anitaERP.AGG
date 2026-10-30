@php
    $vendidos = $pares['vendidos'];
    $producidos = $pares['producidos'];
@endphp
<div class="td-panel td-panel-pares">
    <h2>Pares vendidos y producidos</h2>
    <div class="td-cuerpo">
        <div class="td-pares">
            @include('direccion.tablero.partials.caja_pares', [
                'caja' => $vendidos,
                'clase' => 'td-par-venta',
                'icono' => 'fa-shopping-bag',
                'titulo' => 'Pares vendidos',
            ])
            @include('direccion.tablero.partials.caja_pares', [
                'caja' => $producidos,
                'clase' => 'td-par-prod',
                'icono' => 'fa-industry',
                'titulo' => 'Pares producidos',
            ])
        </div>

        <p class="td-pares-leyenda">Últimos 12 meses, hasta el cierre del período elegido.</p>
        <div class="td-chart td-chart-pares"><canvas id="td-chart-pares"></canvas></div>

        <div class="td-rank-botones">
            <button type="button" class="td-rank-btn" data-toggle="collapse" data-target="#td-rank-lineas" aria-expanded="false" aria-controls="td-rank-lineas">
                <i class="fas fa-chevron-right"></i>
                <i class="fas fa-chevron-down"></i>
                Ranking por línea
            </button>
        </div>

        <div id="td-rank-lineas" class="collapse td-rank-panel">
            <div class="td-rank-split">
                <div class="td-rank-col">
                    <h3>Vendidos</h3>
                    @include('direccion.tablero.partials.ranking_linea', [
                        'canvas' => 'td-chart-linea-venta',
                        'filas' => $pares['lineas_venta'],
                        'vacio' => 'No hay pares vendidos en este período.',
                    ])
                </div>
                <div class="td-rank-col">
                    <h3>Producidos</h3>
                    @include('direccion.tablero.partials.ranking_linea', [
                        'canvas' => 'td-chart-linea-prod',
                        'filas' => $pares['lineas_produccion'],
                        'vacio' => 'No hay pares que hayan salido de producción en este período.',
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
