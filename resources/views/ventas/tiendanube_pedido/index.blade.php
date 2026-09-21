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
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp

<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pedidos Tiendanube</h3>
                <div class="card-tools">
                    @if (can('editar-configuracion-tiendanube', false))
                        <a href="{{ route('editar_configuracion_tiendanube') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-cog"></i> Configuraci&oacute;n
                        </a>
                    @endif
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
                        Faltan credenciales de Tiendanube en <code>.env</code>
                        (<code>TIENDANUBE_ACCESS_TOKEN</code> y, para Boaonda, <code>TIENDANUBE_BOAONDA_ACCESS_TOKEN</code>).
                    </div>
                @elseif (! ($apiHealth['auth_ok'] ?? true))
                    <div class="alert alert-danger">
                        <strong>API Tiendanube: token inválido.</strong>
                        {{ $apiHealth['mensaje_ui'] ?? '' }}
                        @if (! empty($apiHealth['checked_at']))
                            <br><small>Última verificación: {{ \Carbon\Carbon::parse($apiHealth['checked_at'])->format('d/m/Y H:i') }}</small>
                        @endif
                    </div>
                @elseif ($apiHealth['stale'] ?? false)
                    <div class="alert alert-warning">
                        <strong>Sincronización atrasada.</strong>
                        {{ $apiHealth['mensaje_ui'] ?? '' }}
                        El cron baja pedidos cada 2 h (07–23). También podés usar «Sincronizar».
                    </div>
                @elseif (! empty($apiHealth['last_sync_ok_at']))
                    <div class="alert alert-success py-2 mb-2">
                        <small>
                            API OK · Último sync:
                            {{ \Carbon\Carbon::parse($apiHealth['last_sync_ok_at'])->format('d/m/Y H:i') }}
                        </small>
                    </div>
                @endif

                @php
                    $estadoErpActivo = (string) ($filtros['estado_erp'] ?? '');
                    $statusTnActivo = (string) ($filtros['status_tn'] ?? '');
                    $pagoActivo = array_key_exists('payment_status', $filtros)
                        ? (string) ($filtros['payment_status'] ?? '')
                        : 'paid';
                @endphp
                <form method="get" action="{{ route('tiendanube_pedidos') }}" class="mb-2" id="form-tn-filtros">
                    <input type="hidden" name="consultar" value="1">
                    <input type="hidden" name="estado_erp" id="filtro_estado_erp" value="{{ $estadoErpActivo }}">
                    <input type="hidden" name="status_tn" id="filtro_status_tn" value="{{ $statusTnActivo }}">
                    <input type="hidden" name="payment_status" id="filtro_payment_status" value="{{ $pagoActivo }}">
                    <input type="hidden" name="store_id" id="filtro_store_id" value="{{ $filtros['store_id'] ?? '' }}">

                    <div class="form-inline mb-2">
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
                            <input type="text" name="buscar" class="form-control form-control-sm"
                                   placeholder="Nº / cliente / doc"
                                   value="{{ $filtros['buscar'] ?? '' }}">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mb-2" title="Filtra lo ya bajado a anitaERP">
                            Consultar
                        </button>
                    </div>

                    <div class="mb-2 d-flex flex-wrap align-items-center" style="gap:6px;">
                        <span class="text-muted small mr-1">Estado ERP</span>
                        <button type="button" class="btn btn-sm tn-filtro-etiq {{ $estadoErpActivo === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-campo="estado_erp" data-valor="" title="Todos los estados ERP">Todos</button>
                        @foreach ($estados as $cod => $eti)
                            <button type="button"
                                    class="btn btn-sm tn-filtro-etiq {{ $estadoErpActivo === $cod ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    data-campo="estado_erp" data-valor="{{ $cod }}">{{ $eti }}</button>
                        @endforeach
                    </div>

                    <div class="mb-2 d-flex flex-wrap align-items-center" style="gap:6px;">
                        <span class="text-muted small mr-1">Estado TN</span>
                        <button type="button" class="btn btn-sm tn-filtro-etiq {{ $statusTnActivo === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-campo="status_tn" data-valor="" title="Todos los estados Tiendanube">Todos</button>
                        @foreach (($estadosExternos ?? []) as $cod => $eti)
                            <button type="button"
                                    class="btn btn-sm tn-filtro-etiq {{ $statusTnActivo === $cod ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    data-campo="status_tn" data-valor="{{ $cod }}">{{ $eti }}</button>
                        @endforeach
                    </div>

                    <div class="mb-2 d-flex flex-wrap align-items-center" style="gap:6px;">
                        <span class="text-muted small mr-1">Pago</span>
                        <button type="button" class="btn btn-sm tn-filtro-etiq {{ $pagoActivo === 'paid' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-campo="payment_status" data-valor="paid">Pagado</button>
                        <button type="button" class="btn btn-sm tn-filtro-etiq {{ $pagoActivo === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-campo="payment_status" data-valor="">Todos</button>
                    </div>

                    @php $storeActivo = (string) ($filtros['store_id'] ?? ''); @endphp
                    @if (count($tiendas ?? []) > 1)
                        <div class="mb-2 d-flex flex-wrap align-items-center" style="gap:6px;">
                            <span class="text-muted small mr-1">Tienda</span>
                            <button type="button" class="btn btn-sm tn-filtro-etiq {{ $storeActivo === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    data-campo="store_id" data-valor="">Todas</button>
                            @foreach ($tiendas as $tienda)
                                <button type="button"
                                        class="btn btn-sm tn-filtro-etiq {{ $storeActivo === $tienda['store_id'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        data-campo="store_id" data-valor="{{ $tienda['store_id'] }}">{{ $tienda['nombre'] }}</button>
                            @endforeach
                        </div>
                    @endif
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
                        <input type="hidden" name="status_tn" value="{{ $filtros['status_tn'] ?? '' }}">
                        <input type="hidden" name="payment_status" value="{{ $filtros['payment_status'] ?? 'paid' }}">
                        <input type="hidden" name="store_id" value="{{ $filtros['store_id'] ?? '' }}">
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
                                <th>Tienda</th>
                                <th>ID interno</th>
                                <th>Pagado</th>
                                <th>Cliente</th>
                                <th>Doc</th>
                                <th>Gateway</th>
                                <th class="text-right">Total</th>
                                <th>Estado TN</th>
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
                                    $badgeTn = \App\Support\Ventas\Tiendanube\TiendanubePedidoStatusExternoSupport::badgeClass($p->status);
                                    $etiTn = \App\Support\Ventas\Tiendanube\TiendanubePedidoStatusExternoSupport::etiqueta($p->status);
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
                                    <td>{{ \App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport::nombre($p->store_id) }}</td>
                                    <td>{{ $p->tiendanube_order_id }}</td>
                                    <td>{{ $p->paid_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ \App\Support\Ventas\Tiendanube\TiendanubePedidoReceptorSupport::nombreDesdePedido($p) }}</td>
                                    <td>{{ $p->customer_doc }}</td>
                                    <td>
                                        @php
                                            $gwLabel = $p->gateway_name ?: $p->gateway;
                                            $pj = is_array($p->payment_json) ? $p->payment_json : [];
                                            $method = $pj['method'] ?? null;
                                            $card = $pj['credit_card_company'] ?? null;
                                            $extra = trim(implode(' · ', array_filter([
                                                $method && $method !== strtolower((string) ($p->gateway ?? '')) ? $method : null,
                                                $card,
                                            ])));
                                        @endphp
                                        {{ $gwLabel }}
                                        @if ($extra !== '')
                                            <br><small class="text-muted">{{ $extra }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) $p->total, 2, ',', '.') }}</td>
                                    <td><span class="badge {{ $badgeTn }}">{{ $etiTn }}</span></td>
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
                                           href="{{ route('tiendanube_pedido_show', array_merge(['id' => $p->id], $retornoListadoQuery)) }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $puedeFacturar ? 14 : 13 }}" class="text-center text-muted">
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
