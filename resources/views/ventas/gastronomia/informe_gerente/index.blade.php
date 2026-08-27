@extends("theme.$theme.layout")

@section('titulo')
    Informe gerente gastronomía
@endsection

@section('scripts')
<style>
    .informe-gerente-chart-wrap {
        position: relative;
        height: 280px;
    }
    .informe-gerente-tabla-scroll {
        max-height: 360px;
        overflow: auto;
    }
    .informe-gerente-tabla-top10 {
        max-height: none;
        overflow: visible;
    }
    .informe-gerente-chart-wrap--bar {
        height: 360px;
    }
</style>
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}" type="text/javascript"></script>
<script>
    window.INFORME_GERENTE_GASTRONOMIA = {
        informe: @json($informe),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/informe_gerente.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/informe_gerente.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Informe gerente — gastronomía</h3>
            </div>
            <div class="card-body">
                @include('ventas.gastronomia.informe_gerente.partials.filtros_cabecera')

                @include('includes.proceso_overlay_aviso', [
                    'overlayId' => 'informe-gerente-overlay',
                    'tituloId' => 'informe-gerente-overlay-titulo',
                    'subtituloId' => 'informe-gerente-overlay-subtitulo',
                    'titulo' => 'Generando informe…',
                    'subtitulo' => 'Puede demorar según el período. Pulse Esc para cerrar este aviso.',
                ])

                @if ($empresa_id <= 0)
                    <div class="alert alert-info">Seleccione empresa y rango de fechas de jornada para generar el informe.</div>
                @elseif ($informe === null)
                    <div class="alert alert-warning">No se pudo generar el informe.</div>
                @else
                    <div class="mb-3">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_gastronomia_informe_gerente',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                        @php
                            $pptParams = array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== '');
                            $pptSuffix = count($pptParams) ? '?'.http_build_query($pptParams) : '';
                        @endphp
                        <a href="{{ route('listar_gastronomia_informe_gerente', ['formato' => 'PPTX']).$pptSuffix }}"
                           class="btn btn-app bg-orange">
                            <i class="fas fa-file-powerpoint"></i> PowerPoint
                        </a>
                    </div>

                    <div class="alert alert-secondary py-2">
                        <strong>Período {{ $informe['periodo_label'] ?? $informe['fecha_jornada_label'] }}</strong>
                        — Total ventas (neto): <strong>${{ number_format($informe['total_ventas_periodo'] ?? $informe['total_ventas_jornada'], 2, ',', '.') }}</strong>
                        @if ($jornada_registro)
                            — Estado jornada: {{ $jornada_registro->estado }}
                        @endif
                        @if (!empty($informe['waitry_sin_facturar']['total']))
                            <br>
                            <span class="small">
                                Incluye Waitry pagado sin facturar en Anita:
                                <strong>${{ number_format($informe['waitry_sin_facturar']['total'], 2, ',', '.') }}</strong>
                                ({{ (int) ($informe['waitry_sin_facturar']['cantidad_ordenes'] ?? 0) }} comanda(s);
                                jornada abierta).
                            </span>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header py-2"><strong>Top 10 artículos vendidos — cantidad</strong></div>
                                <div class="card-body p-0 informe-gerente-tabla-top10">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_top10', ['filas' => $informe['top10_cantidad'], 'orden' => 'cantidad'])
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header py-2"><strong>Top 10 artículos vendidos — valor</strong></div>
                                <div class="card-body p-0 informe-gerente-tabla-top10">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_top10', ['filas' => $informe['top10_valor'], 'orden' => 'importe'])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header py-2">
                                    <strong>Top 10 artículos vendidos — cantidad (período)</strong>
                                </div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap informe-gerente-chart-wrap--bar">
                                        <canvas id="chart-articulos-dia"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-info h-100">
                                <div class="card-header py-2">
                                    <strong>Top 10 artículos vendidos — cantidad (mes)</strong>
                                    <span class="float-right small text-muted">{{ $informe['mes_jornada_label'] ?? '' }}</span>
                                </div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap informe-gerente-chart-wrap--bar">
                                        <canvas id="chart-articulos-mes"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <div class="card card-outline card-success h-100">
                                <div class="card-header py-2"><strong>Ventas por turno</strong></div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap">
                                        <canvas id="chart-ventas-turno"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="card card-outline card-success h-100">
                                <div class="card-header py-2"><strong>Ventas por punto de venta (período)</strong></div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap">
                                        <canvas id="chart-ventas-pv"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="card card-outline card-warning h-100">
                                <div class="card-header py-2"><strong>Facturas por código de descuento</strong></div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap">
                                        <canvas id="chart-descuentos"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-success h-100">
                                <div class="card-header py-2">
                                    <strong>Facturación por medio de pago</strong>
                                    <span class="float-right small text-muted">Participación del período</span>
                                </div>
                                <div class="card-body">
                                    <div class="informe-gerente-chart-wrap">
                                        <canvas id="chart-medio-pago"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-success h-100">
                                <div class="card-header py-2"><strong>Detalle por medio de pago</strong></div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_medio_pago', [
                                        'filas' => $informe['ventas_por_medio_pago'] ?? [],
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-primary">
                                <div class="card-header py-2"><strong>Ventas por punto de venta</strong></div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_puntoventa', ['filas' => $informe['ventas_por_puntoventa']])
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-warning">
                                <div class="card-header py-2"><strong>Detalle por descuento</strong></div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_descuentos', ['datos' => $informe['facturas_por_descuento']])
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $recResumen = $informe['recepciones_resumen'] ?? [];
                        $recError = $informe['recepciones']['error'] ?? null;
                        $recDisponible = ! empty($informe['recepciones']['disponible']);
                        $recCentroCosto = $informe['recepciones']['centro_costo_codigo'] ?? null;
                        $recEtiquetaCc = $recCentroCosto ? ' · CC '.$recCentroCosto : '';
                        $recFuente = $informe['recepciones']['fuente'] ?? 'erp';
                        $recFuenteLabel = $recFuente === 'erp'
                            ? 'ERP'
                            : ($recFuente === 'hibrido' ? 'ERP + Anita (fechas antiguas)' : 'Anita');
                    @endphp

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-secondary h-100">
                                <div class="card-header py-2">
                                    <strong>Recepciones del período — importe por proveedor</strong>
                                    @if ($recCentroCosto)
                                        <span class="badge badge-light border ml-1">CC {{ $recCentroCosto }}</span>
                                    @endif
                                    @if ($recDisponible && empty($recError) && ($recResumen['dia_importe'] ?? 0) > 0)
                                        <span class="float-right small text-muted">
                                            ${{ number_format($recResumen['dia_importe'], 2, ',', '.') }}
                                            @if ($recResumen['dia_pct_mes'] !== null)
                                                · {{ number_format($recResumen['dia_pct_mes'], 1, ',', '.') }}% del mes
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="card-body">
                                    @if (! empty($recError))
                                        <p class="text-warning small mb-0">{{ $recError }}</p>
                                    @else
                                        <div class="informe-gerente-chart-wrap">
                                            <canvas id="chart-recepciones-dia"></canvas>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-secondary h-100">
                                <div class="card-header py-2">
                                    <strong>Recepciones del mes — importe por proveedor</strong>
                                    @if ($recCentroCosto)
                                        <span class="badge badge-light border ml-1">CC {{ $recCentroCosto }}</span>
                                    @endif
                                    @if ($recDisponible && empty($recError) && ($recResumen['mes_importe'] ?? 0) > 0)
                                        <span class="float-right small text-muted">
                                            ${{ number_format($recResumen['mes_importe'], 2, ',', '.') }}
                                            · {{ $recResumen['proveedores_mes'] ?? 0 }} prov.
                                        </span>
                                    @endif
                                </div>
                                <div class="card-body">
                                    @if (! empty($recError))
                                        <p class="text-muted small mb-0">Sin datos (ver error en detalle).</p>
                                    @else
                                        <div class="informe-gerente-chart-wrap">
                                            <canvas id="chart-recepciones-mes"></canvas>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-secondary">
                                <div class="card-header py-2">
                                    <strong>Recepciones del período</strong>
                                    <span class="float-right small text-muted">{{ $recFuenteLabel }}{{ $recEtiquetaCc }}</span>
                                </div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_recepciones', [
                                        'bloque' => $informe['recepciones']['dia'],
                                        'error' => $informe['recepciones']['error'] ?? null,
                                        'recepciones_meta' => $informe['recepciones'],
                                    ])
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="card card-outline card-secondary">
                                <div class="card-header py-2">
                                    <strong>Recepciones acumuladas del mes</strong>
                                    <span class="float-right small text-muted">{{ $recFuenteLabel ?? 'ERP' }}{{ $recEtiquetaCc }}</span>
                                </div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_recepciones', [
                                        'bloque' => $informe['recepciones']['mes'],
                                        'error' => $informe['recepciones']['error'] ?? null,
                                        'solo_si_error' => true,
                                        'recepciones_meta' => $informe['recepciones'],
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $top20Costos = $informe['top20_articulos_costo'] ?? ['filas' => [], 'listas' => [], 'error' => null];
                        $top20Listas = $top20Costos['listas'] ?? [];
                    @endphp
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card card-outline card-info">
                                <div class="card-header py-2">
                                    <strong>Top 20 artículos más vendidos del período — precio y costo Anita</strong>
                                    @if (!empty($top20Listas['lista_anterior']) && !empty($top20Listas['lista_actual']))
                                        <span class="float-right small text-muted">
                                            stkpre · listas {{ $top20Listas['lista_anterior'] }} ({{ $top20Listas['mes_anterior_label'] ?? '' }})
                                            y {{ $top20Listas['lista_actual'] }} ({{ $top20Listas['mes_actual_label'] ?? '' }})
                                        </span>
                                    @endif
                                </div>
                                <div class="card-body p-0 informe-gerente-tabla-scroll">
                                    @include('ventas.gastronomia.informe_gerente.partials.tabla_top20_costos', [
                                        'bloque' => $top20Costos,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
