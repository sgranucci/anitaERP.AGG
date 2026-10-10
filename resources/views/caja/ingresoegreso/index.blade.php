@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $ieWorkbenchJs = public_path('assets/pages/scripts/caja/ingresoegreso/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/workbench.js') }}?v={{ file_exists($ieWorkbenchJs) ? filemtime($ieWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/enviar-proveedor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/pagoproveedor/enviar-proveedor.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;
    use App\Support\Caja\IngresoEgresoListadoMontoSupport;

    $columnasVisibles = $columnasVisibles ?? [];
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarQ = IngresoEgresoListadoFiltros::paraQueryStringEmpresa($filtros ?? []);
    if (($filtros['tipos'] ?? []) !== []) {
        $limpiarQ['tipos'] = array_values($filtros['tipos']);
    }
    if (($filtros['periodo'] ?? '') !== '') {
        $limpiarQ['filtro_periodo'] = $filtros['periodo'];
    }
    if (($filtros['mail'] ?? '') !== '') {
        $limpiarQ['mail'] = $filtros['mail'];
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('ingresoegreso', $limpiarQ);
    $camposOrdenablesThead = IngresoEgresoListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesThead
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('ingresoegreso', $qsQuitarOrden);
    $totalesListado = $totalesListado ?? ['cantidad' => 0, 'ingresos' => 0, 'egresos' => 0];
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Ingresos y Egresos de Caja</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-ingresos-egresos-caja', false))
                        <a href="{{ route('crear_ingresoegreso', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
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
            <form method="get" action="{{ route('ingresoegreso') }}" id="form-filtros-ingresoegreso" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($empresaActual > 0)
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                @foreach (($filtros['tipos'] ?? []) as $tipoOculto)
                    <input type="hidden" name="tipos[]" value="{{ (int) $tipoOculto }}">
                @endforeach
                @if (($filtros['periodo'] ?? '') !== '')
                    <input type="hidden" name="filtro_periodo" value="{{ $filtros['periodo'] }}">
                    <input type="hidden" name="mail" value="{{ $filtros['mail'] ?? '' }}">
                @endif
                @if (($filtros['fecha_desde'] ?? '') !== '')
                    <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}">
                @endif
                @if (($filtros['fecha_hasta'] ?? '') !== '')
                    <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}">
                @endif
                @if (! empty($filtros['solicitudpago_id']))
                    <input type="hidden" name="solicitudpago_id" value="{{ (int) $filtros['solicitudpago_id'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('ingresoegreso') }}"
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
                        <button type="button" class="btn btn-sm {{ ($qbeAbierto ?? false) ? 'btn-info' : 'btn-outline-info' }} {{ ($qbeAbierto ?? false) ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ ($qbeAbierto ?? false) ? 'true' : 'false' }}" aria-controls="lw-qbe-panel" title="Consulta avanzada">
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
                        @if (IngresoEgresoListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por ID descendente y lo saca de la vista">
                                <i class="fa fa-sort"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('caja.ingresoegreso.partials.filtros_externos')
                @include('caja.ingresoegreso.partials.workbench_qbe')
                @php
                    $camposDelVisual = \App\Support\Caja\IngresoEgresoListadoFiltros::camposVisual();
                    $ejesVisual = $camposDelVisual;
                    $medidasVisual = [];
                    foreach (\App\Support\Listado\ListadoMedidaSupport::catalogo($camposDelVisual) as $keyMedida => $metaMedida) {
                        $medidasVisual[$keyMedida] = $metaMedida['label'];
                    }
                    $lienzoVisual = true;
                    $calculadasVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @php
                $mailListado = trim((string) (auth()->user()->email ?? ''));
            @endphp
            <div class="collapse" id="lw-mail-panel">
                <form method="post" action="{{ route('enviar_listado_ingresoegreso', $filtrosQuery ?? []) }}" class="px-3 pb-2 mb-0">
                    @csrf
                    <div class="border rounded p-2" style="background:#f8fbfd;">
                        <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                            <label class="small mb-0" for="email-listado-ingresoegreso">Enviar este listado</label>
                            <input type="email" name="email" id="email-listado-ingresoegreso" class="form-control form-control-sm" required
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
                                    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline" form="form-baja-envio-ie-{{ $envio->id }}">Dar de baja</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </form>
                @foreach (($enviosProgramados ?? collect()) as $envio)
                    <form id="form-baja-envio-ie-{{ $envio->id }}" method="post" action="{{ route('baja_envio_ingresoegreso', $envio->id) }}" class="d-none">
                        @csrf
                    </form>
                @endforeach
            </div>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Caja\IngresoEgresoListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'ingresoegreso',
            ])
            @if (! empty($filtros['solicitudpago_id']))
                <div class="px-3 py-2 border-bottom bg-light small">
                    <i class="fa fa-link text-primary"></i>
                    Filtrado por solicitud de pago id <strong>{{ (int) $filtros['solicitudpago_id'] }}</strong>.
                    <a href="{{ route('editar_solicitudpago', ['id' => (int) $filtros['solicitudpago_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       class="text-primary ml-1" target="_blank" rel="noopener">Abrir SP</a>
                    <a href="{{ route('ingresoegreso', \App\Support\Caja\IngresoEgresoListadoFiltros::paraQueryStringEmpresa($filtros ?? [])) }}"
                       class="ml-2">Quitar filtro SP</a>
                </div>
            @endif
            @if (! empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Alcance del listado:
                    <strong>{{ $alcance_centro_costo }}</strong>
                    <span class="text-muted">· Sin cobranzas POS (módulo Cobranza)</span>
                </div>
            @else
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-info-circle"></i>
                    Listado de ingresos/egresos de caja (OPP, remesas, transferencias, etc.).
                    Las cobranzas POS (gastronomía, estacionamiento) se consultan en el módulo Cobranza.
                </div>
            @endif
            <div class="px-3 py-2 border-bottom bg-white small d-flex flex-wrap" style="gap:1rem;">
                <span><strong>{{ number_format((int) $totalesListado['cantidad'], 0, ',', '.') }}</strong> movimientos</span>
                <span>Ingresos <strong>{{ number_format((float) $totalesListado['ingresos'], 2, ',', '.') }}</strong></span>
                <span>Egresos <strong>{{ number_format((float) $totalesListado['egresos'], 2, ',', '.') }}</strong></span>
                <span class="text-muted">Totales del filtro completo, en pesos.</span>
            </div>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ingresoegreso',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.ingresoegreso.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            @foreach (($filtros['calculadas'] ?? []) as $calc)
                                <th data-orderable="false">{{ $calc['etiqueta'] ?? '' }}</th>
                            @endforeach
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($caja_movimiento as $data)
                        @php
                            $ieMonto = $data->_ie_resumen ?? IngresoEgresoListadoMontoSupport::resumen($data);
                            $tonoFila = \App\Support\Listado\ListadoVisualSupport::tonoFila(
                                $data,
                                $filtros['formato'] ?? [],
                                static fn ($row, $key) => \App\Support\Caja\IngresoEgresoListadoColumnas::valorCelda($row, $key)
                            );
                        @endphp
                        <tr @class(['lw-tono-danger' => $tonoFila === 'danger', 'lw-tono-warning' => $tonoFila === 'warning', 'lw-tono-success' => $tonoFila === 'success'])>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.ingresoegreso.partials.workbench_celda', [
                                    'key' => $keyColumna,
                                    'data' => $data,
                                    'ieMonto' => $ieMonto,
                                    'retornoListadoQuery' => $retornoListadoQuery,
                                ])
                            @endforeach
                            @foreach (($filtros['calculadas'] ?? []) as $iCalc => $calc)
                                <td>{{ $data->{'calc_'.$iCalc} ?? '' }}</td>
                            @endforeach
                            <td class="text-nowrap">
                                @if (! empty($data->_es_pago_proveedor) && (can('listar-ingresos-egresos-caja', false) || can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false)))
                                    <button type="button"
                                        class="btn-accion-tabla tooltipsC js-op-enviar-proveedor text-success"
                                        title="{{ ! empty($data->_mail_enviado) ? 'Ver o reenviar el correo al proveedor' : 'Enviar OP por email al proveedor' }}"
                                        data-pagoproveedor-id="{{ (int) $data->pagoproveedor_id }}">
                                        <i class="fa {{ ! empty($data->_mail_enviado) ? 'fa-envelope' : 'fa-envelope-o' }}"></i>
                                    </button>
                                @endif
                                @if (can('listar-ingresos-egresos-caja', false))
                                    <a href="{{ route('imprimir_ingresoegreso', $data->id) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="Emitir comprobante / orden de pago"
                                       target="_blank" rel="noopener">
                                        <i class="fa fa-print"></i>
                                    </a>
                                @endif
                                @if (can('editar-ingresos-egresos-caja', false))
                                    @php
                                        $ieSoloLectura = ! empty($data->caja_movimiento_origen_id)
                                            || ! empty($data->caja_movimiento_revertido_por_id);
                                    @endphp
                                    <a href="{{ route('editar_ingresoegreso', ['id' => $data->id, 'origen' => 'ingresoegreso'] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="{{ $ieSoloLectura ? 'Consultar (solo lectura)' : 'Editar este registro' }}">
                                        <i class="fa {{ $ieSoloLectura ? 'fa-eye' : 'fa-edit' }}"></i>
                                    </a>
                                @endif
                                @if (
                                    can('anular-ingresos-egresos-caja', false)
                                    && empty($data->caja_movimiento_origen_id)
                                    && empty($data->caja_movimiento_revertido_por_id)
                                )
                                    <form action="{{ route('anular_fisicamente_ingresoegreso', ['id' => $data->id]) }}"
                                          class="d-inline form-anular-fisico-ie" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Anular físicamente (borra OP y reabre SP)">
                                            <i class="fa fa-ban text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                                @if (
                                    can('revertir-ingresos-egresos-caja', false)
                                    && empty($data->caja_movimiento_origen_id)
                                    && empty($data->caja_movimiento_revertido_por_id)
                                )
                                    <form action="{{ route('revertir_ingresoegreso_id', ['id' => $data->id]) }}"
                                          class="d-inline form-revertir-ie" method="POST">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $data->id }}">
                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir (compensatorio + asiento + Anita)">
                                            <i class="fa fa-undo text-warning"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($columnasVisibles) + count($filtros['calculadas'] ?? []) + 1 }}" class="text-center text-muted py-4">
                                No hay movimientos con los filtros aplicados.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $caja_movimiento->appends($filtrosQuery ?? [])->links() }}
@include('caja.ingresoegreso.partials.workbench_modales')
@include('compras.pagoproveedor.partials.modal_enviar_proveedor')
@endsection
