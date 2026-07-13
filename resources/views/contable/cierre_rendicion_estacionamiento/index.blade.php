@extends("theme.$theme.layout")
@section('titulo')
    Cierre rendiciones estacionamiento
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.CIERRE_REND_EST = {
        urlPreview: @json(route('api_cierre_rendicion_estacionamiento_preview')),
        urlEjecutar: @json(route('api_cierre_rendicion_estacionamiento_ejecutar')),
        urlAnular: @json(route('api_cierre_rendicion_estacionamiento_anular')),
        urlPreviewRango: @json(route('api_cierre_rendicion_estacionamiento_preview_rango')),
        urlEjecutarRango: @json(route('api_cierre_rendicion_estacionamiento_ejecutar_rango')),
        puedeEjecutar: @json(can('ejecutar-cierre-rendicion-estacionamiento-contable', false)),
        puedeAnular: @json(can('anular-cierre-rendicion-estacionamiento-contable', false)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_rendicion_estacionamiento/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
    use App\Support\Contable\CierreRendicionEstacionamientoListadoFiltros;
    $vistaPorTurno = ! empty($vistaPorTurno);
    $vistaActual = $vistaPorTurno
        ? CierreRendicionEstacionamientoListadoFiltros::VISTA_POR_TURNO
        : CierreRendicionEstacionamientoListadoFiltros::VISTA_AGRUPADO;
    $qsBase = $filtrosQuery ?? [];
    $qsAgrupado = array_merge($qsBase, ['vista' => CierreRendicionEstacionamientoListadoFiltros::VISTA_AGRUPADO]);
    unset($qsAgrupado['vista']); // default: no enviar vista
    $qsPorTurno = array_merge($qsBase, ['vista' => CierreRendicionEstacionamientoListadoFiltros::VISTA_POR_TURNO]);
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
                <h3 class="card-title">Cierre rendiciones estacionamiento</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('listar-cierre-rendicion-estacionamiento-contable', false))
                        <a href="{{ route('cierre_rendicion_estacionamiento_conciliacion_flash', $retornoListadoQuery) }}"
                           class="btn btn-sm btn-outline-info mr-2 mb-1" title="Conciliar rendiciones vs flash">
                            <i class="fa fa-balance-scale"></i> Conciliaci&oacute;n flash
                        </a>
                    @endif
                    @if (can('ejecutar-cierre-rendicion-estacionamiento-contable', false))
                        <button type="button" class="btn btn-sm btn-success mr-2 mb-1" id="btn-abrir-cierre-rango"
                                title="Cerrar rendiciones pendientes por rango de fechas">
                            <i class="fa fa-calendar-check-o"></i> Cierre por rango
                        </button>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cierre-rend-est',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CierreRendicionEstacionamientoListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => route('cierre_rendicion_estacionamiento_contable'),
                        'placeholder' => 'Búsqueda rápida (ticket, ID, empresa…)',
                        'toggleTarget' => '#panel-filtros-cierre-rend-est',
                        'toggleId' => 'btn-toggle-filtros-cierre-rend-est',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cierre_rendicion_estacionamiento_contable') }}" id="form-filtros-cierre-rend-est" class="mb-0">
                @include('contable.cierre_rendicion_estacionamiento.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_cierre_rendicion_estacionamiento_contable',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 pt-2">
                    <p class="small text-muted mb-1">
                        @if ($vistaPorTurno)
                            Vista <strong>por turno</strong>: una fila por rendici&oacute;n/turno.
                            El cierre contable sigue siendo por <strong>fecha jornada + punto de venta</strong>.
                            <strong>Venta neta</strong> = facturas − NC;
                            <strong>Venta total</strong> = neta + NC (m&aacute;s comparable al asiento).
                        @else
                            Vista <strong>unificada</strong>: un asiento contable por <strong>fecha jornada + punto de venta</strong>.
                            Use <i class="fa fa-chevron-down"></i> para ver cada rendici&oacute;n del d&iacute;a.
                            <strong>Venta neta</strong> = facturas − NC;
                            <strong>Venta total</strong> = neta + NC (m&aacute;s comparable al asiento).
                        @endif
                    </p>
                    <div class="btn-group btn-group-sm mb-1" role="group" aria-label="Vista listado">
                        <a href="{{ route('cierre_rendicion_estacionamiento_contable', $qsAgrupado) }}"
                           class="btn btn-outline-secondary {{ $vistaPorTurno ? '' : 'active' }}">
                            Unificado (PV + fecha)
                        </a>
                        <a href="{{ route('cierre_rendicion_estacionamiento_contable', $qsPorTurno) }}"
                           class="btn btn-outline-secondary {{ $vistaPorTurno ? 'active' : '' }}">
                            Por turno
                        </a>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    @if ($vistaPorTurno)
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha jornada</th>
                            <th>Empresa</th>
                            <th>Punto venta</th>
                            <th>Turno</th>
                            <th>Ticket</th>
                            <th class="text-right" title="Venta neta (facturas − NC)">Venta neta</th>
                            <th class="text-right" title="Notas de crédito (absoluto)">NC</th>
                            <th class="text-right" title="Venta bruta = neta + NC (comparable al asiento)">Venta total</th>
                            <th class="text-right">Invit.</th>
                            <th class="text-right">Cobrado</th>
                            <th>Estado</th>
                            <th>Asiento</th>
                            <th class="width120" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('contable.cierre_rendicion_estacionamiento.partials.tabla_por_turno', [
                            'coleccion' => $coleccion,
                            'retornoListadoQuery' => $retornoListadoQuery,
                        ])
                    </tbody>
                    @else
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width30"></th>
                            <th>Fecha jornada</th>
                            <th>Empresa</th>
                            <th>Punto venta</th>
                            <th class="text-center">Rend.</th>
                            <th class="text-right" title="Venta neta del grupo (facturas − NC)">Venta neta</th>
                            <th class="text-right" title="Notas de crédito (absoluto)">NC</th>
                            <th class="text-right" title="Venta bruta = neta + NC (comparable al asiento)">Venta total</th>
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
                                CierreRendicionEstacionamientoGrupoSupport::ESTADO_CERRADA => 'table-success',
                                CierreRendicionEstacionamientoGrupoSupport::ESTADO_PARCIAL => 'table-warning',
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
                                @if ((float) ($grupo['total_notas_credito'] ?? 0) > 0.009)
                                    {{ number_format((float) $grupo['total_notas_credito'], 2, ',', '.') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right text-nowrap font-weight-bold">{{ number_format((float) ($grupo['total_ventas_brutas'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right text-nowrap">
                                @if ((float) ($grupo['total_invitaciones'] ?? 0) > 0.009)
                                    {{ number_format((float) $grupo['total_invitaciones'], 2, ',', '.') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ number_format((float) ($grupo['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
                            <td>
                                @if ($estado === CierreRendicionEstacionamientoGrupoSupport::ESTADO_CERRADA)
                                    <span class="badge badge-success">Cerrado</span>
                                @elseif ($estado === CierreRendicionEstacionamientoGrupoSupport::ESTADO_LEGACY)
                                    <span class="badge badge-secondary">Hist&oacute;rico</span>
                                @elseif ($estado === CierreRendicionEstacionamientoGrupoSupport::ESTADO_PARCIAL)
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
                                @if (! empty($grupo['puede_cerrar']) && can('ejecutar-cierre-rendicion-estacionamiento-contable', false))
                                    <button type="button" class="btn btn-success btn-sm js-cerrar-grupo"
                                            title="Generar cierre contable del grupo">
                                        <i class="fa fa-lock"></i>
                                    </button>
                                @endif
                                @if (! empty($grupo['puede_anular']) && can('anular-cierre-rendicion-estacionamiento-contable', false))
                                    <button type="button" class="btn btn-outline-danger btn-sm js-anular-grupo"
                                            title="Anular cierre contable del grupo">
                                        <i class="fa fa-unlock"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @include('contable.cierre_rendicion_estacionamiento.partials.detalle_rendiciones_grupo', [
                            'grupoId' => $grupoId,
                            'rendiciones' => $grupo['rendiciones'] ?? [],
                        ])
                        @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">Sin rendiciones de turno presentadas en caja.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @endif
                </table>
            </div>
            @if ($vistaPorTurno && $coleccion instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="card-footer clearfix">
                <span class="text-muted small">
                    {{ $coleccion->firstItem() }}–{{ $coleccion->lastItem() }} de {{ $coleccion->total() }} rendici&oacute;n(es)
                </span>
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
            @elseif (! $vistaPorTurno && $grupos instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
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

@include('contable.cierre_rendicion_estacionamiento.modal_preview_asiento')
@if (can('ejecutar-cierre-rendicion-estacionamiento-contable', false))
    @include('contable.cierre_rendicion_estacionamiento.modal_cierre_rango')
@endif
@endsection
