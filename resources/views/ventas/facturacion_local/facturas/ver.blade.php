@extends("theme.$theme.layout")

@section('titulo')
    Factura Local — venta {{ $venta->id }}
@endsection

@section('styles')
@include('ventas.facturacion_local.facturas.partials.estilos_acciones_tabla')
@endsection

@section('scripts')
@include('ventas.facturacion_local.facturas.partials.script_generar_nc')
@if (can('cambiar-medio-pago-facturacion-local', false))
    @include('ventas.facturacion_local.facturas.partials.script_cambiar_medio_pago')
@endif
<script>
(function () {
    var btn = document.getElementById('btn-fl-reimprimir');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        var ventaId = btn.getAttribute('data-venta-id');
        var pdfUrl = btn.getAttribute('data-pdf-url') || ('{{ url('ventas/listaunafactura') }}/' + ventaId);
        window.open(pdfUrl, '_blank');
    });
})();
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (! ($turno_abierto ?? null) && ($puede_nc ?? false) === false && ($nc_venta_id ?? null) === null && ! ($es_comprobante_nc ?? false))
            <div class="alert alert-warning py-2 mb-2">
                No hay turno abierto en este local.
                Debe <a href="{{ $url_turnos ?? route('facturacion_local_turnos') }}">abrir un turno</a>
                antes de generar la nota de crédito.
            </div>
        @endif
        @if ($nc_venta_id ?? null)
            <div class="alert alert-info py-2 mb-2 d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <i class="fas fa-undo text-muted mr-1"></i>
                    Este comprobante ya fue revertido por una nota de crédito.
                </span>
                <a href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $nc_venta_id]) }}" class="btn btn-sm btn-outline-info">
                    Ver nota de crédito
                </a>
            </div>
        @elseif ($es_comprobante_nc ?? false)
            <div class="alert alert-secondary py-2 mb-2">
                <i class="fas fa-undo text-muted mr-1"></i>
                Este comprobante es una <strong>nota de crédito</strong>; no se puede generar otra NC sobre él.
            </div>
        @elseif ($motivo_no_cambio_medio ?? null)
            <div class="alert alert-warning py-2 mb-2">
                <i class="fa fa-exchange-alt text-muted mr-1"></i>
                {{ $motivo_no_cambio_medio }}
            </div>
        @endif

        <div class="card card-outline card-primary mb-3">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">{{ $venta->codigo ?? '' }}</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <div class="btn-group btn-group-sm flex-wrap">
                        <a href="{{ route('facturacion_local_facturas') }}" class="btn btn-outline-secondary">Volver al listado</a>
                        @if ($puede_cambiar_medio_pago ?? false)
                            <button type="button"
                                    class="btn btn-outline-warning js-fd-cambiar-medio-pago"
                                    data-venta-id="{{ $venta->id }}"
                                    title="Cambiar cuenta de caja del cobro (sin modificar montos)">
                                <i class="fa fa-exchange-alt"></i> Cambiar medio de pago
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline-dark" id="btn-fl-reimprimir"
                                data-venta-id="{{ $venta->id }}"
                                data-pdf-url="{{ url('ventas/listaunafactura/'.$venta->id) }}">
                            <i class="fas fa-print"></i> Reimprimir PDF
                        </button>
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
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Cliente:</strong> {{ $venta->nombre ?: ($venta->clientes->nombre ?? '—') }}<br>
                        <strong>Fecha:</strong> {{ $venta->fecha ? \Illuminate\Support\Carbon::parse($venta->fecha)->format('d-m-Y') : '—' }}<br>
                        <strong>Hora creación:</strong> {{ $venta->created_at ? $venta->created_at->format('H:i:s') : '—' }}<br>
                        <strong>Total:</strong> {{ number_format((float) $venta->total, 2, ',', '.') }}
                        {{ $venta->monedas->abreviatura ?? '' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Local:</strong> {{ $meta->localVenta?->codigo ?? '—' }} — {{ $meta->localVenta?->nombre ?? '' }}<br>
                        <strong>PV:</strong> {{ $venta->puntoventas->codigo ?? '—' }} — modo {{ $venta->puntoventas->modofacturacion ?? '—' }}<br>
                        <strong>CAE:</strong> {{ $venta->cae ?? '—' }}<br>
                        <strong>Turno:</strong>
                        @if ($meta->turno)
                            #{{ $meta->turno->id }} {{ $meta->turno->turnoLocal?->nombre ?? '' }}
                            ({{ $meta->turno->estado }})
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($cobranzas->isNotEmpty())
        <div class="card card-outline card-success mb-3">
            <div class="card-header py-2">
                <strong>Resumen operativo</strong>
                <span class="small text-muted ml-2">Cobranza de esta venta</span>
            </div>
            <div class="card-body py-2">
                <h6 class="mb-1">Cobranzas ({{ $cobranzas->count() }})</h6>
                <ul class="list-unstyled small mb-0">
                    @foreach ($cobranzas as $cob)
                        <li>
                            <a href="#tab-cobranzas" class="js-fl-tab-link">#{{ $cob->id }}</a>
                            — {{ number_format((float) $cob->monto, 2, ',', '.') }}
                            <span class="text-muted">{{ $cob->estado ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
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
                    @include('ventas.facturacion_local.facturas.partials.tabla_items')
                    @if ($cobranzas->isNotEmpty())
                        <h6 class="mt-3 mb-2">Cuentas de caja utilizadas</h6>
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
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
                                            <td>@include('ventas.facturacion_local.facturas.partials.link_cuentacaja', [
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

                <div class="tab-pane fade" id="tab-cobranzas">
                    @if ($cobranzas->isEmpty())
                        <p class="text-muted mb-0">Sin cobranzas registradas para esta venta.</p>
                    @else
                        <table class="table table-sm table-striped">
                            <thead style="background:#85C1E9;color:#17202A;">
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
                                                            @include('ventas.facturacion_local.facturas.partials.link_cuentacaja', [
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
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-contable">
                    @php
                        $asientoVenta = $venta->asientos;
                        $asientos = $asientoVenta ? collect([$asientoVenta]) : collect();
                    @endphp
                    @if ($asientos->isEmpty())
                        <p class="text-muted mb-0">Sin asiento contable asociado.</p>
                    @else
                        @foreach ($asientos as $asiento)
                            <h6 class="mb-2">Asiento #{{ $asiento->id }}</h6>
                            <table class="table table-sm table-bordered mb-3">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Cuenta</th>
                                        <th class="text-right">Debe</th>
                                        <th class="text-right">Haber</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($asiento->asiento_movimientos ?? [] as $mov)
                                        @php $monto = (float) ($mov->monto ?? 0); @endphp
                                        <tr>
                                            <td>{{ $mov->cuentacontables->codigo ?? '' }} — {{ $mov->cuentacontables->nombre ?? '' }}</td>
                                            <td class="text-right">{{ $monto > 0 ? number_format($monto, 2, ',', '.') : '' }}</td>
                                            <td class="text-right">{{ $monto < 0 ? number_format(abs($monto), 2, ',', '.') : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('ventas.facturacion_local.facturas.partials.modal_generar_nc')
@if (can('cambiar-medio-pago-facturacion-local', false))
    @include('ventas.facturacion_local.facturas.partials.modal_cambiar_medio_pago')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@endsection
