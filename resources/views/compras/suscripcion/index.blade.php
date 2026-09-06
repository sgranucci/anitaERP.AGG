@extends("theme.$theme.layout")
@section('titulo')
    Suscripciones
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/suscripcion/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\SuscripcionListadoFiltros;
    use App\Support\Compras\SuscripcionSupport;

    $filtrosQuery = $filtrosQuery ?? SuscripcionListadoFiltros::paraQueryString($filtros ?? []);
    $limpiarUrl = route('consultar_suscripcion', SuscripcionListadoFiltros::paraQueryStringExternos($filtros ?? []));
    $estadoActivo = (string) ($filtros['estado'] ?? '');
    $urlEstado = function (string $estado) use ($filtrosQuery) {
        $q = $filtrosQuery;
        unset($q['estado'], $q['page']);
        if ($estado !== '') {
            $q['estado'] = $estado;
        }

        return route('consultar_suscripcion', $q);
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="row">
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Vigentes</span>
                        <span class="info-box-number">{{ $kpis['vigentes'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Pendientes</span>
                        <span class="info-box-number">{{ $kpis['pendientes'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Vencidas / desvío</span>
                        <span class="info-box-number">{{ $kpis['vencidas'] }} <small class="text-danger">+{{ $kpis['desvios'] }}</small></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Mensualizado</span>
                        <span class="info-box-number">{{ number_format($kpis['mensualizado'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Suscripciones activas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual-suscripciones')
                    @if (can('aprobar-suscripcion', false))
                        <a href="{{ route('aprobacion_suscripcion') }}" class="btn btn-warning btn-sm ml-1">
                            <i class="fa fa-inbox"></i> Aprobación
                            @if (($pendientes_count ?? 0) > 0)
                                <span class="badge badge-light">{{ $pendientes_count }}</span>
                            @endif
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-suscripcion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SuscripcionListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-suscripcion',
                        'toggleId' => 'btn-toggle-filtros-suscripcion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_suscripcion'),
                        'nuevoRegistroCan' => 'crear-suscripcion',
                        'nuevoRegistroLabel' => 'Nueva',
                    ])
                </div>
            </div>

            <form method="get" action="{{ route('consultar_suscripcion') }}" id="form-filtros-suscripcion" class="mb-0">
                @include('compras.suscripcion.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>

            @include('compras.suscripcion.partials.filtros_externos', [
                'rutaNombre' => 'consultar_suscripcion',
                'empresa_query' => $empresa_query,
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'filtrosQuery' => $filtrosQuery,
            ])

            <div class="card-body py-2 border-bottom">
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado">
                    <a href="{{ $urlEstado('') }}" class="btn {{ $estadoActivo === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">Todos</a>
                    @foreach ($estados as $val => $label)
                        <a href="{{ $urlEstado($val) }}"
                           class="btn {{ $estadoActivo === $val ? 'btn-info' : 'btn-outline-info' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'exportar_suscripcion',
                    'queryparams' => $filtrosQuery,
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-suscripciones">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Suscripción</th>
                            <th>Proveedor</th>
                            <th>Área</th>
                            <th>CC</th>
                            <th>Solicitante</th>
                            <th>Dueño</th>
                            <th>Tarjeta</th>
                            <th class="text-right">Monto</th>
                            <th>Estado</th>
                            <th>Próx. venc.</th>
                            <th>OC N°</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $oc)
                            @php
                                $estado = SuscripcionSupport::estadoNegocio($oc);
                                $moneda = optional($oc->contrato_monedas)->nombre ?? optional($oc->contrato_monedas)->abreviatura ?? '';
                            @endphp
                            <tr>
                                <td><strong>{{ $oc->suscripcion_nombre ?: $oc->detalle }}</strong></td>
                                <td>{{ optional($oc->proveedores)->nombre ?? '—' }}</td>
                                <td>{{ $oc->suscripcion_area ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-light">
                                        {{ trim((optional($oc->centrocostos)->codigo ?? '').' '.(optional($oc->centrocostos)->nombre ?? '')) ?: '—' }}
                                    </span>
                                </td>
                                <td>{{ optional($oc->usuarios)->nombre ?: '—' }}</td>
                                <td>
                                    @if ($oc->suscripcion_owner_usuario_id)
                                        {{ optional($oc->suscripcion_owners)->nombre }}
                                    @else
                                        <span class="badge badge-warning">Sin dueño</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">••{{ $oc->suscripcion_tarjeta_ult4 ?: '····' }}</td>
                                <td class="text-right">
                                    {{ $moneda }} {{ number_format((float)($oc->suscripcion_monto_periodo ?? 0), 2, ',', '.') }}
                                    <small class="text-muted d-block">{{ SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad) }}</small>
                                </td>
                                <td>
                                    <span class="{{ SuscripcionSupport::clasePillEstado($estado) }}">
                                        {{ SuscripcionSupport::etiquetaEstado($estado) }}
                                    </span>
                                </td>
                                <td>{{ $oc->contrato_vigencia_hasta ? $oc->contrato_vigencia_hasta->format('d/m/Y') : '—' }}</td>
                                <td>
                                    @if ($oc->numeroordencompra)
                                        <a href="{{ url('compras/ordencompra/'.$oc->id.'/imprimir-pdf') }}" target="_blank">{{ $oc->numeroordencompra }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ route('ver_suscripcion', $oc->id) }}" class="btn btn-xs btn-outline-info" title="Ver"><i class="fa fa-eye"></i></a>
                                    @if ($estado === 'borrador' && can('crear-suscripcion', false))
                                        <form method="post" action="{{ route('enviar_borrador_suscripcion', $oc->id) }}" class="d-inline" onsubmit="return confirm('¿Enviar a aprobación?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-warning" title="Enviar"><i class="fa fa-paper-plane"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No hay suscripciones con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
