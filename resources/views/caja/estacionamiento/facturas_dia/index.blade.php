@extends("theme.$theme.layout")

@section('titulo')
    Facturas estacionamiento del día
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@include('caja.estacionamiento.facturas_dia.partials.script_generar_nc')
@if (can('cambiar-medio-pago-estacionamiento-facturas-dia', false))
    @include('caja.estacionamiento.facturas_dia.partials.script_cambiar_medio_pago')
@endif
<script>
(function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var token = csrfToken ? csrfToken.getAttribute('content') : '';

    function leerRespuesta(r) {
        return r.text().then(function (txt) {
            var body = null;
            if (txt) {
                try { body = JSON.parse(txt); } catch (e) {}
            }
            return { ok: r.ok, status: r.status, body: body };
        });
    }

    function mensajeError(res, defecto) {
        if (res && res.status === 419) {
            return 'La sesión expiró por inactividad. Recargá la página (F5) y volvé a intentar.';
        }
        if (res && res.body) {
            var b = res.body;
            return b.error || b.mensaje || b.message || defecto;
        }
        return defecto;
    }

    function refrescarCsrfToken() {
        return fetch('{{ route('csrf_token_refresh') }}', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                var nuevo = j && j.token ? j.token : null;
                if (nuevo) {
                    token = nuevo;
                    if (csrfToken) csrfToken.setAttribute('content', nuevo);
                }
                return nuevo;
            })
            .catch(function () { return null; });
    }

    document.querySelectorAll('.js-fd-reimprimir-ticket').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            btn.disabled = true;
            function enviarReimpresion() {
                return fetch('{{ url('caja/estacionamiento/facturas-dia') }}/' + ventaId + '/reimprimir-ticket', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }).then(leerRespuesta);
            }

            enviarReimpresion()
                .then(function (res) {
                    if (res.status !== 419) return res;
                    return refrescarCsrfToken().then(function (nuevo) {
                        return nuevo ? enviarReimpresion() : res;
                    });
                })
                .then(function (res) {
                    if (res.ok && res.body && res.body.ok) {
                        var okMsg = (res.body.mensaje || res.body.message) || 'Ticket enviado.';
                        if (typeof toastr !== 'undefined') toastr.success(okMsg);
                        else alert(okMsg);
                    } else {
                        var msg = mensajeError(res, 'Error al reimprimir.');
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
})();
</script>
@endsection

@section('contenido')
@include('caja.estacionamiento.facturas_dia.partials.estilos_acciones_tabla')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="alert alert-info py-2 mb-2">
            @if ($tiene_cfg_pv ?? false)
                Terminal: <strong>{{ $identificador_pc }}</strong>
                · Empresa: <strong>{{ $empresa_nombre }}</strong>
                ·
            @else
                Terminal: <strong>{{ $identificador_pc }}</strong>
                · <span class="text-warning">Sin configuración PV estacionamiento para esta terminal</span>
                ·
            @endif
            Fecha jornada <strong>{{ $fecha }}</strong>
            · Fecha calendario <strong>{{ $fecha_calendario ?? \Illuminate\Support\Carbon::today()->format('Y-m-d') }}</strong>
            @if (! empty($jornada['jornada_abierta']))
                · jornada abierta
                @if (! request()->filled('fecha'))
                    (filtro por defecto)
                @endif
            @elseif ($jornada !== null)
                · sin jornada abierta para esta empresa
            @endif
            @if (($requiere_habilitacion_turno ?? false) && ($turno_filtro_val ?? '0') !== '0' && ($turno_filtro ?? null))
                · filtrando turno
                <strong>{{ $turno_filtro->turno?->nombre ?? 'Turno' }}</strong>
                @if (($turno_filtro_val ?? '') === 'activo')
                    (activo)
                @else
                    (cerrado)
                @endif
                · {{ $turno_filtro->habilitacion_en?->format('Y-m-d H:i') ?? '—' }}
                @if ($turno_filtro->cierre_en)
                    → {{ $turno_filtro->cierre_en->format('Y-m-d H:i') }}
                @else
                    → activo
                @endif
            @elseif ($todas_pc ?? false)
                · mostrando todas las terminales
                @if ($empresa_nombre ?? null)
                    de <strong>{{ $empresa_nombre }}</strong>
                @endif
            @elseif (($requiere_habilitacion_turno ?? false) && ($turno_filtro_val ?? '0') === '0')
                · mostrando facturas del día en esta terminal
            @endif
        </div>
        @if (($requiere_habilitacion_turno ?? false) && ! ($turno_habilitado ?? false))
            <div class="alert alert-warning py-2 mb-2">
                No hay turno habilitado en esta terminal (<strong>{{ $identificador_pc }}</strong>).
                Debe <a href="{{ $url_habilitacion_turno ?? route('estacionamiento_habilitacion_turno') }}">habilitar el turno</a>
                antes de generar notas de crédito desde este listado.
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Facturas estacionamiento del día</h3>
                <div class="card-tools">
                    @if ($tiene_cfg_pv ?? false)
                        <small class="text-muted">
                            Empresa: <strong>{{ $empresa_nombre }}</strong>
                            · Terminal: <strong>{{ $identificador_pc }}</strong>
                        </small>
                    @else
                        <small class="text-muted">Terminal: <strong>{{ $identificador_pc }}</strong></small>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end align-items-md-end flex-wrap">
                    <form action="{{ route('estacionamiento_facturas_dia') }}" method="GET" class="d-flex flex-wrap align-items-end mb-2 mb-md-0">
                        <div class="form-group mb-0 mr-2">
                            <label for="fecha_fd" class="small text-muted mb-0 d-block">Fecha jornada</label>
                            <input type="date" id="fecha_fd" name="fecha" value="{{ $fecha }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label for="item_nombre_fd" class="small text-muted mb-0 d-block">Ítem estacionamiento</label>
                            <input type="text" id="item_nombre_fd" name="item_nombre" class="form-control form-control-sm" style="min-width:140px;"
                                   placeholder="Nombre o ID de ítem" value="{{ $item_nombre ?? '' }}"
                                   title="Filtra facturas que incluyan este ítem de estacionamiento">
                            @if ($item_filtro ?? null)
                                <input type="hidden" name="item_id" value="{{ $item_filtro->id }}">
                            @endif
                        </div>
                        <div class="form-group mb-0 mr-2">
                            <label class="small text-muted mb-0 d-block">&nbsp;</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="todas_pc" name="todas_pc" value="1" @checked($todas_pc ?? false)>
                                <label class="custom-control-label small" for="todas_pc" title="Incluye facturas emitidas desde otras PCs de la misma empresa en el día">Todas las terminales</label>
                            </div>
                        </div>
                        @if (count($items_selector ?? []) > 0)
                            <div class="form-group mb-0 mr-2">
                                <label for="item_estacionamiento_id" class="small text-muted mb-0 d-block">Ítem</label>
                                <select name="item_estacionamiento_id" id="item_estacionamiento_id" class="form-control form-control-sm" style="min-width:160px;">
                                    <option value="">Todos</option>
                                    @foreach ($items_selector as $itemOp)
                                        <option value="{{ $itemOp['id'] }}" @selected((int) ($item_estacionamiento_id ?? 0) === (int) $itemOp['id'])>
                                            {{ $itemOp['nombre'] }}@if (! empty($itemOp['codigo'])) ({{ $itemOp['codigo'] }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if ($requiere_habilitacion_turno ?? false)
                            <div class="form-group mb-0 mr-2">
                                <label for="turno_filtro" class="small text-muted mb-0 d-block">Turno</label>
                                <select name="turno_filtro" id="turno_filtro" class="form-control form-control-sm" style="min-width:220px;"
                                        title="Todo el día, turno activo o un turno cerrado de esta terminal">
                                    <option value="0" @selected(($turno_filtro_val ?? '0') === '0')>Todo el día</option>
                                    @if ($turno_activo ?? null)
                                        <option value="activo" @selected(($turno_filtro_val ?? '') === 'activo')>
                                            Turno activo — {{ $turno_activo->turno?->nombre ?? 'Turno' }}
                                            ({{ $turno_activo->habilitacion_en?->format('Y-m-d H:i') ?? '—' }})
                                        </option>
                                    @endif
                                    @foreach ($turnos_selector ?? [] as $op)
                                        @if (! ($op['es_activo'] ?? false))
                                            <option value="{{ $op['id'] }}" @selected((string) ($turno_filtro_val ?? '') === (string) $op['id'])>
                                                {{ $op['label'] }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="btn-group mr-2">
                            <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Nº venta, cliente…" value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default btn-sm" title="Buscar">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                        @if (($item_filtro ?? null) || ($busqueda ?? '') !== '' || ($item_estacionamiento_id ?? null))
                            <a href="{{ route('estacionamiento_facturas_dia', array_filter([
                                'fecha' => $fecha,
                                'todas_pc' => ($todas_pc ?? false) ? '1' : null,
                                'turno_filtro' => ($turno_filtro_val ?? '0') !== '0' ? ($turno_filtro_val ?? '0') : null,
                            ])) }}"
                               class="btn btn-outline-secondary btn-sm ml-1 mb-0" title="Quitar filtros de texto">Limpiar</a>
                        @endif
                    </form>
                </div>
            </div>
            @if ($item_filtro ?? null)
                <div class="card-body py-2 border-bottom bg-light">
                    <span class="small">
                        <strong>Consulta por ítem:</strong>
                        @include('caja.estacionamiento.facturas_dia.partials.link_item_estacionamiento', [
                            'itemId' => $item_filtro->id,
                            'nombre' => $item_filtro->nombre,
                        ])
                        <span class="text-muted">(facturas del día que incluyen este ítem de estacionamiento)</span>
                    </span>
                </div>
            @elseif (($item_nombre ?? '') !== '')
                <div class="card-body py-2 border-bottom">
                    <span class="text-warning small">No se encontró ítem de estacionamiento para «{{ $item_nombre }}». Revise el nombre o el ID.</span>
                </div>
            @endif
            <div class="card-body p-0">
                @php
                    $tot = $totales_facturacion ?? [];
                @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_estacionamiento_facturas_dia',
                            'queryparams' => array_filter([
                                'fecha' => $fecha,
                                'busqueda' => $busqueda ?? '',
                                'todas_pc' => ($todas_pc ?? false) ? '1' : null,
                                'turno_filtro' => ($requiere_habilitacion_turno ?? false) && ($turno_filtro_val ?? '0') !== '0'
                                    ? ($turno_filtro_val ?? '0')
                                    : null,
                                'item_nombre' => $item_nombre ?? '',
                                'item_id' => ($item_filtro ?? null) ? $item_filtro->id : null,
                                'item_estacionamiento_id' => $item_estacionamiento_id ?? null,
                            ], fn ($v) => $v !== null && $v !== ''),
                        ])
                    </div>
                    <div class="small mb-1 mb-md-0 text-md-right" title="Totales de todos los comprobantes que coinciden con los filtros aplicados">
                        <span class="text-muted">Totales filtro:</span>
                        <strong>{{ (int) ($tot['cantidad_comprobantes'] ?? 0) }}</strong> comprob.
                        · Facturas
                        <strong>${{ number_format((float) ($tot['total_facturas'] ?? 0), 2, ',', '.') }}</strong>
                        <span class="text-muted">({{ (int) ($tot['cantidad_facturas'] ?? 0) }})</span>
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
                @php
                    $colItemFiltro = ($item_filtro ?? null) !== null;
                    $colSpanEmpty = 11 + (($todas_pc ?? false) ? 1 : 0) + ($colItemFiltro ? 1 : 0);
                @endphp
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Venta ID</th>
                            @if ($todas_pc ?? false)
                                <th>PC emisión</th>
                            @endif
                            <th>Fecha jornada</th>
                            <th>Fecha comprob.</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Ítem</th>
                            <th>Punto de venta</th>
                            <th class="text-right">Total</th>
                            @if ($colItemFiltro)
                                <th class="text-right" title="Cantidad facturada del ítem filtrado">Cant. ítem</th>
                            @endif
                            <th>Cobranza</th>
                            <th>Cuenta estac.</th>
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
                                $cantItem = ($item_filtro ?? null)
                                    ? \App\Support\Caja\Estacionamiento\EstacionamientoVentaDetalleSupport::cantidadItemFacturadoEnVenta((int) $r->venta_id, (int) $item_filtro->id)
                                    : 0.;
                                $verParams = ['ventaId' => $r->venta_id];
                                if ($item_filtro ?? null) {
                                    $verParams['item_id'] = $item_filtro->id;
                                }
                                $ncVentaId = ($notas_credito_por_factura ?? [])[$r->venta_id] ?? null;
                                $esComprobanteNc = ($r->venta_factura_origen_id ?? null) !== null;
                                $tipoFactura = $v?->tipotransacciones;
                                $esFacturaVenta = ! $esComprobanteNc
                                    && (! $tipoFactura || $tipoFactura->signo === 'S');
                                $puedeNc = can('generar-nota-credito-estacionamiento-facturas-dia', false)
                                    && $esFacturaVenta
                                    && $v
                                    && (float) ($v->total ?? 0) >= 0.01
                                    && $ncVentaId === null
                                    && (! ($requiere_habilitacion_turno ?? false) || ($turno_habilitado ?? false));
                            @endphp
                            <tr>
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
                                <td><small>{{ $v?->codigo ?? '—' }}</small>
                                    @php
                                        $ticketDisplay = null;
                                        if ($r->ticket) {
                                            $patenteTicket = trim((string) ($r->ticket->patente ?? ''));
                                            if ($patenteTicket !== '') {
                                                $ticketDisplay = $patenteTicket;
                                            } elseif ((int) ($r->ticket->numero_ticket ?? 0) > 0) {
                                                $ticketDisplay = '#'.$r->ticket->numero_ticket;
                                            }
                                        }
                                        if ($ticketDisplay === null && $v) {
                                            $ticketDisplay = \App\Support\Caja\Estacionamiento\EstacionamientoVentaDisplaySupport::estacionamientoDisplayId($v);
                                        }
                                    @endphp
                                    @if ($ticketDisplay)
                                        <br><small class="text-muted" title="Ticket estacionamiento">Ticket {{ $ticketDisplay }}</small>
                                    @endif
                                </td>
                                <td><small>{{ $v ? \App\Support\Caja\Estacionamiento\EstacionamientoVentaDisplaySupport::nombreReceptorFactura($v) : '—' }}</small></td>
                                <td><small>{{ $r->cuenta?->item?->nombre ?? '—' }}</small></td>
                                <td><small>{{ $pvTxt !== '' ? $pvTxt : '—' }}</small></td>
                                <td class="text-right est-col-monto"><small>{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</small></td>
                                @if ($colItemFiltro)
                                    <td class="text-right"><small>{{ number_format($cantItem, 3, ',', '.') }}</small></td>
                                @endif
                                <td>
                                    @if ($cobDirecta)
                                        <small><a href="{{ route('estacionamiento_facturas_dia_ver', $verParams).'#tab-cobranzas' }}" title="Ver cobranza">{{ $cobDirecta->id }}</a></small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td><small>{{ $r->cuenta_estacionamiento_id ?? '—' }}</small></td>
                                <td class="facturas-dia-tabla-acciones text-nowrap">
                                    @if (can('ver-factura-estacionamiento', false))
                                        <a href="{{ route('estacionamiento_facturas_dia_ver', $verParams) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('estacionamiento_facturas_dia_ver', $verParams).'#tab-detalle' }}" class="btn-accion-tabla tooltipsC" title="Ítems facturados">
                                            <i class="fas fa-boxes text-info"></i>
                                        </a>
                                    @endif
                                    @if (can('cambiar-medio-pago-estacionamiento-facturas-dia', false) && $v && $cobDirecta)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-cambiar-medio-pago"
                                            data-venta-id="{{ $v->id }}"
                                            data-placement="left"
                                            title="Cambiar medio de pago (sin modificar el monto)">
                                            <i class="fa fa-exchange-alt text-warning"></i>
                                        </button>
                                    @endif
                                    @if ($puedeNc)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-generar-nc"
                                            data-venta-id="{{ $v->id }}"
                                            data-codigo="{{ $v->codigo ?? '' }}"
                                            data-placement="left"
                                            title="Generar nota de crédito">
                                            <i class="fas fa-undo text-warning"></i>
                                        </button>
                                    @elseif ($ncVentaId)
                                        <a href="{{ route('estacionamiento_facturas_dia_ver', ['ventaId' => $ncVentaId]) }}"
                                           class="btn-accion-tabla tooltipsC"
                                           title="Ver nota de crédito generada">
                                            <i class="fas fa-undo text-muted"></i>
                                        </a>
                                    @endif
                                    @if ($v)
                                        <button type="button"
                                            class="btn-accion-tabla tooltipsC js-fd-reimprimir-ticket"
                                            data-venta-id="{{ $v->id }}"
                                            data-placement="left"
                                            title="Reimprimir ticket térmico">
                                            <i class="fas fa-receipt text-secondary"></i>
                                        </button>
                                        <a href="{{ url('ventas/listaunafactura/'.$v->id) }}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="PDF comprobante">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colSpanEmpty }}" class="text-center text-muted py-4">
                                Sin registros para la fecha y filtros indicados.
                                @if (! ($todas_pc ?? false))
                                    <br><span class="small">Si la facturó otra terminal de esta empresa, active <strong>Todas las terminales</strong> o busque por <strong>nº de venta</strong>.</span>
                                @endif
                                @if ($item_filtro ?? null)
                                    <br><span class="small">Ninguna factura del día incluye el ítem
                                        @include('caja.estacionamiento.facturas_dia.partials.link_item_estacionamiento', [
                                            'itemId' => $item_filtro->id,
                                            'nombre' => $item_filtro->nombre,
                                        ])
                                    </span>
                                @endif
                            </td></tr>
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
                        <small class="text-muted">{{ $registros->total() }} factura(s) en la página.</small>
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('caja.estacionamiento.facturas_dia.partials.modal_generar_nc')
@if (can('cambiar-medio-pago-estacionamiento-facturas-dia', false))
    @include('caja.estacionamiento.facturas_dia.partials.modal_cambiar_medio_pago')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@endsection
