@extends("theme.$theme.layout")
@section('titulo')
    Cierre rendiciones vending
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.CIERRE_REND_VENDING = {
        urlPreview: @json(route('api_cierre_rendicion_maquinavending_preview')),
        urlEjecutar: @json(route('api_cierre_rendicion_maquinavending_ejecutar')),
        urlAnular: @json(route('api_cierre_rendicion_maquinavending_anular')),
        urlPreviewRango: @json(route('api_cierre_rendicion_maquinavending_preview_rango')),
        urlEjecutarRango: @json(route('api_cierre_rendicion_maquinavending_ejecutar_rango')),
        puedeEjecutar: @json(can('ejecutar-cierre-rendicion-maquinavending-contable', false)),
        puedeAnular: @json(can('anular-cierre-rendicion-maquinavending-contable', false)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_maquinavending/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_maquinavending/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_maquinavending/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_maquinavending/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Contable\CierreRendicionMaquinavendingGrupoSupport;
    use App\Support\Contable\CierreRendicionMaquinavendingListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre rendiciones vending</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @php
                        $retornoConciliacion = [];
                        foreach (($filtrosQuery ?? []) as $rqKey => $rqVal) {
                            $retornoConciliacion['retorno['.$rqKey.']'] = $rqVal;
                        }
                    @endphp
                    @if (can('listar-cierre-rendicion-maquinavending-contable', false))
                        <a href="{{ route('cierre_rendicion_maquinavending_conciliacion_flash', $retornoConciliacion) }}"
                           class="btn btn-sm btn-outline-info mr-2 mb-1" title="Conciliar rendiciones vs flash / rendgastro">
                            <i class="fa fa-balance-scale"></i> Conciliaci&oacute;n flash
                        </a>
                        <a href="{{ route('cierre_rendicion_maquinavending_diario_puntoventa', $retornoConciliacion) }}"
                           class="btn btn-sm btn-outline-primary mr-2 mb-1"
                           title="Diario por punto de venta y medios de pago">
                            <i class="fa fa-table"></i> Diario por PV / medios
                        </a>
                    @endif
                    @if (can('ejecutar-cierre-rendicion-maquinavending-contable', false))
                        <button type="button" class="btn btn-sm btn-success mr-2 mb-1" id="btn-abrir-cierre-rango"
                                title="Cerrar rendiciones pendientes por rango de fechas">
                            <i class="fa fa-calendar-check-o"></i> Cierre por rango
                        </button>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cierre-rend-vending',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CierreRendicionMaquinavendingListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => route('cierre_rendicion_maquinavending_contable'),
                        'placeholder' => 'Búsqueda rápida (ticket, ID, empresa…)',
                        'toggleTarget' => '#panel-filtros-cierre-rend-vending',
                        'toggleId' => 'btn-toggle-filtros-cierre-rend-vending',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cierre_rendicion_maquinavending_contable') }}" id="form-filtros-cierre-rend-vending" class="mb-0">
                @include('contable.cierre_rendicion_maquinavending.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_cierre_rendicion_maquinavending_contable',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <p class="small text-muted px-3 pt-2 mb-0">
                    Un asiento contable por <strong>fecha jornada + punto de venta</strong>.
                    Use <i class="fa fa-chevron-down"></i> para ver cada rendici&oacute;n del d&iacute;a.
                </p>
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width30"></th>
                            <th>Fecha jornada</th>
                            <th>Empresa</th>
                            <th>Punto venta</th>
                            <th class="text-center">Rend.</th>
                            <th class="text-right" title="Total ventas del grupo">Ventas</th>
                            <th class="text-right" title="Total invitaciones del grupo">Invit.</th>
                            <th class="text-right">Cobrado</th>
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
                                CierreRendicionMaquinavendingGrupoSupport::ESTADO_CERRADA => 'table-success',
                                CierreRendicionMaquinavendingGrupoSupport::ESTADO_PARCIAL => 'table-warning',
                                default => '',
                            };
                        @endphp
                        <tr class="grupo-resumen {{ $rowClass }}"
                            data-empresa-id="{{ $grupo['empresa_id'] }}"
                            data-fecha-dia="{{ $grupo['fecha_dia'] }}"
                            data-puntoventa-cae-id="{{ $grupo['puntoventa_cae_id'] }}">
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-link btn-sm p-0 js-toggle-grupo-detalle"
                                        data-target="#detalle-grupo-{{ $grupoId }}"
                                        title="Ver rendiciones del grupo">
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                            </td>
                            <td>{{ $grupo['fecha_dia_fmt'] ?? '—' }}</td>
                            <td>{{ $grupo['empresa_nombre'] ?? '—' }}</td>
                            <td><small>{{ $grupo['puntoventa_label'] ?? '—' }}</small></td>
                            <td class="text-center">
                                {{ $grupo['cantidad_rendiciones'] ?? 0 }}
                                @if (($grupo['cantidad_pendiente'] ?? 0) > 0 && ($grupo['cantidad_cerrada'] ?? 0) > 0)
                                    <br><small class="text-muted">{{ $grupo['cantidad_pendiente'] }} pend.</small>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ number_format((float) ($grupo['total_ventas'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right text-nowrap">
                                @if ((float) ($grupo['total_invitaciones'] ?? 0) > 0.009)
                                    {{ number_format((float) $grupo['total_invitaciones'], 2, ',', '.') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ number_format((float) ($grupo['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                            <td>
                                @if ($estado === CierreRendicionMaquinavendingGrupoSupport::ESTADO_CERRADA)
                                    <span class="badge badge-success">Cerrado</span>
                                @elseif ($estado === CierreRendicionMaquinavendingGrupoSupport::ESTADO_LEGACY)
                                    <span class="badge badge-secondary">Hist&oacute;rico</span>
                                @elseif ($estado === CierreRendicionMaquinavendingGrupoSupport::ESTADO_PARCIAL)
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
                                @if (! empty($grupo['puede_cerrar']) && can('ejecutar-cierre-rendicion-maquinavending-contable', false))
                                    <button type="button" class="btn btn-success btn-sm js-cerrar-grupo"
                                            title="Generar cierre contable del grupo">
                                        <i class="fa fa-lock"></i>
                                    </button>
                                @endif
                                @if (! empty($grupo['puede_anular']) && can('anular-cierre-rendicion-maquinavending-contable', false))
                                    <button type="button" class="btn btn-outline-danger btn-sm js-anular-grupo"
                                            title="Anular cierre contable del grupo">
                                        <i class="fa fa-unlock"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @include('contable.cierre_rendicion_maquinavending.partials.detalle_rendiciones_grupo', [
                            'grupoId' => $grupoId,
                            'rendiciones' => $grupo['rendiciones'] ?? [],
                        ])
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Sin rendiciones vending presentadas en caja.</td>
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

@include('contable.cierre_rendicion_maquinavending.modal_preview_asiento')
@if (can('ejecutar-cierre-rendicion-maquinavending-contable', false))
    @include('contable.cierre_rendicion_maquinavending.modal_cierre_rango')
@endif
@endsection
