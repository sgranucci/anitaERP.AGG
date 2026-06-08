@extends("theme.$theme.layout")
@section('titulo')
    Cierre jornada Waitry
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/waitry_cierre_jornada/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/waitry_cierre_jornada/index.js')) ?: time() }}" type="text/javascript"></script>
@if ($puede_proceso_cierre ?? false)
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script>
    window.WAITRY_CIERRE_JORNADA_PROCESO = {
        csrf: @json(csrf_token()),
        puedeProceso: true,
        urlAnalizar: @json(route('waitry_cierre_jornada_api_proceso_analizar')),
        urlRecalcular: @json(route('waitry_cierre_jornada_api_proceso_recalcular')),
        urlPreviewFactura: @json(route('waitry_cierre_jornada_api_proceso_preview_factura')),
        urlPreviewLotesFactura: @json(route('waitry_cierre_jornada_api_proceso_preview_lotes_factura')),
        urlEmitirFactura: @json(route('waitry_cierre_jornada_api_proceso_emitir_factura')),
        urlGrabarAsientos: @json(route('waitry_cierre_jornada_api_proceso_grabar_asientos')),
        urlRevertirProceso: @json(route('waitry_cierre_jornada_api_proceso_revertir')),
        urlOpcionesEmitir: @json(route('waitry_cierre_jornada_api_proceso_opciones_emitir')),
        urlMovimientosBase: @json($url_movimientos_proceso_base ?? ''),
        urlCuadroDetalleBase: @json(url('caja/waitry-cierre-jornada/api/proceso/cuadro-detalle/__FILA__/__MEDIO__')),
        urlFacturaVerBase: @json(can('ver-factura-gastronomia', false) ? url('ventas/gastronomia/facturas-dia') : null),
        urlAsientoVerBase: @json(can('editar-asiento', false) ? url('contable/asiento') : null),
        urlMovimientoStockVerBase: @json(can('editar-movimientos-de-stock', false) ? url('stock/movimientostock') : null),
        urlConfigBase: @json(url('caja/waitry-cierre-jornada/api/proceso/config/__EMPRESA_ID__')),
        urlConfigGuardarBase: @json(url('caja/waitry-cierre-jornada/api/proceso/config/__EMPRESA_ID__')),
        configInicial: @json($config_contable ?? []),
        sincronizarAnitaAlFacturar: @json(config('gastronomia.sincronizar_anita_al_facturar', true)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/caja/waitry_cierre_jornada_proceso.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/waitry_cierre_jornada_proceso.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre de jornada Waitry</h3>
            </div>
            <div class="card-body">
                @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                    'id' => 'waitry-ayuda-conciliacion-intro',
                    'label' => 'Ayuda — conciliación Waitry',
                    'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.conciliacion_intro',
                ])

                <form method="get" action="{{ route('waitry_cierre_jornada') }}" class="form-inline mb-4">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'select_class' => 'mr-3',
                        'required' => true,
                        'permite_todas' => false,
                    ])
                    <label class="mr-2" for="fecha_jornada">Fecha jornada</label>
                    <input type="date" name="fecha_jornada" id="fecha_jornada" class="form-control mr-3"
                           value="{{ $fecha_jornada }}" required>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </form>

                @if ($error)
                    <div class="alert alert-danger">{{ $error }}</div>
                @endif

                @if ($consultado && $payload && ($payload['ok'] ?? false))
                    @php
                        $resumen = $payload['resumen'] ?? [];
                        $metaConciliacion = $payload['meta_conciliacion'] ?? [];
                    @endphp

                    @if (! empty($metaConciliacion))
                        <div class="alert alert-secondary py-2 mb-3">
                            <p class="mb-1 small">
                                <strong>Consulta Waitry:</strong>
                                {{ $metaConciliacion['waitry_rango_etiqueta'] ?? '' }}
                            </p>
                            <p class="mb-1 small">
                                <strong>Cruce Anita:</strong>
                                {{ $metaConciliacion['anita_criterio'] ?? '' }}
                            </p>
                            @if (! empty($metaConciliacion['ventana_jornada_etiqueta']))
                                <p class="mb-0 small">
                                    <strong>Jornada gastronomía (apertura — cierre):</strong>
                                    {{ $metaConciliacion['ventana_jornada_etiqueta'] }}
                                </p>
                            @endif
                        </div>
                    @endif

                    @if (! empty($payload['jornada']))
                        <div class="alert alert-info py-2">
                            Jornada Anita #{{ $payload['jornada']['id'] }}
                            — estado: <strong>{{ $payload['jornada']['estado'] }}</strong>
                            @if (! empty($payload['jornada']['apertura_en']))
                                · apertura {{ $payload['jornada']['apertura_en'] }}
                            @endif
                            @if (! empty($payload['jornada']['cierre_en']))
                                · cierre {{ $payload['jornada']['cierre_en'] }}
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning py-2">
                            No hay registro de jornada gastronomía en Anita para esta empresa y fecha.
                        </div>
                    @endif

                    @php
                        $circuitosConciliacion = $resumen['circuitos'] ?? [];
                        $circuitoTotem = $circuitosConciliacion[\App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA] ?? [];
                        $circuitoImportadaImpaga = $circuitosConciliacion[\App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_IMPORTADA_IMPAGA_WAITRY] ?? [];
                        $circuitoTotemImpaga = $circuitosConciliacion[\App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA] ?? [];
                        $circuitoAnita = $circuitosConciliacion[\App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_ANITA_FACTURA_WAITRY] ?? [];
                    @endphp
                    <style>
                        .waitry-circuito-box { cursor: pointer; user-select: none; transition: box-shadow 0.15s ease; }
                        .waitry-circuito-box.filtro-activo { box-shadow: 0 0 0 3px #343a40; }
                        .waitry-circuito-box.waitry-circuito-vacio { cursor: default; opacity: 0.65; }
                        .badge-filtro-conciliacion { cursor: pointer; user-select: none; }
                        .badge-filtro-conciliacion.filtro-activo { box-shadow: 0 0 0 2px #343a40; }
                        .badge-filtro-conciliacion.badge-filtro-vacio { cursor: default; opacity: 0.55; }
                    </style>
                    <div class="row mb-3" id="waitry-conciliacion-circuitos" role="group" aria-label="Circuitos Waitry-Anita">
                        <div class="col-xl-3 col-lg-6 mb-2 mb-xl-0">
                            @php
                                $countTotem = (int) ($circuitoTotem['cantidad'] ?? 0);
                                $claseTotem = 'info-box bg-primary waitry-circuito-box js-filtro-circuito-conciliacion'
                                    .($countTotem <= 0 ? ' waitry-circuito-vacio' : '');
                            @endphp
                            <div class="{{ $claseTotem }}"
                                 data-filtro-circuito="{{ \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA }}"
                                 data-filtro-etiqueta="{{ $circuitoTotem['etiqueta'] ?? 'Importada Anita — cobrada en Waitry' }}"
                                 data-filtro-count="{{ $countTotem }}"
                                 title="{{ $countTotem > 0 ? 'Clic para filtrar importadas y cobradas en Waitry' : 'Sin órdenes de este circuito' }}"
                                 tabindex="{{ $countTotem > 0 ? '0' : '-1' }}"
                                 role="button">
                                <span class="info-box-icon"><i class="fa fa-desktop"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Importadas — cobradas en Waitry</span>
                                    <span class="info-box-number">{{ $countTotem }} orden(es)</span>
                                    <span class="info-box-text small">
                                        Waitry: ${{ number_format((float) ($circuitoTotem['total_waitry'] ?? 0), 2, ',', '.') }}
                                        · Anita: ${{ number_format((float) ($circuitoTotem['total_anita'] ?? 0), 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 mb-2 mb-xl-0">
                            @php
                                $countImportadaImpagaSolo = (int) ($circuitoImportadaImpaga['cantidad'] ?? 0);
                                $countImportadaImpaga = $countImportadaImpagaSolo + (int) ($circuitoTotemImpaga['cantidad'] ?? 0);
                                $totalImportadaImpagaWaitry = (float) ($circuitoImportadaImpaga['total_waitry'] ?? 0)
                                    + (float) ($circuitoTotemImpaga['total_waitry'] ?? 0);
                                $totalImportadaImpagaAnita = (float) ($circuitoImportadaImpaga['total_anita'] ?? 0)
                                    + (float) ($circuitoTotemImpaga['total_anita'] ?? 0);
                                $claseImportadaImpaga = 'info-box bg-warning waitry-circuito-box js-filtro-circuito-conciliacion'
                                    .($countImportadaImpaga <= 0 ? ' waitry-circuito-vacio' : '');
                                $filtroImportadaImpagaCircuitos = implode(',', [
                                    \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_IMPORTADA_IMPAGA_WAITRY,
                                    \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA,
                                ]);
                            @endphp
                            <div class="{{ $claseImportadaImpaga }}"
                                 data-filtro-circuito="{{ $filtroImportadaImpagaCircuitos }}"
                                 data-filtro-etiqueta="Importadas — impagas en Waitry"
                                 data-filtro-count="{{ $countImportadaImpaga }}"
                                 title="{{ $countImportadaImpaga > 0 ? 'Clic para filtrar importadas impagas en Waitry (criterio getOrdersPOS)' : 'Sin órdenes de este circuito' }}"
                                 tabindex="{{ $countImportadaImpaga > 0 ? '0' : '-1' }}"
                                 role="button">
                                <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Importadas — impagas en Waitry</span>
                                    <span class="info-box-number">{{ $countImportadaImpaga }} orden(es)</span>
                                    <span class="info-box-text small">
                                        Waitry: ${{ number_format($totalImportadaImpagaWaitry, 2, ',', '.') }}
                                        · Anita: ${{ number_format($totalImportadaImpagaAnita, 2, ',', '.') }}
                                        · cobradas Anita: {{ (int) ($circuitoTotemImpaga['cantidad'] ?? 0) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 mb-2 mb-xl-0">
                            @php
                                $countTotemImpaga = (int) ($circuitoTotemImpaga['cantidad'] ?? 0);
                                $claseTotemImpaga = 'info-box bg-secondary waitry-circuito-box js-filtro-circuito-conciliacion'
                                    .($countTotemImpaga <= 0 ? ' waitry-circuito-vacio' : '');
                            @endphp
                            <div class="{{ $claseTotemImpaga }}"
                                 data-filtro-circuito="{{ \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA }}"
                                 data-filtro-etiqueta="{{ $circuitoTotemImpaga['etiqueta'] ?? 'Importada Anita — impaga Waitry, cobrada Anita' }}"
                                 data-filtro-count="{{ $countTotemImpaga }}"
                                 title="{{ $countTotemImpaga > 0 ? 'Clic para filtrar importadas impagas en Waitry y cobradas en Anita' : 'Sin órdenes de este circuito' }}"
                                 tabindex="{{ $countTotemImpaga > 0 ? '0' : '-1' }}"
                                 role="button">
                                <span class="info-box-icon"><i class="fa fa-exchange"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Impagas Waitry — cobradas en Anita</span>
                                    <span class="info-box-number">{{ $countTotemImpaga }} orden(es)</span>
                                    <span class="info-box-text small">
                                        Waitry: ${{ number_format((float) ($circuitoTotemImpaga['total_waitry'] ?? 0), 2, ',', '.') }}
                                        · Anita: ${{ number_format((float) ($circuitoTotemImpaga['total_anita'] ?? 0), 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6">
                            @php
                                $countAnita = (int) ($circuitoAnita['cantidad'] ?? 0);
                                $claseAnita = 'info-box bg-info waitry-circuito-box js-filtro-circuito-conciliacion'
                                    .($countAnita <= 0 ? ' waitry-circuito-vacio' : '');
                            @endphp
                            <div class="{{ $claseAnita }}"
                                 data-filtro-circuito="{{ \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_ANITA_FACTURA_WAITRY }}"
                                 data-filtro-etiqueta="{{ $circuitoAnita['etiqueta'] ?? 'Anita → Waitry (factura Anita)' }}"
                                 data-filtro-count="{{ $countAnita }}"
                                 title="{{ $countAnita > 0 ? 'Clic para filtrar ventas emitidas en Anita y enviadas a Waitry' : 'Sin órdenes de este circuito' }}"
                                 tabindex="{{ $countAnita > 0 ? '0' : '-1' }}"
                                 role="button">
                                <span class="info-box-icon"><i class="fa fa-paper-plane"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Anita → Waitry (factura generada en Anita)</span>
                                    <span class="info-box-number">{{ $countAnita }} orden(es)</span>
                                    <span class="info-box-text small">
                                        Waitry: ${{ number_format((float) ($circuitoAnita['total_waitry'] ?? 0), 2, ',', '.') }}
                                        · Anita: ${{ number_format((float) ($circuitoAnita['total_anita'] ?? 0), 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-shopping-cart"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Órdenes Waitry</span>
                                    <span class="info-box-number">{{ $resumen['ordenes_waitry'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-file-text"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Facturas Anita (jornada)</span>
                                    <span class="info-box-number">{{ $resumen['facturas_anita_jornada'] ?? ($resumen['facturas_anita_waitry'] ?? 0) }}</span>
                                    <span class="info-box-text small text-muted">
                                        con Waitry: {{ $resumen['facturas_anita_waitry'] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Anita facturado</span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['total_anita_facturado'] ?? 0), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-cutlery"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text"
                                          title="Suma de totalAmount (importe POS) de todas las órdenes Waitry activas del tramo operativo del día">
                                        Total tramo Waitry
                                    </span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['total_waitry'] ?? 0), 2, ',', '.') }}</span>
                                    <span class="info-box-text small text-muted">
                                        {{ $resumen['ordenes_waitry'] ?? 0 }} orden(es) en el tramo
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-money"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text"
                                          title="Suma de la columna Total Anita en filas con order Waitry (importe factura ERP, no el del POS)">
                                        Total Anita→Waitry
                                    </span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['total_anita_enviadas_waitry'] ?? 0), 2, ',', '.') }}</span>
                                    <span class="info-box-text small text-muted">
                                        @php
                                            $soloAnitaMonto = (float) ($resumen['total_anita_solo_anita_monto'] ?? 0);
                                            $difPareadas = (float) ($resumen['diferencia_importes_pareados'] ?? 0);
                                        @endphp
                                        @if ($soloAnitaMonto > 0.02)
                                            ${{ number_format($soloAnitaMonto, 2, ',', '.') }} sin orden en tramo
                                        @elseif (abs($difPareadas) > 0.02)
                                            vs tramo: ${{ number_format($difPareadas, 2, ',', '.') }} (Anita − Waitry)
                                        @else
                                            alineado al tramo Waitry
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box {{ ($resumen['tiene_diferencias'] ?? false) ? 'bg-warning' : 'bg-success' }}">
                                <span class="info-box-icon"><i class="fa fa-balance-scale"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text"
                                          title="Tramo Waitry (importes POS) menos facturación Anita completa de la jornada (todas las ventas, con y sin Waitry)">
                                        Dif. global
                                    </span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['diferencia_global'] ?? 0), 2, ',', '.') }}</span>
                                    <span class="info-box-text small text-muted">tramo Waitry − Anita jornada</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-1" id="waitry-conciliacion-filtros-circuitos" role="group" aria-label="Filtrar por circuito">
                        @foreach ([
                            [
                                'circuito' => \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_COBRADA,
                                'class' => 'badge-primary',
                                'icon' => 'fa-desktop',
                                'texto' => 'Importadas cobradas W.',
                                'count' => $countTotem,
                            ],
                            [
                                'circuito' => $filtroImportadaImpagaCircuitos,
                                'class' => 'badge-warning',
                                'icon' => 'fa-clock-o',
                                'texto' => 'Importadas impagas W.',
                                'count' => $countImportadaImpaga,
                            ],
                            [
                                'circuito' => \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA,
                                'class' => 'badge-secondary',
                                'icon' => 'fa-exchange',
                                'texto' => 'Impaga W. / cobrada Anita',
                                'count' => $countTotemImpaga,
                            ],
                            [
                                'circuito' => \App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport::CIRCUITO_ANITA_FACTURA_WAITRY,
                                'class' => 'badge-info',
                                'icon' => 'fa-paper-plane',
                                'texto' => 'Anita → Waitry facturadas',
                                'count' => $countAnita,
                            ],
                        ] as $filtroCircuito)
                            @php
                                $countCircuito = (int) $filtroCircuito['count'];
                                $clasesCircuito = 'badge badge-filtro-conciliacion js-filtro-circuito-conciliacion mr-1 '.$filtroCircuito['class']
                                    .($countCircuito <= 0 ? ' badge-filtro-vacio' : '');
                            @endphp
                            <span class="{{ $clasesCircuito }}"
                                  data-filtro-circuito="{{ $filtroCircuito['circuito'] }}"
                                  data-filtro-etiqueta="{{ $filtroCircuito['texto'] }}"
                                  data-filtro-count="{{ $countCircuito }}"
                                  title="{{ $countCircuito > 0 ? 'Clic para filtrar la tabla' : 'Sin filas de este circuito' }}"
                                  tabindex="{{ $countCircuito > 0 ? '0' : '-1' }}"
                                  role="button">
                                <i class="fa {{ $filtroCircuito['icon'] }} mr-1" aria-hidden="true"></i>
                                {{ $filtroCircuito['texto'] }}: {{ $countCircuito }}
                            </span>
                        @endforeach
                    </div>
                    <div class="mb-1" id="waitry-conciliacion-filtros" role="group" aria-label="Filtrar tabla por estado">
                        @php
                            $badgesFiltro = [
                                ['estados' => ['conciliada'], 'class' => 'badge-success', 'texto' => 'Conciliadas', 'count' => (int) ($resumen['conciliadas'] ?? 0)],
                                ['estados' => ['sin_factura_anita'], 'class' => 'badge-danger', 'texto' => 'Sin factura', 'count' => (int) ($resumen['sin_factura_anita'] ?? 0)],
                                ['estados' => ['importada_pendiente'], 'class' => 'badge-warning', 'texto' => 'Importadas pendientes', 'count' => (int) ($resumen['importadas_pendientes'] ?? 0)],
                                ['estados' => ['monto_distinto'], 'class' => 'badge-info', 'texto' => 'Monto distinto', 'count' => (int) ($resumen['monto_distinto'] ?? 0)],
                                ['estados' => ['medio_distinto'], 'class' => 'badge-primary', 'texto' => 'Medio distinto', 'count' => (int) ($resumen['medio_distinto'] ?? 0)],
                                ['estados' => ['solo_anita'], 'class' => 'badge-secondary', 'texto' => 'Solo Anita (día W.)', 'count' => (int) ($resumen['solo_anita'] ?? 0)],
                                ['estados' => ['anita_sin_waitry'], 'class' => 'badge-danger', 'texto' => 'Anita sin Waitry ID', 'count' => (int) ($resumen['anita_sin_waitry'] ?? 0)],
                                ['estados' => ['jornada_distinta', 'jornada_distinta_monto'], 'class' => 'badge-dark', 'texto' => 'Otra jornada Anita', 'count' => (int) ($resumen['jornada_distinta'] ?? 0)],
                            ];
                        @endphp
                        @foreach ($badgesFiltro as $badgeFiltro)
                            @php
                                $countFiltro = $badgeFiltro['count'];
                                $clasesFiltro = 'badge badge-filtro-conciliacion js-filtro-estado-conciliacion mr-1 '.$badgeFiltro['class']
                                    .($countFiltro <= 0 ? ' badge-filtro-vacio' : '');
                            @endphp
                            <span class="{{ $clasesFiltro }}"
                                  data-filtro-estados="{{ implode(',', $badgeFiltro['estados']) }}"
                                  data-filtro-etiqueta="{{ $badgeFiltro['texto'] }}"
                                  data-filtro-count="{{ $countFiltro }}"
                                  title="{{ $countFiltro > 0 ? 'Clic para filtrar la tabla' : 'Sin filas de este estado' }}"
                                  tabindex="{{ $countFiltro > 0 ? '0' : '-1' }}"
                                  role="button">
                                {{ $badgeFiltro['texto'] }}: {{ $countFiltro }}
                            </span>
                        @endforeach
                        @if (($resumen['waitry_canceladas_cantidad'] ?? 0) > 0)
                            <span class="badge badge-light border text-muted" title="Excluidas del cuadro y totales operativos">
                                Waitry canceladas: {{ $resumen['waitry_canceladas_cantidad'] }}
                                (${{ number_format((float) ($resumen['waitry_canceladas_total'] ?? 0), 2, ',', '.') }})
                            </span>
                        @endif
                        @if (($resumen['waitry_anuladas_descuento_cantidad'] ?? 0) > 0)
                            <span class="badge badge-light border text-muted" title="Descuento 100 % en kiosco, neto $0 — excluidas de impagos">
                                Waitry anuladas (desc. 100 %): {{ $resumen['waitry_anuladas_descuento_cantidad'] }}
                                (${{ number_format((float) ($resumen['waitry_anuladas_descuento_total'] ?? 0), 2, ',', '.') }})
                            </span>
                        @endif
                    </div>
                    <p class="small text-muted mb-2 d-none" id="waitry-conciliacion-filtro-aviso">
                        Filtro activo: <strong id="waitry-conciliacion-filtro-etiqueta"></strong>
                        (<span id="waitry-conciliacion-filtro-visible"></span> fila(s) visibles).
                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="waitry-conciliacion-limpiar-filtro">
                            Ver todas
                        </button>
                    </p>

                    @if (! empty($resumen['por_medio_waitry']))
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Medio Waitry</th>
                                        <th>Cuenta caja esperada</th>
                                        <th class="text-right">Cantidad</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($resumen['por_medio_waitry'] as $medio)
                                        <tr>
                                            <td>{{ $medio['etiqueta'] ?? '—' }}</td>
                                            <td>{{ $medio['cuentacaja_label'] ?? '—' }}</td>
                                            <td class="text-right">{{ (int) ($medio['cantidad'] ?? 0) }}</td>
                                            <td class="text-right">${{ number_format((float) ($medio['total'] ?? 0), 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @php
                        $exportQuery = http_build_query([
                            'empresa_id' => $empresa_id,
                            'fecha_jornada' => $fecha_jornada,
                        ]);
                    @endphp
                    <div class="mb-2 d-flex flex-wrap align-items-start justify-content-between">
                        <div class="waitry-conciliacion-export-wrap">
                            @php
                                $puedePdfWaitry = \App\Support\Caja\RendicionGastronomiaPdfPermiso::puedeVerPdfWaitry();
                            @endphp
                            @if ($puedePdfWaitry)
                                <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'PDF']) }}?{{ $exportQuery }}" class="btn btn-app bg-danger">
                                    <i class="fas fa-file-pdf"></i> Pdf
                                </a>
                            @else
                                <a href="javascript:void(0)" class="btn btn-app bg-danger disabled" aria-disabled="true" title="Sin permiso para exportar PDF (ver-pdf-waitry-gastronomia-caja)">
                                    <i class="fas fa-file-pdf"></i> Pdf
                                </a>
                                <small class="text-muted d-block">
                                    Sin permiso para exportar PDF de cierre Waitry. Use Excel/CSV o solicite el permiso <code>ver-pdf-waitry-gastronomia-caja</code>.
                                </small>
                            @endif
                            <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'EXCEL']) }}?{{ $exportQuery }}" class="btn btn-app bg-success">
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                            <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'CSV']) }}?{{ $exportQuery }}" class="btn btn-app bg-warning">
                                <i class="fas fa-file-csv"></i> Csv
                            </a>
                        </div>
                        <div id="waitry-conciliacion-totales-filtro"
                             class="alert alert-light border py-2 px-3 mb-0 ml-md-3 text-right d-none"
                             role="status"
                             aria-live="polite">
                            <div class="small text-muted mb-1">
                                Totales filtrados — <strong id="waitry-conciliacion-totales-etiqueta"></strong>
                            </div>
                            <div class="small mb-0">
                                <span class="mr-2"><strong id="waitry-conciliacion-totales-filas">0</strong> fila(s)</span>
                                <span class="mr-2">Waitry: <strong id="waitry-conciliacion-totales-waitry">$ 0,00</strong></span>
                                <span class="mr-2">Anita: <strong id="waitry-conciliacion-totales-anita">$ 0,00</strong></span>
                                <span>Dif.: <strong id="waitry-conciliacion-totales-diferencia">$ 0,00</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover table-sm" id="tabla-waitry-conciliacion">
                            <thead>
                                <tr>
                                    <th>Orden Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora Waitry</th>
                                    <th class="text-right"
                                        title="Importe de la comanda en Waitry (factura Anita enviada al POS)">
                                        Importe Waitry
                                    </th>
                                    <th>Pagada W.</th>
                                    <th>Venta Anita</th>
                                    <th class="text-right">Total Anita</th>
                                    <th>Medio Waitry</th>
                                    <th>Cta. caja esp.</th>
                                    <th>Cta. caja Anita</th>
                                    <th class="text-right">Diferencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($payload['filas'] ?? [] as $fila)
                                    @php
                                        $estadoClass = match ($fila['estado']) {
                                            'conciliada' => 'success',
                                            'monto_distinto' => 'info',
                                            'medio_distinto' => 'primary',
                                            'importada_pendiente' => 'warning',
                                            'sin_factura_anita' => 'danger',
                                            'jornada_distinta', 'jornada_distinta_monto' => 'dark',
                                            'solo_anita' => 'secondary',
                                            'anita_sin_waitry' => 'danger',
                                            default => 'light',
                                        };
                                    @endphp
                                    <tr data-estado="{{ $fila['estado'] ?? '' }}"
                                        data-circuito="{{ $fila['circuito_conciliacion'] ?? '' }}"
                                        data-waitry-total="{{ $fila['waitry_total'] !== null ? (float) $fila['waitry_total'] : '' }}"
                                        data-anita-total="{{ $fila['anita_total'] !== null ? (float) $fila['anita_total'] : '' }}"
                                        data-diferencia="{{ $fila['diferencia'] !== null ? (float) $fila['diferencia'] : '' }}">
                                        <td>{{ $fila['waitry_order_id'] ?: '—' }}</td>
                                        <td>
                                            {{ $fila['referencia_waitry'] ?: '—' }}
                                            @if (! empty($fila['waitry_order_id']) && ($fila['referencia_waitry'] ?? '') !== '#'.$fila['waitry_order_id'])
                                                <br><small class="text-muted">#{{ $fila['waitry_order_id'] }}</small>
                                            @endif
                                            @if (empty($fila['waitry_en_listado_dia']))
                                                <br><small class="text-info">Fuera del listado Waitry del día</small>
                                            @endif
                                        </td>
                                        <td data-order="{{ $fila['placed_at'] ?? '' }}">{{ $fila['fecha_hora_waitry'] ?: ($fila['hora_waitry'] ?: '—') }}</td>
                                        <td class="text-right">
                                            @if ($fila['waitry_total'] !== null)
                                                ${{ number_format((float) $fila['waitry_total'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if ($fila['waitry_paid'] === null)
                                                —
                                            @elseif ($fila['waitry_paid'])
                                                <span class="badge badge-success">Sí</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $fila['anita_codigo'] ?? ($fila['anita_venta_id'] ? '#'.$fila['anita_venta_id'] : '—') }}
                                            @if (! empty($fila['anita_fechajornada_fmt']) && ($fila['estado'] ?? '') !== 'conciliada' && str_starts_with((string) ($fila['estado'] ?? ''), 'jornada_distinta'))
                                                <br><small class="text-muted">Jorn. Anita {{ $fila['anita_fechajornada_fmt'] }}</small>
                                            @endif
                                            @if (in_array($fila['estado'] ?? '', ['anita_sin_waitry', 'solo_anita'], true) || empty($fila['waitry_en_listado_dia']))
                                                <br><small class="{{ ($fila['estado'] ?? '') === 'anita_sin_waitry' ? 'text-danger' : 'text-muted' }}">
                                                    @if (($fila['estado'] ?? '') === 'anita_sin_waitry')
                                                        KDS:
                                                    @else
                                                        Comanda:
                                                    @endif
                                                    {{ $fila['waitry_comanda_estado'] ?? 'sin envío' }}
                                                    @if (! empty($fila['waitry_comanda_error']))
                                                        — {{ \Illuminate\Support\Str::limit($fila['waitry_comanda_error'], 80) }}
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($fila['anita_total'] !== null)
                                                ${{ number_format((float) $fila['anita_total'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (! empty($fila['waitry_medio_label']) && $fila['waitry_medio_label'] !== '—')
                                                <span class="badge badge-light">{{ $fila['waitry_medio_label'] }}</span>
                                            @elseif ($fila['anita_totem'])
                                                <span class="badge badge-primary">TOTEM</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $fila['cuentacaja_esperada_label'] ?? '—' }}</td>
                                        <td>{{ $fila['anita_cuentacaja_label'] ?? '—' }}</td>
                                        <td class="text-right">
                                            @if ($fila['diferencia'] !== null)
                                                ${{ number_format((float) $fila['diferencia'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><span class="badge badge-{{ $estadoClass }}">{{ $fila['estado_label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center text-muted">Sin órdenes Waitry ni ventas Anita para esta jornada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($consultado && ! $error)
                    <div class="alert alert-info">No hay datos para mostrar.</div>
                @endif

                @include('caja.waitry_cierre_jornada.partials.proceso_cierre')
            </div>
        </div>
    </div>
</div>
@endsection
