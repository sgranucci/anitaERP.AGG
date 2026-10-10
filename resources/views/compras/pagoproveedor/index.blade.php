@extends("theme.$theme.layout")
@section('titulo')
    Pago a proveedores
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<style>
    #tabla-paginada tr.pp-tono-danger > td { background:#FDEDEC !important; }
    #tabla-paginada tr.pp-tono-warning > td { background:#FEF9E7 !important; }
    #tabla-paginada tr.pp-tono-success > td { background:#E8F8F5 !important; }
</style>
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $workbenchJs = public_path('assets/pages/scripts/compras/pagoproveedor/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/workbench.js') }}?v={{ file_exists($workbenchJs) ? filemtime($workbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/enviar-proveedor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/pagoproveedor/enviar-proveedor.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js')) ?: time() }}" type="text/javascript"></script>
@include('compras.pagoproveedor.partials.documentos_relacionados_script')
@php
    $graficoSeriesJs = array_values(array_filter($graficoSeries ?? [], static fn ($serie) => ($serie['labels'] ?? []) !== []));
@endphp
@if ($graficoSeriesJs !== [])
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script>
    (function () {
        if (typeof Chart === 'undefined') {
            return;
        }
        var paleta = ['#85C1E9', '#2471A3', '#5DADE2', '#1A5276', '#AED6F1', '#2E86AB', '#7FB3D5', '#1B4F72', '#D4E6F1', '#5499C7', '#1ABC9C', '#E67E22'];
        @json($graficoSeriesJs).forEach(function (serie, n) {
            var canvas = document.getElementById('pp-grafico-listado-' + n);
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
@if (session('imprimir_pagoproveedor_url'))
<script>
    (function () {
        var url = @json(session('imprimir_pagoproveedor_url'));
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    })();
</script>
@endif
@endsection

@php
    use App\Support\Compras\PagoproveedorListadoFiltros;
    use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
    use App\Support\Listado\ListadoOrdenamientoSupport;
    use App\Support\Listado\ListadoQbeSupport;

    $columnasVisibles = $columnasVisibles ?? [];
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarQ = PagoproveedorListadoFiltros::paraQueryStringEmpresa($filtros ?? []);
    if (($filtros['mail'] ?? '') !== '') {
        $limpiarQ['mail'] = $filtros['mail'];
    }
    if (($filtros['periodo'] ?? '') !== '') {
        $limpiarQ['filtro_periodo'] = $filtros['periodo'];
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    } elseif (! empty($filtrosQuery['vista_estandar'])) {
        $limpiarQ['vista_estandar'] = 1;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('pagoproveedor', $limpiarQ);
    $camposOrdenablesThead = PagoproveedorListadoFiltros::camposOrdenables();
    $ordenActualThead = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $camposOrdenablesThead);
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('pagoproveedor', $qsQuitarOrden);
    $qbeActivo = ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $qbeAbierto = false;
    $tipoOppIeId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig();
    $crearIeParams = array_filter([
        'tipotransaccion_caja_id' => $tipoOppIeId > 0 ? $tipoOppIeId : null,
        'empresa_id' => ((int) ($filtros['empresa_id'] ?? 0)) > 0 ? (int) $filtros['empresa_id'] : null,
    ], static fn ($v) => $v !== null && $v !== '');
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Órdenes de pago</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-pagoproveedor', false))
                        <a href="{{ route('crear_pagoproveedor', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nueva OP
                        </a>
                    @endif
                    @if ($tipoOppIeId > 0 && can('crear-ingresos-egresos-caja', false))
                        <a href="{{ route('crear_ingresoegreso', $crearIeParams) }}"
                           class="btn btn-light btn-sm"
                           title="Abrir Ingresos/Egresos con tipo Orden de pago (OPP)">
                            <i class="fa fa-fw fa-exchange"></i> Pago vía IE
                        </a>
                    @endif
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('pagoproveedor') }}" id="form-filtros-pagoproveedor" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($empresaActual > 0)
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                @if (($filtros['mail'] ?? '') !== '')
                    <input type="hidden" name="mail" value="{{ $filtros['mail'] }}">
                @endif
                @if (($filtros['fecha_desde'] ?? '') !== '')
                    <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}">
                @endif
                @if (($filtros['fecha_hasta'] ?? '') !== '')
                    <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}">
                @endif
                @if (($filtros['periodo'] ?? '') !== '')
                    <input type="hidden" name="filtro_periodo" value="{{ $filtros['periodo'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('pagoproveedor') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm {{ ($qbeActivo ?? false) ? 'btn-info' : 'btn-outline-info' }} collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel" title="{{ ($qbeActivo ?? false) ? 'Hay una consulta aplicada' : 'Consulta avanzada' }}">
                            <i class="fa fa-filter"></i> Consulta avanzada
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico, color de fila y columnas calculadas">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary collapsed" data-toggle="collapse" data-target="#lw-mail-panel" aria-expanded="false" aria-controls="lw-mail-panel" title="Enviar este listado por correo">
                            <i class="fa fa-envelope"></i> Enviar por mail
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Texto o número"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (PagoproveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por fecha descendente y lo saca de la vista">
                                <i class="fa fa-sort"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('compras.pagoproveedor.partials.filtros_externos')
                @include('compras.pagoproveedor.partials.workbench_qbe')
                @include('compras.pagoproveedor.partials.workbench_analisis')
            </form>
            @php
                $mailListado = trim((string) (auth()->user()->email ?? ''));
            @endphp
            <div class="collapse" id="lw-mail-panel">
                <form method="post" action="{{ route('enviar_listado_pagoproveedor', $filtrosQuery ?? []) }}" class="px-3 pb-2 mb-0">
                    @csrf
                    <div class="border rounded p-2" style="background:#f8fbfd;">
                        <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                            <label class="small mb-0" for="email-listado-pagoproveedor">Enviar este listado</label>
                            <input type="email" name="email" id="email-listado-pagoproveedor" class="form-control form-control-sm" required
                                   style="max-width:22rem;" value="{{ $mailListado }}" placeholder="correo@empresa.com">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="fa fa-envelope"></i> Mandar Excel ahora
                            </button>
                            <select name="programar" class="form-control form-control-sm" style="max-width:11rem;">
                                <option value="">Solo ahora</option>
                                <option value="diaria">Todos los días</option>
                                <option value="semanal">Cada lunes</option>
                            </select>
                            <span class="small text-muted">Sale con el filtro de esta pantalla. Si hay más de 2.000 filas, el archivo corta ahí. Lo programado se manda a las 07:05.</span>
                        </div>
                    </div>
                    @if (($enviosProgramados ?? collect())->isNotEmpty())
                        <ul class="small mb-0 mt-2 pl-3">
                            @foreach ($enviosProgramados as $envio)
                                <li>
                                    {{ $envio->email }} · {{ $envio->frecuencia === 'semanal' ? 'cada lunes' : 'todos los días' }}
                                    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline" form="form-baja-envio-{{ $envio->id }}">Dar de baja</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </form>
                @foreach (($enviosProgramados ?? collect()) as $envio)
                    <form id="form-baja-envio-{{ $envio->id }}" method="post" action="{{ route('baja_envio_pagoproveedor', $envio->id) }}" class="d-none">
                        @csrf
                    </form>
                @endforeach
            </div>
            @php
                $graficoSeriesPantalla = array_values(array_filter($graficoSeries ?? [], static fn ($serie) => ($serie['labels'] ?? []) !== []));
            @endphp
            @if ($graficoSeriesPantalla !== [])
                <div class="px-3 pt-2">
                    @if (trim((string) ($filtros['grafico_click'] ?? '')) !== '')
                        @php
                            $qsSinClick = $filtrosQuery ?? [];
                            unset($qsSinClick['grafico_click'], $qsSinClick['grafico_click_dimension']);
                        @endphp
                        <div class="small mb-1">
                            Filtrado por el gráfico: {{ $filtros['grafico_click'] }}.
                            <a href="{{ route('pagoproveedor', $qsSinClick) }}">Quitar este filtro</a>
                        </div>
                    @endif
                    <div class="d-flex flex-wrap" style="gap:.75rem;">
                        @foreach ($graficoSeriesPantalla as $iSerie => $seriePantalla)
                            <div class="border rounded p-2 mb-2" style="flex:1 1 280px; min-width:280px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="small font-weight-bold mb-1">{{ $seriePantalla['titulo'] ?? 'Gráfico' }}</div>
                                    @if ($iSerie === 0)
                                        <form method="post" action="{{ route('quitar_grafico_pagoproveedor', $filtrosQuery ?? []) }}" class="mb-0">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Quitar gráficos</button>
                                        </form>
                                    @endif
                                </div>
                                <div style="position:relative;height:220px;">
                                    <canvas id="pp-grafico-listado-{{ $iSerie }}" data-dimension="{{ $seriePantalla['dimension'] ?? '' }}"></canvas>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_pagoproveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('compras.pagoproveedor.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            @foreach (($filtros['calculadas'] ?? []) as $calc)
                                <th data-orderable="false">{{ $calc['etiqueta'] ?? '' }}</th>
                            @endforeach
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coleccion as $fila)
                            @php
                                $esIeOpp = $fila instanceof \App\Support\Compras\PagoproveedorListadoFila && $fila->esIeOpp();
                                $tonoFila = \App\Support\Compras\PagoproveedorListadoAnalisisSupport::tonoFila($fila, $filtros['formato'] ?? []);
                            @endphp
                            <tr @class([
                                'pp-tono-danger' => $tonoFila === 'danger',
                                'pp-tono-warning' => $tonoFila === 'warning',
                                'pp-tono-success' => $tonoFila === 'success',
                            ])>
                                @foreach ($columnasVisibles as $keyColumna)
                                    @include('compras.pagoproveedor.partials.workbench_celda', [
                                        'key' => $keyColumna,
                                        'fila' => $fila,
                                    ])
                                @endforeach
                                @foreach (($filtros['calculadas'] ?? []) as $iCalc => $calc)
                                    <td>{{ $fila->calculadas['calc_'.$iCalc] ?? '' }}</td>
                                @endforeach
                                <td class="text-nowrap">
                                    @php
                                        $opSoloLectura = strtoupper(trim((string) ($fila->estado ?? ''))) === 'REVERTIDA';
                                    @endphp
                                    @if ($esIeOpp)
                                        @if (can('editar-ingresos-egresos-caja', false) || can('listar-ingresos-egresos-caja', false))
                                            <a href="{{ route('editar_ingresoegreso', ['id' => $fila->id, 'origen' => 'pagoproveedor']) }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="{{ $opSoloLectura ? 'Consultar OP (IE, solo lectura)' : 'Consultar OP (IE)' }}"
                                               target="_blank" rel="noopener">
                                                <i class="fa {{ $opSoloLectura ? 'fa-eye' : 'fa-edit' }}"></i>
                                            </a>
                                        @endif
                                        @if (can('listar-ingresos-egresos-caja', false))
                                            <a class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener"
                                               href="{{ route('imprimir_ingresoegreso', $fila->id) }}" title="Imprimir">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        @endif
                                        @if (
                                            can('revertir-ingresos-egresos-caja', false)
                                            && $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                                            && $fila->revertible
                                        )
                                            <form action="{{ route('revertir_ingresoegreso_id', ['id' => $fila->id]) }}"
                                                  class="d-inline form-revertir-ie" method="POST">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $fila->id }}">
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir (compensatorio + asiento + Anita)">
                                                    <i class="fa fa-undo text-warning"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        @if (can('editar-pagoproveedor', false))
                                            <a href="{{ route('editar_pagoproveedor', ['id' => $fila->id] + $retornoListadoQuery) }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="{{ $opSoloLectura ? 'Consultar (solo lectura)' : 'Editar' }}">
                                                <i class="fa {{ $opSoloLectura ? 'fa-eye' : 'fa-edit' }}"></i>
                                            </a>
                                        @endif
                                        <a class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" href="{{ route('imprimir_pagoproveedor', $fila->id) }}" title="Imprimir">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        @if (can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false))
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-op-documentos-relacionados text-primary"
                                                title="Documentos relacionados (factura, OC, COM, requisición)"
                                                data-id="{{ $fila->id }}"
                                                data-numero="{{ $fila->etiquetaComprobante() }}">
                                                <i class="fa fa-sitemap"></i>
                                            </button>
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-op-enviar-proveedor text-success"
                                                title="{{ $fila->mailEnviado ? 'Ver o reenviar el correo' : 'Enviar OP por email' }}"
                                                data-pagoproveedor-id="{{ $fila->id }}">
                                                <i class="fa fa-envelope"></i>
                                            </button>
                                        @endif
                                        @if (
                                            can('revertir-pagoproveedor', false)
                                            && $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                                            && $fila->revertible
                                        )
                                            <form action="{{ route('revertir_pagoproveedor', $fila->id) }}"
                                                  class="d-inline form-revertir-ie" method="POST"
                                                  data-confirm="¿Revertir esta OP? Se genera compensatorio con asiento y Anita invertidos. La OP original no se borra.">
                                                @csrf
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir (compensatorio + asiento + Anita)">
                                                    <i class="fa fa-undo text-warning"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columnasVisibles) + count($filtros['calculadas'] ?? []) + 1 }}" class="text-center text-muted">Sin órdenes de pago</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('compras.pagoproveedor.partials.workbench_modales')
@include('compras.pagoproveedor.partials.modal_enviar_proveedor')
@include('compras.pagoproveedor.partials.documentos_relacionados_modal')
@endsection
