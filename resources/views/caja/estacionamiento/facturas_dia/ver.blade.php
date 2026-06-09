@extends("theme.$theme.layout")

@section('titulo')
    Factura estacionamiento — venta {{ $venta->id }}
@endsection

@section('styles')
@include('caja.estacionamiento.facturas_dia.partials.estilos_acciones_tabla')
<style>
    .est-resumen-insumos-scroll {
        max-height: 180px;
        overflow-y: scroll;
        overflow-x: hidden;
        scrollbar-gutter: stable;
        padding-right: 18px;
        box-sizing: border-box;
    }
    .est-resumen-insumos-scroll table {
        width: 100%;
        margin-bottom: 0;
        table-layout: fixed;
    }
    .est-resumen-insumos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .est-resumen-insumos-scroll th:nth-child(1),
    .est-resumen-insumos-scroll td:nth-child(1) {
        width: 42%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .est-resumen-insumos-scroll th:nth-child(2),
    .est-resumen-insumos-scroll td:nth-child(2) {
        width: 13%;
        white-space: nowrap;
    }
    .est-resumen-insumos-scroll th:nth-child(3),
    .est-resumen-insumos-scroll td:nth-child(3) {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .est-resumen-insumos-scroll .col-cant-insumo {
        width: 7rem;
        min-width: 7rem;
        max-width: 7rem;
        padding-left: 0.35rem;
        padding-right: 0.35rem !important;
        white-space: nowrap;
        box-sizing: border-box;
    }
    .est-insumos-grid {
        table-layout: fixed;
        width: 100%;
    }
    .est-insumos-grid th:nth-child(1),
    .est-insumos-grid td:nth-child(1) {
        width: 42%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .est-insumos-grid th:nth-child(2),
    .est-insumos-grid td:nth-child(2) {
        width: 13%;
        white-space: nowrap;
    }
    .est-insumos-grid th:nth-child(3),
    .est-insumos-grid td:nth-child(3) {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .est-insumos-grid th:nth-child(4),
    .est-insumos-grid td:nth-child(4) {
        width: 7rem;
        min-width: 7rem;
        max-width: 7rem;
        white-space: nowrap;
    }
    .est-estacionamiento-comandas-grid {
        table-layout: fixed;
        width: 100%;
    }
    .est-col-monto,
    th.est-col-monto,
    td.est-col-monto {
        min-width: 6.85rem;
        max-width: 9.5rem;
        white-space: nowrap;
        text-align: right !important;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
    }
    .est-estacionamiento-comandas-grid th:nth-child(1),
    .est-estacionamiento-comandas-grid td:nth-child(1) {
        width: 6.5rem;
    }
    .est-estacionamiento-comandas-grid th:nth-child(3),
    .est-estacionamiento-comandas-grid td:nth-child(3) {
        width: 9.5rem;
    }
</style>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (($requiere_habilitacion_turno ?? false) && ! ($turno_habilitado ?? false) && ($puede_nc ?? false) === false && ($nc_venta_id ?? null) === null && ! ($es_comprobante_nc ?? false))
            <div class="alert alert-warning py-2 mb-2">
                No hay turno habilitado en esta terminal (<strong>{{ $identificador_pc ?? '' }}</strong>).
                Debe <a href="{{ $url_habilitacion_turno ?? route('estacionamiento_habilitacion_turno') }}">habilitar el turno</a>
                antes de generar la nota de crédito desde este comprobante.
            </div>
        @endif
        @if ($nc_venta_id ?? null)
            <div class="alert alert-info py-2 mb-2 d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <i class="fas fa-undo text-muted mr-1"></i>
                    Este comprobante ya fue revertido por una nota de crédito.
                </span>
                <a href="{{ route('estacionamiento_facturas_dia_ver', ['ventaId' => $nc_venta_id]) }}" class="btn btn-sm btn-outline-info">
                    Ver nota de crédito
                </a>
            </div>
        @elseif ($es_comprobante_nc ?? false)
            <div class="alert alert-secondary py-2 mb-2">
                <i class="fas fa-undo text-muted mr-1"></i>
                Este comprobante es una <strong>nota de crédito</strong>; no se puede generar otra NC sobre él.
            </div>
        @endif

        <div class="card card-outline card-primary mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <span>{{ $venta->codigo ?? '' }}</span>
                <div class="btn-group btn-group-sm mt-1 mt-md-0 flex-wrap">
                    <a href="{{ route('estacionamiento_facturas_dia') }}" class="btn btn-outline-secondary">Volver al listado</a>
                    @if ($puede_cambiar_medio_pago ?? false)
                        <button type="button"
                                class="btn btn-outline-warning js-fd-cambiar-medio-pago"
                                data-venta-id="{{ $venta->id }}"
                                title="Cambiar cuenta de caja del cobro (sin modificar montos)">
                            <i class="fa fa-exchange-alt"></i> Cambiar medio de pago
                        </button>
                    @endif
                    @if ($puede_nc ?? false)
                        <button type="button"
                                class="btn btn-outline-warning js-fd-generar-nc"
                                data-venta-id="{{ $venta->id }}"
                                data-codigo="{{ $venta->codigo ?? '' }}"
                                title="Revertir este comprobante emitiendo una nota de crédito">
                            <i class="fas fa-undo"></i> Generar nota de crédito
                        </button>
                    @endif
                    <a href="{{ url('ventas/listaunafactura/'.$venta->id) }}" target="_blank" class="btn btn-outline-primary">PDF / QR ARCA</a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Cliente:</strong> {{ \App\Support\Caja\Estacionamiento\EstacionamientoVentaDisplaySupport::nombreReceptorFactura($venta) }}<br>
                        <strong>Fecha:</strong> {{ $venta->fecha ? \Illuminate\Support\Carbon::parse($venta->fecha)->format('d-m-Y') : '—' }}<br>
                        <strong>Hora creación:</strong> {{ $venta->created_at ? $venta->created_at->format('H:i:s') : '—' }}<br>
                        <strong>Total:</strong> {{ number_format((float) $venta->total, 2, ',', '.') }}
                        {{ $venta->monedas->abreviatura ?? '' }}
                    </div>
                    <div class="col-md-6">
                        <strong>PV:</strong> {{ $venta->puntoventas->codigo ?? '—' }} — modo {{ $venta->puntoventas->modofacturacion ?? '—' }}<br>
                        <strong>Ticket:</strong> {{ $meta->ticket?->numero_ticket ?? '—' }}<br>
                        @php $patenteTicket = \App\Support\Caja\Estacionamiento\EstacionamientoVentaDisplaySupport::estacionamientoDisplayId($venta); @endphp
                        @if ($patenteTicket !== null)
                            <strong>Patente:</strong>
                            <span class="font-weight-bold text-primary">{{ $patenteTicket }}</span><br>
                        @endif
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

        @include('caja.estacionamiento.facturas_dia.partials.panel_estacionamiento_comandas')

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
                                        <a href="#tab-cobranzas" class="js-est-tab-link">#{{ $cob->id }}</a>
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
                            <div class="est-resumen-insumos-scroll">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>SKU ítem</th>
                                            <th>SKU insumo</th>
                                            <th>Insumo</th>
                                            <th class="text-right col-cant-insumo">Cant.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($movimientosInsumos->take(8) as $mov)
                                            <tr>
                                                <td>@include('caja.estacionamiento.facturas_dia.partials.item_facturado_desde_movimiento', ['movimiento' => $mov])</td>
                                                <td>@include('caja.estacionamiento.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->item_id])</td>
                                                <td>{{ $mov->articulos->descripcion ?? '—' }}</td>
                                                <td class="text-right col-cant-insumo">{{ number_format((float) $mov->cantidad, 3, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($movimientosInsumos->count() > 8)
                                <p class="small text-muted mb-0 mt-1">
                                    y {{ $movimientosInsumos->count() - 8 }} más —
                                    <a href="#tab-insumos" class="js-est-tab-link">ver todos</a>
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
                    @if ($meta->ticket ?? null)
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-estacionamiento-comandas">
                                Ticket estacionamiento
                                <span class="badge badge-info">1</span>
                            </a>
                        </li>
                    @endif
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
                                @if ($puede_ver_formula ?? false)
                                    <th class="text-nowrap" style="width:2rem;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($itemsConInsumos as $item)
                                @php
                                    $tieneInsumos = $item->insumos->isNotEmpty();
                                    $resaltarItem = ($articulo_filtro_id ?? 0) > 0
                                        && (int) $item->item_id === (int) $articulo_filtro_id;
                                    $expandirItem = $resaltarItem && $tieneInsumos;
                                @endphp
                                <tr class="{{ $tieneInsumos ? 'js-est-item-row' : '' }}{{ $resaltarItem ? ' table-info' : '' }}"
                                    @if($tieneInsumos) data-target="insumos-item-{{ $item->venta_emision_id }}" style="cursor:pointer;" @endif>
                                    <td class="text-center align-middle">
                                        @if ($tieneInsumos)
                                            <i class="fa {{ $expandirItem ? 'fa-chevron-down' : 'fa-chevron-right' }} js-est-item-toggle text-muted" aria-hidden="true"></i>
                                        @endif
                                    </td>
                                    <td>@include('caja.estacionamiento.facturas_dia.partials.link_sku_articulo', ['sku' => $item->sku, 'articuloId' => $item->item_id])</td>
                                    <td>
                                        {{ $item->detalle }}
                                        @if ($tieneInsumos)
                                            <span class="badge badge-light ml-1">{{ $item->insumos->count() }} insumo(s)</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($item->cantidad, 3, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($item->precio, 2, ',', '.') }}</td>
                                    @if ($puede_ver_formula ?? false)
                                        <td class="text-nowrap text-center align-middle facturas-dia-tabla-acciones">
                                            @include('includes.btn_formula_articulo', ['articuloId' => $item->item_id])
                                        </td>
                                    @endif
                                </tr>
                                @if ($tieneInsumos)
                                    <tr id="insumos-item-{{ $item->venta_emision_id }}" class="{{ $expandirItem ? '' : 'd-none' }} bg-light">
                                        <td></td>
                                        <td colspan="{{ ($puede_ver_formula ?? false) ? 5 : 4 }}" class="py-2">
                                            <p class="small mb-2">
                                                <strong>Ítem facturado:</strong>
                                                @include('caja.estacionamiento.facturas_dia.partials.item_facturado_insumos', [
                                                    'sku' => $item->sku,
                                                    'articuloId' => $item->item_id,
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
                                                            <td>@include('caja.estacionamiento.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->item_id])</td>
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
                                <tr><td colspan="{{ ($puede_ver_formula ?? false) ? 6 : 5 }}" class="text-muted">Sin ítems de emisión.</td></tr>
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
                                            <td>@include('caja.estacionamiento.facturas_dia.partials.link_cuentacaja', [
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
                                <table class="table table-sm table-bordered est-insumos-grid">
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
                                                <td>@include('caja.estacionamiento.facturas_dia.partials.item_facturado_desde_movimiento', ['movimiento' => $mov])</td>
                                                <td>@include('caja.estacionamiento.facturas_dia.partials.link_sku_articulo', ['sku' => $mov->articulos->sku ?? '—', 'articuloId' => $mov->item_id])</td>
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
                                                            @include('caja.estacionamiento.facturas_dia.partials.link_cuentacaja', [
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
                                        <td class="facturas-dia-tabla-acciones text-nowrap">
                                            @if (can('listar-cobranza', false))
                                                <a href="{{ route('listar_una_cobranza', ['id' => $cob->id]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Ver comprobante de cobranza (PDF)">
                                                    <i class="fa fa-print"></i> Ver
                                                </a>
                                            @endif
                                            @if ($puede_cambiar_medio_pago ?? false)
                                                <button type="button"
                                                        class="btn-accion-tabla tooltipsC js-fd-cambiar-medio-pago"
                                                        data-venta-id="{{ $venta->id }}"
                                                        data-placement="left"
                                                        title="Cambiar medio de pago (monto fijo)">
                                                    <i class="fa fa-exchange-alt text-warning"></i>
                                                </button>
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

                @if ($meta->ticket ?? null)
                    <div class="tab-pane fade" id="tab-estacionamiento-comandas">
                        @include('caja.estacionamiento.facturas_dia.partials.panel_estacionamiento_comandas', ['solo_tabla' => true])
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('caja.estacionamiento.facturas_dia.partials.modal_generar_nc')
@if ($puede_cambiar_medio_pago ?? false)
    @include('caja.estacionamiento.facturas_dia.partials.modal_cambiar_medio_pago')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@endsection

@section('scripts')
@include('caja.estacionamiento.facturas_dia.partials.script_generar_nc')
@if ($puede_ver_formula ?? false)
<script>
    window.FORMULA_ARTICULO_ACCION = {
        urlResolverFormulaBase: @json(url('stock/formula-articulo/resolver-por-articulo')),
        urlFormulaBase: @json(url('stock/formula-articulo')),
        puedeVerFormula: true,
    };
</script>
<script src="{{ asset('assets/pages/scripts/includes/formula_articulo_accion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/formula_articulo_accion.js')) ?: time() }}" type="text/javascript"></script>
@endif
@if ($puede_cambiar_medio_pago ?? false)
    @include('caja.estacionamiento.facturas_dia.partials.script_cambiar_medio_pago')
@endif
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
    document.querySelectorAll('.js-est-tab-link').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var hash = el.getAttribute('href');
            if (hash) {
                window.location.hash = hash;
                activarTab(hash);
            }
        });
    });
    document.querySelectorAll('.js-est-item-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var targetId = row.getAttribute('data-target');
            if (!targetId) return;
            var detail = document.getElementById(targetId);
            var icon = row.querySelector('.js-est-item-toggle');
            if (!detail) return;
            detail.classList.toggle('d-none');
            if (icon) {
                icon.classList.toggle('fa-chevron-right');
                icon.classList.toggle('fa-chevron-down');
            }
        });
    });

})();
</script>
@endsection
