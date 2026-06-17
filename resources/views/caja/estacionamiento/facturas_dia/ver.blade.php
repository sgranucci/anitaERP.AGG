@extends("theme.$theme.layout")

@section('titulo')
    Factura estacionamiento — venta {{ $venta->id }}
@endsection

@section('styles')
@include('caja.estacionamiento.facturas_dia.partials.estilos_acciones_tabla')
<style>
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
                    <button type="button" class="btn btn-outline-dark" id="btn-reimprimir-ticket" data-venta-id="{{ $venta->id }}">
                        <i class="fas fa-receipt"></i> Reimprimir ticket
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
                    </div>
                </div>
            </div>
        </div>

        @include('caja.estacionamiento.facturas_dia.partials.panel_estacionamiento_comandas')

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
                            <a href="#tab-cobranzas" class="js-est-tab-link">#{{ $cob->id }}</a>
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
                    @include('caja.estacionamiento.facturas_dia.partials.tabla_items_facturados')
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
    if (!hash && {{ (int) ($item_filtro_id ?? 0) }} > 0) {
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

    var btnTicket = document.getElementById('btn-reimprimir-ticket');
    if (btnTicket) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
        btnTicket.addEventListener('click', function () {
            var ventaId = btnTicket.getAttribute('data-venta-id');
            if (!ventaId || btnTicket.disabled) return;
            btnTicket.disabled = true;
            fetch('{{ route('estacionamiento_facturas_dia_reimprimir_ticket', ['ventaId' => $venta->id]) }}', {
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
