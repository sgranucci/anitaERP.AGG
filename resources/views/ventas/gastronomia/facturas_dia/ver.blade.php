@extends("theme.$theme.layout")

@section('titulo')
    Factura gastronomía — venta {{ $venta->id }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-outline card-primary mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <span>{{ $venta->codigo ?? '' }}</span>
                <div class="btn-group btn-group-sm mt-1 mt-md-0">
                    <a href="{{ route('gastronomia_facturas_dia') }}" class="btn btn-outline-secondary">Volver al listado</a>
                    @if (can('editar-factura', false))
                        <a href="{{ route('editar_factura', ['id' => $venta->id, 'origen' => 'gastronomia_facturas_dia']) }}" class="btn btn-outline-warning">Editar comprobante</a>
                    @endif
                    <button type="button" class="btn btn-outline-dark" id="btn-reimprimir-ticket" data-venta-id="{{ $venta->id }}">
                        <i class="fas fa-receipt"></i> Reimprimir ticket
                    </button>
                    <a href="{{ url('ventas/listaunafactura/'.$venta->id) }}" target="_blank" class="btn btn-outline-primary">PDF / QR ARCA</a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Cliente:</strong> {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreReceptorFactura($venta) }}<br>
                        <strong>Fecha:</strong> {{ $venta->fecha ? \Illuminate\Support\Carbon::parse($venta->fecha)->format('d-m-Y') : '—' }}<br>
                        <strong>Hora creación:</strong> {{ $venta->created_at ? $venta->created_at->format('H:i:s') : '—' }}<br>
                        <strong>Total:</strong> {{ number_format((float) $venta->total, 2, ',', '.') }}
                        {{ $venta->monedas->abreviatura ?? '' }}
                    </div>
                    <div class="col-md-6">
                        <strong>PV:</strong> {{ $venta->puntoventas->codigo ?? '—' }} — modo {{ $venta->puntoventas->modofacturacion ?? '—' }}<br>
                        <strong>Cuenta gastronomía:</strong> {{ $meta->cuenta_gastronomia_id ?? '—' }}<br>
                        <strong>PC emisión:</strong> {{ $meta->identificador_pc }}<br>
                        @if ($depositoVentaConfig)
                            <strong>Depósito artículos facturados:</strong>
                            {{ $depositoVentaConfig->codigo }} — {{ $depositoVentaConfig->nombre }}<br>
                        @endif
                        @if ($depositoInsumosConfig)
                            <strong>Depósito descuento insumos:</strong>
                            {{ $depositoInsumosConfig->codigo }} — {{ $depositoInsumosConfig->nombre }}
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($cobranzas->isNotEmpty() || $movimientosInsumos->isNotEmpty())
        <div class="card card-outline card-success mb-3">
            <div class="card-header py-2">
                <strong>Resumen operativo</strong>
                <span class="small text-muted ml-2">Cobranza e insumos de esta venta</span>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-5 mb-2 mb-md-0">
                        <h6 class="mb-1">Cobranzas ({{ $cobranzas->count() }})</h6>
                        @if ($cobranzas->isEmpty())
                            <p class="text-muted small mb-0">Sin cobranzas.</p>
                        @else
                            <ul class="list-unstyled small mb-0">
                                @foreach ($cobranzas as $cob)
                                    <li>
                                        <a href="#tab-cobranzas" class="js-gastro-tab-link">#{{ $cob->id }}</a>
                                        — {{ number_format((float) $cob->monto, 2, ',', '.') }}
                                        <span class="text-muted">{{ $cob->estado ?? '' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <h6 class="mb-1">Insumos descontados ({{ $movimientosInsumos->count() }})</h6>
                        @if ($movimientosInsumos->isEmpty())
                            <p class="text-muted small mb-0">Sin movimientos de insumos.</p>
                        @else
                            <div class="table-responsive" style="max-height: 180px;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>SKU ítem</th>
                                            <th>SKU insumo</th>
                                            <th>Insumo</th>
                                            <th class="text-right">Cant.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($movimientosInsumos->take(8) as $mov)
                                            <tr>
                                                <td>@include('ventas.gastronomia.facturas_dia.partials.item_facturado_desde_movimiento', ['movimiento' => $mov])</td>
                                                <td>@include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->articulo_id])</td>
                                                <td>{{ $mov->articulos->descripcion ?? '—' }}</td>
                                                <td class="text-right">{{ number_format((float) $mov->cantidad, 3, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($movimientosInsumos->count() > 8)
                                <p class="small text-muted mb-0 mt-1">
                                    y {{ $movimientosInsumos->count() - 8 }} más —
                                    <a href="#tab-insumos" class="js-gastro-tab-link">ver todos</a>
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-detalle">Ítems facturados</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-insumos">
                            Insumos descontados
                            @if ($movimientosInsumos->isNotEmpty())
                                <span class="badge badge-secondary">{{ $movimientosInsumos->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-cobranzas">
                            Cobranzas
                            @if ($cobranzas->isNotEmpty())
                                <span class="badge badge-success">{{ $cobranzas->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-contable">Asiento</a>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade show active" id="tab-detalle">
                    <p class="small text-muted">Productos y servicios incluidos en el comprobante fiscal. Expandir para ver insumos descontados por ítem.</p>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th style="width:2rem;"></th>
                                <th>SKU</th>
                                <th>Detalle</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($itemsConInsumos as $item)
                                @php
                                    $tieneInsumos = $item->insumos->isNotEmpty();
                                    $resaltarItem = ($articulo_filtro_id ?? 0) > 0
                                        && (int) $item->articulo_id === (int) $articulo_filtro_id;
                                    $expandirItem = $resaltarItem && $tieneInsumos;
                                @endphp
                                <tr class="{{ $tieneInsumos ? 'js-gastro-item-row' : '' }}{{ $resaltarItem ? ' table-info' : '' }}"
                                    @if($tieneInsumos) data-target="insumos-item-{{ $item->venta_emision_id }}" style="cursor:pointer;" @endif>
                                    <td class="text-center align-middle">
                                        @if ($tieneInsumos)
                                            <i class="fa {{ $expandirItem ? 'fa-chevron-down' : 'fa-chevron-right' }} js-gastro-item-toggle text-muted" aria-hidden="true"></i>
                                        @endif
                                    </td>
                                    <td>@include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', ['sku' => $item->sku, 'articuloId' => $item->articulo_id])</td>
                                    <td>
                                        {{ $item->detalle }}
                                        @if ($tieneInsumos)
                                            <span class="badge badge-light ml-1">{{ $item->insumos->count() }} insumo(s)</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($item->cantidad, 3, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($item->precio, 2, ',', '.') }}</td>
                                </tr>
                                @if ($tieneInsumos)
                                    <tr id="insumos-item-{{ $item->venta_emision_id }}" class="{{ $expandirItem ? '' : 'd-none' }} bg-light">
                                        <td></td>
                                        <td colspan="4" class="py-2">
                                            <p class="small mb-2">
                                                <strong>Ítem facturado:</strong>
                                                @include('ventas.gastronomia.facturas_dia.partials.item_facturado_insumos', [
                                                    'sku' => $item->sku,
                                                    'articuloId' => $item->articulo_id,
                                                    'detalle' => $item->detalle,
                                                ])
                                            </p>
                                            <table class="table table-sm table-bordered mb-0 small">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>SKU insumo</th>
                                                        <th>Descripción insumo</th>
                                                        <th class="text-right">Cant. descontada</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($item->insumos as $mov)
                                                        <tr>
                                                            <td>@include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->articulo_id])</td>
                                                            <td>{{ $mov->articulos->descripcion ?? '—' }}</td>
                                                            <td class="text-right">{{ number_format((float) $mov->cantidad, 3, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-muted">Sin ítems de emisión.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($cobranzas->isNotEmpty())
                        <h6 class="mt-3 mb-2">Cuentas de caja utilizadas</h6>
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cuenta</th>
                                    <th class="text-right">Monto</th>
                                    <th>Moneda</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cobranzas as $cob)
                                    @foreach ($cobranzaMedios[$cob->id] ?? [] as $med)
                                        <tr>
                                            <td>@include('ventas.gastronomia.facturas_dia.partials.link_cuentacaja', [
                                                'cuentacajaId' => $med->cuentacaja_id,
                                                'codigo' => $med->codigo,
                                                'nombre' => $med->nombre,
                                                'cuenta' => $med->cuenta,
                                            ])</td>
                                            <td class="text-right">{{ number_format($med->monto, 2, ',', '.') }}</td>
                                            <td>{{ $med->moneda }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-insumos">
                    <p class="small text-muted mb-2">
                        Movimientos de stock al facturar (fórmulas / recetas). Cantidad negativa = salida del depósito.
                        @if ($depositoInsumosConfig)
                            Depósito insumos (PV): <strong>{{ $depositoInsumosConfig->codigo }} — {{ $depositoInsumosConfig->nombre }}</strong>.
                        @endif
                    </p>
                    @if ($movimientosInsumos->isEmpty())
                        <p class="text-muted mb-0">No hay insumos descontados para esta venta.</p>
                    @else
                        @foreach ($insumosPorDeposito as $grupo)
                            <div class="mb-3">
                                <h6 class="mb-2">
                                    Depósito: {{ $grupo->deposito_codigo }} — {{ $grupo->deposito_nombre }}
                                    <span class="text-muted small">(id {{ $grupo->deposito_id }})</span>
                                </h6>
                                <table class="table table-sm table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>SKU ítem facturado</th>
                                            <th>SKU insumo</th>
                                            <th>Insumo</th>
                                            <th class="text-right">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($grupo->movimientos as $mov)
                                            <tr>
                                                <td>@include('ventas.gastronomia.facturas_dia.partials.item_facturado_desde_movimiento', ['movimiento' => $mov])</td>
                                                <td>@include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->articulo_id])</td>
                                                <td>{{ $mov->articulos->descripcion ?? '—' }}</td>
                                                <td class="text-right">{{ number_format((float) $mov->cantidad, 3, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-cobranzas">
                    @if ($cobranzas->isEmpty())
                        <p class="text-muted mb-0">Sin cobranzas registradas para esta venta.</p>
                    @else
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Estado</th>
                                    <th class="text-right">Monto</th>
                                    <th>Medios de cobro</th>
                                    <th>Detalle</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cobranzas as $cob)
                                    @php $medios = $cobranzaMedios[$cob->id] ?? []; @endphp
                                    <tr>
                                        <td>{{ $cob->id }}</td>
                                        <td>{{ $cob->estado ?? '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $cob->monto, 2, ',', '.') }}</td>
                                        <td>
                                            @if ($medios === [])
                                                <small class="text-muted">—</small>
                                            @else
                                                <ul class="list-unstyled mb-0 small">
                                                    @foreach ($medios as $med)
                                                        <li>
                                                            @include('ventas.gastronomia.facturas_dia.partials.link_cuentacaja', [
                                                                'cuentacajaId' => $med->cuentacaja_id,
                                                                'codigo' => $med->codigo,
                                                                'nombre' => $med->nombre,
                                                                'cuenta' => $med->cuenta,
                                                            ])
                                                            — {{ number_format($med->monto, 2, ',', '.') }} {{ $med->moneda }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td><small>{{ $cob->detalle ?? '' }}</small></td>
                                        <td>
                                            @if (can('listar-cobranza', false))
                                                <a href="{{ route('listar_una_cobranza', ['id' => $cob->id]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Ver comprobante de cobranza (PDF)">
                                                    <i class="fa fa-print"></i> Ver
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-contable">
                    @php $asientos = $venta->asientos ?? collect(); @endphp
                    @if ($asientos->isEmpty())
                        <p class="text-muted mb-0">Sin asientos asociados.</p>
                    @else
                        @foreach ($asientos as $as)
                            <div class="mb-3">
                                <strong>Asiento {{ $as->id }}</strong>
                                <table class="table table-sm table-bordered mt-1">
                                    <thead><tr><th>Cuenta</th><th>Monto</th><th>Obs.</th></tr></thead>
                                    <tbody>
                                        @foreach ($as->asiento_movimientos as $mov)
                                            <tr>
                                                <td>{{ $mov->cuentacontables->codigo ?? '' }} {{ $mov->cuentacontables->nombre ?? '' }}</td>
                                                <td>{{ number_format((float) ($mov->monto ?? 0), 2, ',', '.') }}</td>
                                                <td>{{ $mov->observacion ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    function activarTab(hash) {
        if (hash && document.querySelector('a.nav-link[href="' + hash + '"]')) {
            $('a.nav-link[href="' + hash + '"]').tab('show');
        }
    }
    var hash = window.location.hash || '';
    if (!hash && {{ (int) ($articulo_filtro_id ?? 0) }} > 0) {
        hash = '#tab-detalle';
    }
    activarTab(hash);
    document.querySelectorAll('.js-gastro-tab-link').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var hash = el.getAttribute('href');
            if (hash) {
                window.location.hash = hash;
                activarTab(hash);
            }
        });
    });
    document.querySelectorAll('.js-gastro-item-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var targetId = row.getAttribute('data-target');
            if (!targetId) return;
            var detail = document.getElementById(targetId);
            var icon = row.querySelector('.js-gastro-item-toggle');
            if (!detail) return;
            detail.classList.toggle('d-none');
            if (icon) {
                icon.classList.toggle('fa-chevron-right');
                icon.classList.toggle('fa-chevron-down');
            }
        });
    });

    var btnTicket = document.getElementById('btn-reimprimir-ticket');
    if (btnTicket) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
        btnTicket.addEventListener('click', function () {
            var ventaId = btnTicket.getAttribute('data-venta-id');
            if (!ventaId || btnTicket.disabled) return;
            btnTicket.disabled = true;
            fetch('{{ route('gastronomia_facturas_dia_reimprimir_ticket', ['ventaId' => $venta->id]) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    if (res.ok && res.body.ok) {
                        if (typeof toastr !== 'undefined') toastr.success(res.body.mensaje || 'Ticket enviado.');
                        else alert(res.body.mensaje || 'Ticket enviado.');
                    } else {
                        var msg = (res.body && (res.body.error || res.body.mensaje)) || 'Error al reimprimir.';
                        if (typeof toastr !== 'undefined') toastr.error(msg);
                        else alert(msg);
                    }
                })
                .catch(function () {
                    if (typeof toastr !== 'undefined') toastr.error('Error de comunicación.');
                    else alert('Error de comunicación.');
                })
                .finally(function () { btnTicket.disabled = false; });
        });
    }
})();
</script>
@endsection
