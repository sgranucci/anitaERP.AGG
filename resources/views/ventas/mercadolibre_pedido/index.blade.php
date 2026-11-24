@extends("theme.$theme.layout")

@section('titulo')
    Pedidos Mercado Libre
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/mercadolibre_pedido/index.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/mercadolibre_pedido/index.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ml-proceso-overlay',
    'tituloId' => 'ml-proceso-titulo',
    'subtituloId' => 'ml-proceso-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Puede demorar. No cierre la página.',
])

@php
    $listosPorId = $listosPorId ?? [];
    $puedeFacturar = $puedeFacturar ?? false;
    $cantListosPagina = collect($listosPorId)->filter()->count();
@endphp

<div class="card card-info">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="card-title mb-0">Pedidos Mercado Libre</h3>
        <div class="card-tools">
            <a href="{{ route('editar_configuracion_mercadolibre') }}" class="btn btn-outline-light btn-sm">
                Configuración
            </a>
        </div>
    </div>
    <div class="card-body">
        @include('includes.mensaje')

        <div class="mb-3">
            @if (! $appOk)
                <div class="alert alert-warning mb-2">Falta App ID / Client Secret en .env (MERCADOLIBRE_*).</div>
            @elseif (! $apiOk)
                <div class="alert alert-warning mb-2">
                    App cargada pero sin autorizar.
                    @if ($puedeAuth)
                        <a href="{{ route('mercadolibre_oauth_autorizar') }}" class="alert-link">Autorizar con FERLIFRAGOLA</a>
                    @endif
                </div>
            @else
                <div class="alert alert-success mb-2 py-2">
                    OAuth OK
                    @if ($oauth['seller_id'] ?? null)
                        — seller {{ $oauth['seller_id'] }}
                    @endif
                    @if ($oauth['expires_at'] ?? null)
                        — vence {{ $oauth['expires_at'] }}
                    @endif
                </div>
            @endif
        </div>

        <form method="get" action="{{ route('mercadolibre_pedidos') }}" class="mb-3" id="form-ml-filtros">
            <input type="hidden" name="consultar" value="1">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2">
                    <label class="control-label">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="{{ $filtros['desde'] ?? '' }}">
                </div>
                <div class="form-group col-md-2">
                    <label class="control-label">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="{{ $filtros['hasta'] ?? '' }}">
                </div>
                <div class="form-group col-md-2">
                    <label class="control-label">Estado ERP</label>
                    <select name="estado_erp" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($estados as $k => $et)
                            <option value="{{ $k }}" @if (($filtros['estado_erp'] ?? '') === $k) selected @endif>{{ $et }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label class="control-label">Texto / order / MP</label>
                    <input type="text" name="texto" class="form-control form-control-sm" value="{{ $filtros['texto'] ?? '' }}">
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-info btn-sm">Consultar</button>
                    @if ($puedeSync)
                        <button type="submit" form="form-ml-sync" class="btn btn-primary btn-sm" id="btn-ml-sync">
                            Sincronizar
                        </button>
                    @endif
                </div>
            </div>
        </form>

        @if ($puedeSync)
            <form id="form-ml-sync" method="post" action="{{ route('mercadolibre_pedidos_sincronizar') }}" class="d-none">
                @csrf
                <input type="hidden" name="desde" value="{{ $filtros['desde'] ?? '' }}">
                <input type="hidden" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
            </form>
        @endif

        <p class="text-muted small mb-3">
            <strong>Consultar</strong> filtra pedidos ya guardados en anitaERP.
            <strong>Sincronizar</strong> baja/actualiza órdenes pagadas desde Mercado Libre.
            <strong>Facturar seleccionados</strong> solo toma pedidos listos (SKU, PV/depósito/medio y datos fiscales OK).
            Órdenes del mismo pack se agrupan en una sola factura.
        </p>

        @if ($puedeFacturar)
            <form method="POST" action="{{ route('mercadolibre_pedidos_facturar_masivo') }}" id="form-ml-masivo">
                @csrf
                <input type="hidden" name="desde" value="{{ $filtros['desde'] ?? '' }}">
                <input type="hidden" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
                <input type="hidden" name="estado_erp" value="{{ $filtros['estado_erp'] ?? '' }}">
                <input type="hidden" name="texto" value="{{ $filtros['texto'] ?? '' }}">
                <div class="mb-2">
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-ml-masivo"
                            @if ($cantListosPagina === 0) disabled @endif>
                        <i class="fa fa-file-invoice"></i> Facturar seleccionados
                        <span class="badge badge-light" id="ml-masivo-count">0</span>
                    </button>
                    <span class="text-muted small ml-2">
                        Listos en esta página: {{ $cantListosPagina }} (máx. 30 facturas por corrida)
                    </span>
                </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        @if ($puedeFacturar)
                            <th style="width:36px;">
                                <input type="checkbox" id="ml-check-all-listos" title="Marcar todos los listos">
                            </th>
                        @endif
                        <th>Order</th>
                        <th>Pack</th>
                        <th>Pagado</th>
                        <th>Comprador</th>
                        <th>Doc</th>
                        <th>Letra</th>
                        <th class="text-right">Total</th>
                        <th>MP</th>
                        <th>Estado</th>
                        <th>Full</th>
                        <th>Venta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($coleccion as $p)
                        @php
                            $esListo = ! empty($listosPorId[(int) $p->id]);
                        @endphp
                        <tr class="@if ($esListo) table-success @endif">
                            @if ($puedeFacturar)
                                <td class="text-center">
                                    @if ($esListo)
                                        <input type="checkbox" class="ml-check-pedido" name="pedido_ids[]"
                                               value="{{ $p->id }}">
                                    @endif
                                </td>
                            @endif
                            <td>{{ $p->order_id }}</td>
                            <td>{{ $p->pack_id ?: '—' }}</td>
                            <td>{{ optional($p->paid_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $p->buyer_name ?: $p->buyer_nickname }}</td>
                            <td>{{ $p->buyer_doc_type }} {{ $p->buyer_doc }}</td>
                            <td>{{ $p->letra_sugerida }}</td>
                            <td class="text-right">{{ number_format((float) $p->total, 2, ',', '.') }}</td>
                            <td>{{ $p->payment_id }}</td>
                            <td>{{ $estados[$p->estado_erp] ?? $p->estado_erp }}</td>
                            <td>{{ $p->es_full ? 'Sí' : 'No' }}</td>
                            <td>
                                @if ($p->venta_id)
                                    {{ $p->venta->codigo ?? $p->venta_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <a class="btn-accion-tabla" href="{{ route('mercadolibre_pedido_show', $p->id) }}" title="Ver">
                                    <i class="fa fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $puedeFacturar ? 13 : 12 }}" class="text-center text-muted">Sin pedidos en el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($puedeFacturar)
            </form>
        @endif

        {{ $coleccion->appends($filtrosQuery)->links() }}
    </div>
</div>
@endsection
