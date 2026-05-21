@extends("theme.$theme.layout")

@section('titulo')
    Facturas gastronomía del día
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script>
(function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var token = csrfToken ? csrfToken.getAttribute('content') : '';

    document.querySelectorAll('.js-fd-reimprimir-ticket').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            btn.disabled = true;
            fetch('{{ url('ventas/gastronomia/facturas-dia') }}/' + ventaId + '/reimprimir-ticket', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
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
                    if (typeof toastr !== 'undefined') toastr.error('Error de comunicación al reimprimir.');
                    else alert('Error de comunicación al reimprimir.');
                })
                .finally(function () { btn.disabled = false; });
        });
    });

    document.querySelectorAll('.js-fd-toggle-insumos').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var targetId = btn.getAttribute('data-target');
            if (!targetId) return;
            var row = document.getElementById(targetId);
            if (!row) return;
            row.classList.toggle('d-none');
            var icon = btn.querySelector('i.fa');
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-right');
            }
        });
    });
})();
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($jornada['jornada_abierta']))
            <div class="alert alert-info py-2 mb-2">
                Jornada <strong>{{ $jornada['fecha_jornada'] }}</strong> abierta
                @if (! request()->filled('fecha'))
                    · filtro por defecto: fecha de jornada
                @endif
                @if (! empty($jornada['fecha_factura_hoy']) && ($jornada['fecha_factura_hoy'] ?? '') !== ($jornada['fecha_jornada'] ?? ''))
                    · comprobantes con fecha calendario <strong>{{ $jornada['fecha_factura_hoy'] }}</strong>
                @endif
            </div>
        @elseif ($jornada !== null)
            <div class="alert alert-secondary py-2 mb-2">
                Sin jornada abierta para esta empresa. Mostrando fecha de jornada indicada o el día de hoy.
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Facturas gastronomía del día</h3>
                <div class="card-tools">
                    <small class="text-muted">Esta terminal: <strong>{{ $identificador_pc }}</strong></small>
                </div>
                <div class="d-md-flex justify-content-md-end align-items-md-end flex-wrap">
                    <form action="{{ route('gastronomia_facturas_dia') }}" method="GET" class="d-flex flex-wrap align-items-end mb-2 mb-md-0">
                        <div class="form-group mb-0 mr-2">
                            <label for="fecha_fd" class="small text-muted mb-0 d-block">Fecha jornada</label>
                            <input type="date" id="fecha_fd" name="fecha" value="{{ $fecha }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label for="articulo_sku_fd" class="small text-muted mb-0 d-block">Ítem facturado (SKU)</label>
                            <input type="text" id="articulo_sku_fd" name="articulo_sku" class="form-control form-control-sm" style="min-width:140px;"
                                   placeholder="SKU o descripción" value="{{ $articulo_sku ?? '' }}"
                                   title="Filtra facturas que incluyan este artículo y muestra sus insumos">
                            @if ($articulo_filtro ?? null)
                                <input type="hidden" name="articulo_id" value="{{ $articulo_filtro->id }}">
                            @endif
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label class="small text-muted mb-0 d-block">&nbsp;</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="todas_pc" name="todas_pc" value="1" @checked($todas_pc ?? false)>
                                <label class="custom-control-label small" for="todas_pc" title="Incluye facturas emitidas desde otras PCs del mismo día">Todas las terminales</label>
                            </div>
                        </div>
                        <div class="btn-group mr-2">
                            <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Nº venta, cliente…" value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default btn-sm" title="Buscar">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                        @if (($articulo_filtro ?? null) || ($busqueda ?? '') !== '')
                            <a href="{{ route('gastronomia_facturas_dia', ['fecha' => $fecha, 'todas_pc' => ($todas_pc ?? false) ? '1' : null]) }}"
                               class="btn btn-outline-secondary btn-sm ml-1 mb-0" title="Quitar filtros de texto">Limpiar</a>
                        @endif
                    </form>
                </div>
            </div>
            @if ($articulo_filtro ?? null)
                <div class="card-body py-2 border-bottom bg-light">
                    <span class="small">
                        <strong>Consulta por ítem:</strong>
                        @include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', [
                            'sku' => $articulo_filtro->sku,
                            'articuloId' => $articulo_filtro->id,
                        ])
                        — {{ $articulo_filtro->descripcion }}
                        <span class="text-muted">(facturas del día que incluyen este artículo; expandir fila para ver insumos descontados)</span>
                    </span>
                </div>
            @elseif (($articulo_sku ?? '') !== '')
                <div class="card-body py-2 border-bottom">
                    <span class="text-warning small">No se encontró artículo para «{{ $articulo_sku }}». Revise el SKU o use búsqueda parcial.</span>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_gastronomia_facturas_dia',
                    'queryparams' => array_filter([
                        'fecha' => $fecha,
                        'busqueda' => $busqueda ?? '',
                        'todas_pc' => ($todas_pc ?? false) ? '1' : null,
                        'articulo_sku' => $articulo_sku ?? '',
                        'articulo_id' => ($articulo_filtro ?? null) ? $articulo_filtro->id : null,
                    ], fn ($v) => $v !== null && $v !== ''),
                ])
                @php
                    $colInsumos = ($articulo_filtro ?? null) !== null;
                    $colSpanEmpty = 10 + (($todas_pc ?? false) ? 1 : 0) + ($colInsumos ? 3 : 0);
                @endphp
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            @if ($colInsumos)
                                <th style="width:2rem;" data-orderable="false"></th>
                            @endif
                            <th>Venta ID</th>
                            @if ($todas_pc ?? false)
                                <th>PC emisión</th>
                            @endif
                            <th>Fecha jornada</th>
                            <th>Fecha comprob.</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Total</th>
                            @if ($colInsumos)
                                <th class="text-right" title="Cantidad facturada del ítem filtrado">Cant. ítem</th>
                                <th>Insumos</th>
                            @endif
                            <th>Cobranza</th>
                            <th>Cuenta gastro.</th>
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
                                $insumosVenta = ($insumos_por_venta ?? [])[$r->venta_id] ?? collect();
                                $cantItem = ($articulo_filtro ?? null)
                                    ? \App\Support\Ventas\GastronomiaVentaDetalleSupport::cantidadItemFacturadoEnVenta((int) $r->venta_id, (int) $articulo_filtro->id)
                                    : 0.;
                                $verParams = ['ventaId' => $r->venta_id];
                                if ($articulo_filtro ?? null) {
                                    $verParams['articulo_id'] = $articulo_filtro->id;
                                }
                            @endphp
                            <tr>
                                @if ($colInsumos)
                                    <td class="text-center align-middle">
                                        @if ($insumosVenta->isNotEmpty())
                                            <button type="button" class="btn btn-link btn-sm p-0 js-fd-toggle-insumos"
                                                    data-target="fd-insumos-{{ $r->venta_id }}" title="Ver insumos de este ítem">
                                                <i class="fa fa-chevron-right text-muted"></i>
                                            </button>
                                        @endif
                                    </td>
                                @endif
                                <td>{{ $r->venta_id }}</td>
                                @if ($todas_pc ?? false)
                                    <td><small>{{ $r->identificador_pc ?? '—' }}</small></td>
                                @endif
                                <td class="text-nowrap"><small>
                                    @if ($v?->fechajornada)
                                        {{ \Illuminate\Support\Carbon::parse($v->fechajornada)->format('d-m-Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </small></td>
                                <td class="text-nowrap"><small>
                                    @if ($v?->fecha)
                                        {{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d-m-Y') }}@if ($v->created_at)<span class="text-muted" title="Hora de creación"> {{ $v->created_at->format('H:i:s') }}</span>@endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </small></td>
                                <td><small>{{ $v?->codigo ?? '—' }}</small></td>
                                <td><small>{{ $v ? \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreReceptorFactura($v) : '—' }}</small></td>
                                <td><small>{{ $pvTxt !== '' ? $pvTxt : '—' }}</small></td>
                                <td class="text-right"><small>{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</small></td>
                                @if ($colInsumos)
                                    <td class="text-right"><small>{{ number_format($cantItem, 3, ',', '.') }}</small></td>
                                    <td>
                                        @if ($insumosVenta->isEmpty())
                                            <small class="text-muted">Sin insumos</small>
                                        @else
                                            <small>{{ $insumosVenta->count() }} insumo(s)</small>
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    @if ($cobDirecta)
                                        <small><a href="{{ route('gastronomia_facturas_dia_ver', $verParams).'#tab-cobranzas' }}" title="Ver cobranza">{{ $cobDirecta->id }}</a></small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td><small>{{ $r->cuenta_gastronomia_id ?? '—' }}</small></td>
                                <td>
                                    @if (can('ver-factura-gastronomia', false))
                                        <a href="{{ route('gastronomia_facturas_dia_ver', $verParams) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('gastronomia_facturas_dia_ver', $verParams).'#tab-detalle' }}" class="btn-accion-tabla tooltipsC" title="Ítems e insumos">
                                            <i class="fas fa-boxes text-info"></i>
                                        </a>
                                    @endif
                                    @if (can('editar-factura', false) && $v)
                                        <a href="{{ route('editar_factura', ['id' => $v->id, 'origen' => 'gastronomia_facturas_dia']) }}" class="btn-accion-tabla tooltipsC" title="Editar comprobante">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($v)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-reimprimir-ticket border-0 bg-transparent p-0"
                                            data-venta-id="{{ $v->id }}"
                                            title="Reimprimir ticket térmico">
                                            <i class="fas fa-receipt text-secondary"></i>
                                        </button>
                                        <a href="{{ url('ventas/listaunafactura/'.$v->id) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="PDF comprobante">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @if ($colInsumos && $insumosVenta->isNotEmpty())
                                <tr id="fd-insumos-{{ $r->venta_id }}" class="d-none bg-light">
                                    <td colspan="{{ $colSpanEmpty }}" class="py-2">
                                        @if ($articulo_filtro ?? null)
                                            <p class="small mb-2">
                                                <strong>Ítem facturado:</strong>
                                                @include('ventas.gastronomia.facturas_dia.partials.item_facturado_insumos', [
                                                    'sku' => $articulo_filtro->sku,
                                                    'articuloId' => $articulo_filtro->id,
                                                    'detalle' => $articulo_filtro->descripcion,
                                                ])
                                            </p>
                                        @endif
                                        <table class="table table-sm table-bordered mb-0 small" style="max-width:800px;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>SKU insumo</th>
                                                    <th>Descripción insumo</th>
                                                    <th class="text-right">Cant. descontada</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($insumosVenta as $mov)
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
                            <tr><td colspan="{{ $colSpanEmpty }}" class="text-center text-muted py-4">
                                Sin registros para la fecha y filtros indicados.
                                @if (! ($todas_pc ?? false))
                                    <br><span class="small">Si la facturó otra terminal, active <strong>Todas las terminales</strong> o busque por <strong>nº de venta</strong>.</span>
                                @endif
                                @if ($articulo_filtro ?? null)
                                    <br><span class="small">Ninguna factura del día incluye el ítem <strong>{{ $articulo_filtro->sku }}</strong>.</span>
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
