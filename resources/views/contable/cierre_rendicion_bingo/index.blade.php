@extends("theme.$theme.layout")
@section('titulo')
    Cierre Bingo
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.CIERRE_REND_BINGO = {
        urlPreview: @json(route('api_cierre_rendicion_bingo_preview')),
        urlEjecutar: @json(route('api_cierre_rendicion_bingo_ejecutar')),
        urlAnular: @json(route('api_cierre_rendicion_bingo_anular')),
        urlPreviewRango: @json(route('api_cierre_rendicion_bingo_preview_rango')),
        urlEjecutarRango: @json(route('api_cierre_rendicion_bingo_ejecutar_rango')),
        puedeEjecutar: @json(can('ejecutar-cierre-rendicion-bingo-contable', false)),
        puedeAnular: @json(can('anular-cierre-rendicion-bingo-contable', false)),
    };
    window.CIERRE_REND_PENDIENTES = {
        urlPendientes: @json(route('api_cierre_rendicion_bingo_pendientes')),
        empresaIdFiltro: @json((int) ($filtros['empresa_id'] ?? 0)),
        modalRangoId: 'modal-cierre-rango-rend-bingo',
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_bingo/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_bingo/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_bingo/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_bingo/index.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/includes/pendientes_cierre_modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/includes/pendientes_cierre_modal.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Contable\CierreRendicionBingoGrupoSupport;
    use App\Support\Contable\CierreRendicionBingoListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;
@endphp

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cierre-rend-bingo-overlay',
    'tituloId' => 'cierre-rend-bingo-overlay-titulo',
    'subtituloId' => 'cierre-rend-bingo-overlay-subtitulo',
    'titulo' => 'Cerrando bingo…',
    'subtitulo' => 'Escribe en Anita. No cierre la página ni vuelva a confirmar.',
])
@php
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route(
        'cierre_rendicion_bingo_contable',
        CierreRendicionBingoListadoFiltros::paraQueryStringEmpresa($filtros ?? [])
    );
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre Bingo</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('listar-cierre-rendicion-bingo-contable', false))
                        <button type="button" class="btn btn-sm btn-warning mr-2 mb-1" id="btn-abrir-pendientes-cierre"
                                title="Ver jornadas pendientes de cierre contable">
                            <i class="fa fa-hourglass-half"></i> Pendientes de cerrar
                            <span class="badge badge-dark ml-1 d-none" id="badge-pendientes-cierre">0</span>
                        </button>
                        <a href="{{ route('cierre_rendicion_bingo_conciliacion_flash', $retornoListadoQuery) }}"
                           class="btn btn-sm btn-outline-info mr-2 mb-1" title="Informe p-vtabingo y cruce vs flash">
                            <i class="fa fa-balance-scale"></i> Conciliación flash
                        </a>
                    @endif
                    @if (can('ejecutar-cierre-rendicion-bingo-contable', false))
                        <button type="button" class="btn btn-sm btn-success mr-2 mb-1" id="btn-abrir-cierre-rango"
                                title="Cerrar jornadas pendientes por rango de fechas">
                            <i class="fa fa-calendar-check-o"></i> Cierre por rango
                        </button>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cierre-rend-bingo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CierreRendicionBingoListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (ticket, ID, empresa…)',
                        'toggleTarget' => '#panel-filtros-cierre-rend-bingo',
                        'toggleId' => 'btn-toggle-filtros-cierre-rend-bingo',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cierre_rendicion_bingo_contable') }}" id="form-filtros-cierre-rend-bingo" class="mb-0">
                @include('contable.cierre_rendicion_bingo.partials.filtros_listado')
            </form>
            @include('contable.cierre_rendicion_bingo.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_cierre_rendicion_bingo_contable',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <p class="small text-muted px-3 pt-2 mb-0">
                    Un cierre diario por <strong>empresa + fecha jornada</strong>.
                    Genera FBI exenta en ventas ERP (PV por empresa) y asientos BIN → ctamov Anita.
                    Use <i class="fa fa-chevron-down"></i> para consultar las rendiciones y cierres de turno (PDF).
                </p>
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width30"></th>
                            <th>Fecha jornada</th>
                            <th>Empresa</th>
                            <th>PV FBI</th>
                            <th class="text-center">Rend.</th>
                            <th class="text-right">Recaudaci&oacute;n</th>
                            <th>FBI</th>
                            <th>Estado cierre</th>
                            <th>Asiento</th>
                            <th class="width120" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grupos as $idx => $grupo)
                        @php
                            $grupoId = 'g'.$idx;
                            $estado = $grupo['estado_grupo'] ?? '';
                            $rowClass = match ($estado) {
                                CierreRendicionBingoGrupoSupport::ESTADO_CERRADA => 'table-success',
                                CierreRendicionBingoGrupoSupport::ESTADO_PARCIAL => 'table-warning',
                                default => '',
                            };
                        @endphp
                        <tr class="grupo-resumen {{ $rowClass }}"
                            data-empresa-id="{{ $grupo['empresa_id'] }}"
                            data-fecha-dia="{{ $grupo['fecha_dia'] }}">
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-link btn-sm p-0 js-toggle-grupo-detalle"
                                        data-target="#detalle-grupo-{{ $grupoId }}"
                                        title="Ver rendiciones y cierres de turno que alimentan este cierre">
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                            </td>
                            <td>{{ $grupo['fecha_dia_fmt'] ?? '—' }}</td>
                            <td>{{ $grupo['empresa_nombre'] ?? '—' }}</td>
                            <td class="text-center">{{ $grupo['puntoventa_fbi'] ?? '—' }}</td>
                            <td class="text-center">
                                {{ $grupo['cantidad_rendiciones'] ?? 0 }}
                                @if (($grupo['cantidad_pendiente'] ?? 0) > 0 && ($grupo['cantidad_cerrada'] ?? 0) > 0)
                                    <br><small class="text-muted">{{ $grupo['cantidad_pendiente'] }} pend.</small>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ number_format((float) ($grupo['total_recaudacion'] ?? 0), 2, ',', '.') }}</td>
                            <td><small>{{ $grupo['factura_label'] ?? '—' }}</small></td>
                            <td>
                                @if ($estado === CierreRendicionBingoGrupoSupport::ESTADO_CERRADA)
                                    <span class="badge badge-success">Cerrado</span>
                                @elseif ($estado === CierreRendicionBingoGrupoSupport::ESTADO_PARCIAL)
                                    <span class="badge badge-warning">Parcial</span>
                                @else
                                    <span class="badge badge-warning">Pendiente</span>
                                @endif
                            </td>
                            <td>
                                @if (! empty($grupo['asiento_id']) && can('listar-asiento', false))
                                    <a href="{{ route('editar_asiento', ['id' => $grupo['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="text-primary" target="_blank" rel="noopener">
                                        {{ $grupo['asiento_numero'] ?? ('#'.$grupo['asiento_id']) }}
                                    </a>
                                @elseif (($grupo['asiento_ids_distintos'] ?? 0) > 1)
                                    <span class="text-muted small">Varios asientos</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (! empty($grupo['puede_cerrar']) && can('ejecutar-cierre-rendicion-bingo-contable', false))
                                    <button type="button" class="btn btn-success btn-sm js-cerrar-grupo"
                                            title="Generar cierre contable + FBI ERP">
                                        <i class="fa fa-lock"></i>
                                    </button>
                                @endif
                                @if (! empty($grupo['puede_anular']) && can('anular-cierre-rendicion-bingo-contable', false))
                                    <button type="button" class="btn btn-outline-danger btn-sm js-anular-grupo"
                                            title="Anular cierre contable del d&iacute;a">
                                        <i class="fa fa-unlock"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @include('contable.cierre_rendicion_bingo.partials.detalle_rendiciones_grupo', [
                            'grupoId' => $grupoId,
                            'rendiciones' => $grupo['rendiciones'] ?? [],
                        ])
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Sin rendiciones de bingo presentadas en caja.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($grupos instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="card-footer clearfix">
                <span class="text-muted small">
                    {{ $grupos->firstItem() }}–{{ $grupos->lastItem() }} de {{ $grupos->total() }} grupo(s)
                </span>
                {{ $grupos->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('contable.cierre_rendicion_bingo.modal_preview_asiento')
@if (can('listar-cierre-rendicion-bingo-contable', false))
    @include('contable.partials.modal_pendientes_cierre', [
        'rutaListado' => route('cierre_rendicion_bingo_contable'),
        'estadoPendiente' => \App\Support\Contable\CierreRendicionBingoListadoFiltros::ESTADO_PENDIENTE,
        'permisoEjecutar' => 'ejecutar-cierre-rendicion-bingo-contable',
        'textoIntro' => 'Jornadas de bingo presentadas en caja sin cierre contable. Un cierre diario genera <strong>FBI en ventas ERP + asientos/ctamov</strong>. El cierre debe ser <strong>correlativo por fecha</strong>.',
        'mostrarPuntoventa' => false,
        'mostrarFacturado' => false,
        'labelTurnos' => 'Rendiciones',
        'labelCobrado' => 'Recaudación',
        'exigeCorrelatividad' => true,
    ])
@endif
@if (can('ejecutar-cierre-rendicion-bingo-contable', false))
    @include('contable.cierre_rendicion_bingo.modal_cierre_rango')
@endif
@endsection
