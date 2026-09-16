@extends("theme.$theme.layout")

@section('titulo')
    Pedidos Tiendanube
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/tiendanube_pedido/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tn-proceso-overlay',
    'tituloId' => 'tn-proceso-titulo',
    'subtituloId' => 'tn-proceso-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Puede demorar. No cierre la página.',
])

@php
    $listosPorId = $listosPorId ?? [];
    $puedeFacturar = $puedeFacturar ?? false;
    $cantListosPagina = collect($listosPorId)->filter()->count();
@endphp

<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pedidos Tiendanube</h3>
                <div class="card-tools">
                    @if (can('sincronizar-tiendanube-pedidos', false))
                        <form action="{{ route('tiendanube_pedidos_sincronizar') }}" method="POST" id="form-tn-sync" class="d-inline">
                            @csrf
                            <input type="hidden" name="desde" id="sync_desde" value="{{ $filtros['desde'] ?? '' }}">
                            <input type="hidden" name="hasta" id="sync_hasta" value="{{ $filtros['hasta'] ?? '' }}">
                            <button type="submit" class="btn btn-success btn-sm" id="btn-tn-sync">
                                <i class="fa fa-sync"></i> Sincronizar desde Tiendanube
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if (! $apiOk)
                    <div class="alert alert-warning">
                        Faltan credenciales: configure <code>TIENDANUBE_STORE_ID</code> y <code>TIENDANUBE_ACCESS_TOKEN</code> en <code>.env</code>.
                    </div>
                @endif

                <form method="get" action="{{ route('tiendanube_pedidos') }}" class="form-inline mb-2" id="form-tn-filtros">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1">Desde</label>
                        <input type="date" name="desde" id="filtro_desde" class="form-control form-control-sm"
                               value="{{ $filtros['desde'] ?? '' }}">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1">Hasta</label>
                        <input type="date" name="hasta" id="filtro_hasta" class="form-control form-control-sm"
                               value="{{ $filtros['hasta'] ?? '' }}">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1">Estado</label>
                        <select name="estado_erp" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            @foreach ($estados as $cod => $eti)
                                <option value="{{ $cod }}" @if (($filtros['estado_erp'] ?? '') === $cod) selected @endif>
                                    {{ $eti }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1">Pago</label>
                        <select name="payment_status" class="form-control form-control-sm">
                            <option value="paid" @if (($filtros['payment_status'] ?? '') === 'paid') selected @endif>Pagado</option>
                            <option value="" @if (($filtros['payment_status'] ?? '') === '') selected @endif>Todos</option>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <input type="text" name="buscar" class="form-control form-control-sm"
                               placeholder="Nº / cliente / doc"
                               value="{{ $filtros['buscar'] ?? '' }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mb-2" title="Filtra lo ya bajado a anitaERP">
                        Consultar
                    </button>
                </form>
                <p class="text-muted small mb-3">
                    <strong>Consultar</strong> filtra pedidos ya guardados en anitaERP.
                    <strong>Sincronizar</strong> baja/actualiza pedidos pagados desde Tiendanube.
                    <strong>Facturar seleccionados</strong> solo toma pedidos listos (SKU+comb+talle, PV/depósito/medio y datos fiscales OK).
                </p>

                @if ($puedeFacturar)
                    <form method="POST" action="{{ route('tiendanube_pedidos_facturar_masivo') }}" id="form-tn-masivo">
                        @csrf
                        <input type="hidden" name="desde" value="{{ $filtros['desde'] ?? '' }}">
                        <input type="hidden" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
                        <input type="hidden" name="estado_erp" value="{{ $filtros['estado_erp'] ?? '' }}">
                        <input type="hidden" name="payment_status" value="{{ $filtros['payment_status'] ?? 'paid' }}">
                        <input type="hidden" name="buscar" value="{{ $filtros['buscar'] ?? '' }}">
                        <div class="mb-2">
                            <button type="submit" class="btn btn-warning btn-sm" id="btn-tn-masivo"
                                    @if ($cantListosPagina === 0) disabled @endif>
                                <i class="fa fa-file-invoice"></i> Facturar seleccionados
                                <span class="badge badge-light" id="tn-masivo-count">0</span>
                            </button>
                            <span class="text-muted small ml-2">
                                Listos en esta página: {{ $cantListosPagina }} (máx. 30 por corrida)
                            </span>
                        </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @if ($puedeFacturar)
                                    <th style="width:36px;">
                                        <input type="checkbox" id="tn-check-all-listos" title="Marcar todos los listos">
                                    </th>
                                @endif
                                <th>Nº TN</th>
                                <th>ID interno</th>
                                <th>Pagado</th>
                                <th>Cliente</th>
                                <th>Doc</th>
                                <th>Gateway</th>
                                <th class="text-right">Total</th>
                                <th>Estado ERP</th>
                                <th>Listo</th>
                                <th>Venta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($coleccion as $p)
                                @php
                                    $badge = \App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport::badgeClass($p->estado_erp);
                                    $eti = \App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport::etiqueta($p->estado_erp);
                                    $esListo = ! empty($listosPorId[(int) $p->id]);
                                @endphp
                                <tr class="@if ($esListo) table-success @endif">
                                    @if ($puedeFacturar)
                                        <td class="text-center">
                                            @if ($esListo)
                                                <input type="checkbox" class="tn-check-pedido" name="pedido_ids[]"
                                                       value="{{ $p->id }}">
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $p->order_number }}</td>
                                    <td>{{ $p->tiendanube_order_id }}</td>
                                    <td>{{ $p->paid_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $p->customer_name }}</td>
                                    <td>{{ $p->customer_doc }}</td>
                                    <td>{{ $p->gateway_name ?: $p->gateway }}</td>
                                    <td class="text-right">{{ number_format((float) $p->total, 2, ',', '.') }}</td>
                                    <td><span class="badge {{ $badge }}">{{ $eti }}</span></td>
                                    <td>
                                        @if ($esListo)
                                            <span class="badge badge-success">Sí</span>
                                        @elseif ($p->venta_id)
                                            —
                                        @else
                                            <span class="badge badge-secondary" title="Abrir el pedido para ver qué falta">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($p->venta_id)
                                            {{ $p->venta->codigo ?? ('#'.$p->venta_id) }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn-accion-tabla tooltipsC" title="Ver / facturar"
                                           href="{{ route('tiendanube_pedido_show', $p->id) }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $puedeFacturar ? 12 : 11 }}" class="text-center text-muted">
                                        Sin pedidos. Sincronice desde Tiendanube o amplíe el rango.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($puedeFacturar)
                    </form>
                @endif

                @if ($coleccion->total() > 0)
                    <div class="mt-2">
                        {{ $coleccion->firstItem() }}–{{ $coleccion->lastItem() }} de {{ $coleccion->total() }}
                        {{ $coleccion->appends($filtrosQuery)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
