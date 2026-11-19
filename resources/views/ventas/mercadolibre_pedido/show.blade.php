@extends("theme.$theme.layout")

@section('titulo')
    Pedido MeLi {{ $pedido->order_id }}
@endsection

@section('contenido')
<div class="card card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Orden {{ $pedido->order_id }}</h3>
        <a href="{{ route('mercadolibre_pedidos') }}" class="btn btn-outline-light btn-sm">
            <i class="fa fa-reply-all"></i> Volver
        </a>
    </div>
    <div class="card-body">
        @include('includes.mensaje')

        <div class="row mb-3">
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Estado ERP</dt>
                    <dd class="col-sm-8">{{ $estados[$pedido->estado_erp] ?? $pedido->estado_erp }}</dd>
                    <dt class="col-sm-4">Status ML</dt>
                    <dd class="col-sm-8">{{ $pedido->status }}</dd>
                    <dt class="col-sm-4">Pack</dt>
                    <dd class="col-sm-8">{{ $pedido->pack_id ?: '—' }}</dd>
                    <dt class="col-sm-4">Full</dt>
                    <dd class="col-sm-8">{{ $pedido->es_full ? 'Sí' : 'No' }}</dd>
                    <dt class="col-sm-4">Pagado</dt>
                    <dd class="col-sm-8">{{ optional($pedido->paid_at)->format('d/m/Y H:i') }}</dd>
                    <dt class="col-sm-4">Total</dt>
                    <dd class="col-sm-8">{{ number_format((float) $pedido->total, 2, ',', '.') }} {{ $pedido->currency }}</dd>
                    <dt class="col-sm-4">Payment MP</dt>
                    <dd class="col-sm-8">{{ $pedido->payment_id ?: '—' }}</dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Comprador</dt>
                    <dd class="col-sm-8">{{ $pedido->buyer_name ?: $pedido->buyer_nickname }}</dd>
                    <dt class="col-sm-4">Documento</dt>
                    <dd class="col-sm-8">{{ $pedido->buyer_doc_type }} {{ $pedido->buyer_doc }}</dd>
                    <dt class="col-sm-4">Letra</dt>
                    <dd class="col-sm-8">{{ $pedido->letra_sugerida }}</dd>
                    <dt class="col-sm-4">PV sugerido</dt>
                    <dd class="col-sm-8">{{ optional($pedido->puntoventaSugerido)->codigo }} {{ optional($pedido->puntoventaSugerido)->nombre }}</dd>
                    <dt class="col-sm-4">Depósito</dt>
                    <dd class="col-sm-8">{{ optional($pedido->depositoSugerido)->codigo }} {{ optional($pedido->depositoSugerido)->nombre }}</dd>
                    <dt class="col-sm-4">Venta</dt>
                    <dd class="col-sm-8">
                        @if ($pedido->venta_id)
                            #{{ $pedido->venta_id }} {{ optional($pedido->venta)->codigo }} CAE {{ optional($pedido->venta)->cae }}
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">PDF en ML</dt>
                    <dd class="col-sm-8">{{ optional($pedido->fiscal_publicado_at)->format('d/m/Y H:i') ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        @if ($pedido->error_mensaje)
            <div class="alert alert-danger">{{ $pedido->error_mensaje }}</div>
        @endif

        <div class="mb-3">
            @if ($puedeSync)
                <form method="post" action="{{ route('mercadolibre_pedido_refrescar', $pedido->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-info btn-sm">Refrescar desde ML</button>
                </form>
            @endif
            @if ($puedeFacturar && ! $pedido->estaFacturado() && $pedido->estado_erp !== 'cancelado')
                <form method="post" action="{{ route('mercadolibre_pedido_facturar', $pedido->id) }}" class="d-inline"
                      onsubmit="return confirm('¿Emitir factura {{ $pedido->letra_sugerida }}? Si hay pack, se facturan todas las órdenes del pack juntas.');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Facturar</button>
                </form>
            @endif
        </div>
        @if ($pedido->pack_id)
            @php
                $hermanos = \App\Models\Ventas\MercadolibrePedido::query()
                    ->where('seller_id', $pedido->seller_id)
                    ->where('pack_id', $pedido->pack_id)
                    ->orderBy('id')
                    ->get(['id','order_id','total','estado_erp','venta_id']);
            @endphp
            @if ($hermanos->count() > 1)
                <div class="alert alert-info py-2">
                    Pack {{ $pedido->pack_id }}: {{ $hermanos->count() }} órdenes → una sola factura.
                    @foreach ($hermanos as $h)
                        <a href="{{ route('mercadolibre_pedido_show', $h->id) }}">#{{ $h->order_id }}</a>
                        ({{ number_format((float)$h->total, 2, ',', '.') }})@if (! $loop->last), @endif
                    @endforeach
                </div>
            @endif
        @endif

        <h5>Líneas</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>SKU</th>
                        <th>Item</th>
                        <th>Nombre</th>
                        <th class="text-right">Cant.</th>
                        <th class="text-right">Precio</th>
                        <th>Artículo ERP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedido->lineas as $l)
                        <tr>
                            <td>{{ $l->seller_sku }}</td>
                            <td>{{ $l->item_id }}</td>
                            <td>{{ $l->nombre }}</td>
                            <td class="text-right">{{ number_format((float) $l->quantity, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $l->unit_price, 2, ',', '.') }}</td>
                            <td>
                                @if ($l->articulo_id)
                                    {{ optional($l->articulo)->sku }} — {{ optional($l->articulo)->descripcion }}
                                @else
                                    <span class="text-danger">Sin match</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
