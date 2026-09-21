@extends("theme.$theme.layout")

@section('titulo')
    Facturas Local
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@include('ventas.facturacion_local.facturas.partials.script_generar_nc')
@if (can('cambiar-medio-pago-facturacion-local', false))
    @include('ventas.facturacion_local.facturas.partials.script_cambiar_medio_pago')
@endif
<script>
(function () {
    document.querySelectorAll('.js-fl-reimprimir').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            var pdfUrl = btn.getAttribute('data-pdf-url');
            if (!ventaId || btn.disabled) return;
            btn.disabled = true;
            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            var token = csrfToken ? csrfToken.getAttribute('content') : '';
            fetch('{{ url('ventas/facturacion-local/facturas') }}/' + ventaId + '/reimprimir', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                .then(function (res) {
                    var url = (res.body && res.body.pdf_url) ? res.body.pdf_url : pdfUrl;
                    if (url) {
                        window.open(url, '_blank');
                    } else if (typeof toastr !== 'undefined') {
                        toastr.error((res.body && res.body.error) || 'No se pudo abrir el PDF.');
                    } else {
                        alert((res.body && res.body.error) || 'No se pudo abrir el PDF.');
                    }
                })
                .catch(function () {
                    if (pdfUrl) window.open(pdfUrl, '_blank');
                })
                .finally(function () { btn.disabled = false; });
        });
    });
})();
</script>
@endsection

@section('contenido')
@include('ventas.facturacion_local.facturas.partials.estilos_acciones_tabla')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! ($turno_abierto ?? null) && ($local_id ?? 0) > 0)
            <div class="alert alert-warning py-2 mb-2">
                No hay turno abierto en el local seleccionado.
                Debe <a href="{{ route('facturacion_local_turnos') }}">abrir un turno</a>
                antes de generar notas de crédito desde este listado.
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Facturas Local</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('reportes-facturacion-local', false))
                        <a href="{{ route('facturacion_local_reportes') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-chart-bar"></i> Reportes
                        </a>
                    @endif
                    @if (can('usar-facturacion-local', false))
                        <a href="{{ route('facturacion_local_pos') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-cash-register"></i> POS
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_facturas') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <div class="card-body py-2 border-bottom bg-light">
                <form action="{{ route('facturacion_local_facturas') }}" method="GET" class="d-flex flex-wrap align-items-end fl-facturas-filtros-form">
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="local_id_fl" class="small text-muted mb-0 d-block">Local</label>
                        <select name="local_id" id="local_id_fl" class="form-control form-control-sm">
                            <option value="0">Todos</option>
                            @foreach ($locales as $loc)
                                <option value="{{ $loc->id }}" @selected((int) ($local_id ?? 0) === (int) $loc->id)>
                                    {{ $loc->codigo }} — {{ $loc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="desde_fl" class="small text-muted mb-0 d-block">Desde</label>
                        <input type="date" id="desde_fl" name="desde" value="{{ $desde }}" class="form-control form-control-sm">
                    </div>
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="hasta_fl" class="small text-muted mb-0 d-block">Hasta</label>
                        <input type="date" id="hasta_fl" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm">
                    </div>
                    @if (count($turnos_selector ?? []) > 0)
                        <div class="form-group mb-2 mb-md-0 mr-2">
                            <label for="turno_operativo_local_id" class="small text-muted mb-0 d-block">Turno</label>
                            <select name="turno_operativo_local_id" id="turno_operativo_local_id" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach ($turnos_selector as $op)
                                    <option value="{{ $op['id'] }}" @selected((int) ($turno_operativo_local_id ?? 0) === (int) $op['id'])>
                                        {{ $op['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group mb-2 mb-md-0 mr-2">
                        <label for="busqueda_fl" class="small text-muted mb-0 d-block">Buscar</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="busqueda_fl" name="busqueda" class="form-control form-control-sm"
                                   placeholder="Nº venta, CAE, código…" value="{{ $busqueda ?? '' }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default btn-sm" title="Consultar">
                                    <span class="fa fa-search"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @if (($busqueda ?? '') !== '' || ($local_id ?? 0) > 0 || ($turno_operativo_local_id ?? null))
                        <div class="form-group mb-2 mb-md-0">
                            <label class="small text-muted mb-0 d-block">&nbsp;</label>
                            <a href="{{ route('facturacion_local_facturas') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                        </div>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                @php
                    $tot = $totales_facturacion ?? [];
                @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_facturacion_local_facturas',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small mb-1 mb-md-0 text-md-right" title="Totales de todos los comprobantes que coinciden con los filtros">
                        <span class="text-muted">Totales filtro:</span>
                        <strong>{{ (int) ($tot['cantidad_facturas'] ?? 0) }}</strong> facturas
                        · <strong>${{ number_format((float) ($tot['total_facturas'] ?? 0), 2, ',', '.') }}</strong>
                        @if (($tot['cantidad_notas_credito'] ?? 0) > 0)
                            · NC
                            <strong>${{ number_format((float) ($tot['total_notas_credito'] ?? 0), 2, ',', '.') }}</strong>
                            <span class="text-muted">({{ (int) ($tot['cantidad_notas_credito'] ?? 0) }})</span>
                        @endif
                        · Neto
                        <strong class="text-primary">${{ number_format((float) ($tot['total_neto'] ?? 0), 2, ',', '.') }}</strong>
                    </div>
                </div>
                <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Venta ID</th>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Local</th>
                            <th>Cliente</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Total</th>
                            <th>NC</th>
                            <th>Cobranza</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $r)
                            @php
                                $v = $r->venta;
                                $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
                                $cobDirecta = $v?->cobranzasDirectas?->first();
                                if (! $cobDirecta && $v) {
                                    foreach ($v->caja_movimientos ?? [] as $movCaja) {
                                        if ($movCaja->cobranzas) {
                                            $cobDirecta = $movCaja->cobranzas;
                                            break;
                                        }
                                    }
                                }
                                $ncVentaId = ($notas_credito_por_factura ?? [])[$r->venta_id] ?? ($r->venta_nc_id ?? null);
                                $clienteTxt = $v?->nombre
                                    ?: ($v?->clientes?->nombre ?? '—');
                                $puedeNc = \App\Support\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoUiSupport::puedeGenerarNotaCredito($r, $v, (int) $r->venta_id);
                                $puedeMedio = \App\Support\Ventas\FacturacionLocal\FacturacionLocalFacturaMedioPagoUiSupport::puedeCambiarMedioPago($r, $cobDirecta !== null, (int) $r->venta_id);
                            @endphp
                            <tr>
                                <td>{{ $r->venta_id }}</td>
                                <td class="text-nowrap"><small>
                                    @if ($v?->fecha)
                                        {{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d-m-Y') }}
                                        @if ($v->created_at)
                                            <span class="text-muted"> {{ $v->created_at->format('H:i') }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </small></td>
                                <td>
                                    <small>{{ $v?->codigo ?? '—' }}</small>
                                    @if (! empty($r->es_ticket_regalo))
                                        <br><span class="badge badge-secondary">Regalo</span>
                                    @endif
                                </td>
                                <td><small>{{ $r->localVenta?->codigo ?? '—' }} {{ $r->localVenta?->nombre ?? '' }}</small></td>
                                <td><small>{{ $clienteTxt }}</small></td>
                                <td><small>{{ $pvTxt !== '' ? $pvTxt : '—' }}</small></td>
                                <td class="text-right fl-col-monto"><small>{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</small></td>
                                <td>
                                    @if ($ncVentaId)
                                        <a href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $ncVentaId]) }}" class="small text-primary">
                                            {{ $r->ventaNc?->codigo ?? ('#'.$ncVentaId) }}
                                        </a>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($cobDirecta)
                                        <small><a href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $r->venta_id]).'#tab-cobranzas' }}">{{ $cobDirecta->id }}</a></small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td class="facturas-dia-tabla-acciones text-nowrap">
                                    @if (can('ver-factura-facturacion-local', false) && $v)
                                        <a href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $r->venta_id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                    @if ($puedeMedio && $v)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-cambiar-medio-pago"
                                            data-venta-id="{{ $v->id }}"
                                            title="Cambiar medio de pago">
                                            <i class="fa fa-exchange-alt text-warning"></i>
                                        </button>
                                    @endif
                                    @if ($puedeNc && $v)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-generar-nc"
                                            data-venta-id="{{ $v->id }}"
                                            data-codigo="{{ $v->codigo ?? '' }}"
                                            title="Generar nota de crédito">
                                            <i class="fas fa-undo text-warning"></i>
                                        </button>
                                    @elseif ($ncVentaId)
                                        <a href="{{ route('facturacion_local_facturas_ver', ['ventaId' => $ncVentaId]) }}"
                                           class="btn-accion-tabla tooltipsC"
                                           title="Ver nota de crédito">
                                            <i class="fas fa-undo text-muted"></i>
                                        </a>
                                    @endif
                                    @if ($v)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fl-reimprimir"
                                            data-venta-id="{{ $v->id }}"
                                            data-pdf-url="{{ url('ventas/listaunafactura/'.$v->id) }}"
                                            title="Reimprimir (abrir PDF)">
                                            <i class="fas fa-print text-secondary"></i>
                                        </button>
                                        <a href="{{ url('ventas/listaunafactura/'.$v->id) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="PDF comprobante">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    Sin registros para los filtros indicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if (method_exists($registros, 'hasPages') && $registros->hasPages())
                    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 border-top bg-light">
                        <small class="text-muted mb-2 mb-md-0">
                            Mostrando {{ $registros->firstItem() ?? 0 }}–{{ $registros->lastItem() ?? 0 }}
                            de {{ $registros->total() }} factura(s)
                        </small>
                        <div>{{ $registros->onEachSide(1)->links() }}</div>
                    </div>
                @elseif (method_exists($registros, 'total'))
                    <div class="px-3 py-2 border-top bg-light">
                        <small class="text-muted">{{ $registros->total() }} factura(s).</small>
                    </div>
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
