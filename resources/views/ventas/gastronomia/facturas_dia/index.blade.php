@extends("theme.$theme.layout")

@section('titulo')
    Facturas gastronomía del día
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@include('ventas.gastronomia.facturas_dia.partials.script_generar_nc')
@if (can('cambiar-medio-pago-gastronomia-facturas-dia', false))
    @include('ventas.gastronomia.facturas_dia.partials.script_cambiar_medio_pago')
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

    document.querySelectorAll('.js-fd-tickets-tarjeta').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            var modal = document.getElementById('modal-fd-tickets-tarjeta');
            var tbody = document.getElementById('fd-tickets-tarjeta-body');
            var errEl = document.getElementById('fd-tickets-tarjeta-error');
            if (!modal || !tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted small">Cargando…</td></tr>';
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            if (typeof $ !== 'undefined') {
                $('#modal-fd-tickets-tarjeta').modal('show');
            }
            fetch('{{ url('ventas/gastronomia/facturas-dia') }}/' + ventaId + '/tickets-tarjeta', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(leerRespuesta)
                .then(function (res) {
                    if (!res.ok || !res.body || !res.body.ok) {
                        var msg = mensajeError(res, 'No se pudieron cargar los tickets.');
                        tbody.innerHTML = '';
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    var tickets = res.body.tickets || [];
                    if (!tickets.length) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-muted small">Sin tickets tarjeta canjeados en esta factura.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = tickets.map(function (t) {
                        return '<tr>'
                            + '<td>' + (t.ticket_id || '') + '</td>'
                            + '<td>' + (t.numeroticket || '') + '</td>'
                            + '<td>' + (t.numerodocumento || '—') + '</td>'
                            + '<td class="text-right">' + (parseFloat(t.montoticket) || 0).toFixed(2) + '</td>'
                            + '<td>' + (t.fecha_emision || '—') + '</td>'
                            + '<td>' + (t.created_at || '—') + '</td>'
                            + '</tr>';
                    }).join('');
                })
                .catch(function () {
                    tbody.innerHTML = '';
                    if (errEl) {
                        errEl.textContent = 'Error de comunicación al consultar tickets.';
                        errEl.classList.remove('d-none');
                    }
                });
        });
    });

    document.querySelectorAll('.js-fd-canjes-fidelidad').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            var modal = document.getElementById('modal-fd-canjes-fidelidad');
            var tbody = document.getElementById('fd-canjes-fidelidad-body');
            var errEl = document.getElementById('fd-canjes-fidelidad-error');
            if (!modal || !tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" class="text-muted small">Cargando…</td></tr>';
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            if (typeof $ !== 'undefined') {
                $('#modal-fd-canjes-fidelidad').modal('show');
            }
            fetch('{{ url('ventas/gastronomia/facturas-dia') }}/' + ventaId + '/canjes-fidelidad', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(leerRespuesta)
                .then(function (res) {
                    if (!res.ok || !res.body || !res.body.ok) {
                        var msg = mensajeError(res, 'No se pudieron cargar los canjes.');
                        tbody.innerHTML = '';
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    var canjes = res.body.canjes || [];
                    if (!canjes.length) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-muted small">Sin canjes de fidelidad en esta factura.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = canjes.map(function (c) {
                        return '<tr>'
                            + '<td>' + (c.tarjeta || '—') + '</td>'
                            + '<td class="small">' + (c.trackdata || '—') + '</td>'
                            + '<td>' + (c.documento || '—') + '</td>'
                            + '<td>' + (c.titular || (c.apellido || '') + ' ' + (c.nombre || '')).trim() + '</td>'
                            + '<td>' + (c.categoria_nombre || '') + (c.categoria_codigo ? ' [' + c.categoria_codigo + ']' : '') + '</td>'
                            + '<td>' + (c.sku || '') + '</td>'
                            + '<td>' + (c.articulo || '—') + '</td>'
                            + '<td>' + (c.fechacanje || '—') + '</td>'
                            + '</tr>';
                    }).join('');
                })
                .catch(function () {
                    tbody.innerHTML = '';
                    if (errEl) {
                        errEl.textContent = 'Error de comunicación al consultar canjes de fidelidad.';
                        errEl.classList.remove('d-none');
                    }
                });
        });
    });

    document.querySelectorAll('.js-fd-canjes-premio').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            var modal = document.getElementById('modal-fd-canjes-premio');
            var tbody = document.getElementById('fd-canjes-premio-body');
            var errEl = document.getElementById('fd-canjes-premio-error');
            if (!modal || !tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" class="text-muted small">Cargando…</td></tr>';
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            if (typeof $ !== 'undefined') {
                $('#modal-fd-canjes-premio').modal('show');
            }
            fetch('{{ url('ventas/gastronomia/facturas-dia') }}/' + ventaId + '/canjes-premio', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(leerRespuesta)
                .then(function (res) {
                    if (!res.ok || !res.body || !res.body.ok) {
                        var msg = mensajeError(res, 'No se pudieron cargar los canjes.');
                        tbody.innerHTML = '';
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    var canjes = res.body.canjes || [];
                    if (!canjes.length) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-muted small">Sin canjes de premio en esta factura.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = canjes.map(function (c) {
                        return '<tr>'
                            + '<td>' + (c.numerocupon || '') + '</td>'
                            + '<td>' + (c.sku || '') + '</td>'
                            + '<td>' + (c.articulo || '—') + '</td>'
                            + '<td class="text-right">' + (parseFloat(c.cantidad) || 0) + '</td>'
                            + '<td class="text-right">' + (c.puntos || 0) + '</td>'
                            + '<td>' + (c.apellido || '') + ' ' + (c.nombre || '') + '</td>'
                            + '<td>' + (c.numerodocumento || '—') + '</td>'
                            + '<td>' + (c.fechacanje || '—') + '</td>'
                            + '</tr>';
                    }).join('');
                })
                .catch(function () {
                    tbody.innerHTML = '';
                    if (errEl) {
                        errEl.textContent = 'Error de comunicación al consultar canjes.';
                        errEl.classList.remove('d-none');
                    }
                });
        });
    });

    document.querySelectorAll('.js-fd-reimprimir-ticket').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var ventaId = btn.getAttribute('data-venta-id');
            if (!ventaId || btn.disabled) return;
            btn.disabled = true;
            function enviarReimpresion() {
                return fetch('{{ url('ventas/gastronomia/facturas-dia') }}/' + ventaId + '/reimprimir-ticket', {
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
@include('ventas.gastronomia.facturas_dia.partials.estilos_acciones_tabla')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="alert alert-info py-2 mb-2">
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
            @elseif (($requiere_habilitacion_turno ?? false) && ($turno_filtro_val ?? '0') === '0')
                · mostrando todas las facturas del día en la terminal
            @endif
        </div>
        @if (($requiere_habilitacion_turno ?? false) && ! ($turno_habilitado ?? false))
            <div class="alert alert-warning py-2 mb-2">
                No hay turno habilitado en esta terminal (<strong>{{ $identificador_pc }}</strong>).
                Debe <a href="{{ $url_habilitacion_turno ?? route('gastronomia_habilitacion_turno') }}">habilitar el turno</a>
                antes de generar notas de crédito desde este listado.
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
                        @if (($articulo_filtro ?? null) || ($busqueda ?? '') !== '')
                            <a href="{{ route('gastronomia_facturas_dia', array_filter([
                                'fecha' => $fecha,
                                'todas_pc' => ($todas_pc ?? false) ? '1' : null,
                                'turno_filtro' => ($turno_filtro_val ?? '0') !== '0' ? ($turno_filtro_val ?? '0') : null,
                            ])) }}"
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
            <div class="card-body p-0">
                @php
                    $tot = $totales_facturacion ?? [];
                @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_gastronomia_facturas_dia',
                            'queryparams' => array_filter([
                                'fecha' => $fecha,
                                'busqueda' => $busqueda ?? '',
                                'todas_pc' => ($todas_pc ?? false) ? '1' : null,
                                'turno_filtro' => ($requiere_habilitacion_turno ?? false) && ($turno_filtro_val ?? '0') !== '0'
                                    ? ($turno_filtro_val ?? '0')
                                    : null,
                                'articulo_sku' => $articulo_sku ?? '',
                                'articulo_id' => ($articulo_filtro ?? null) ? $articulo_filtro->id : null,
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
                                $ncVentaId = ($notas_credito_por_factura ?? [])[$r->venta_id] ?? null;
                                $puedeNc = can('generar-nota-credito-gastronomia-facturas-dia', false)
                                    && $v
                                    && (float) ($v->total ?? 0) >= 0.01
                                    && $ncVentaId === null
                                    && (! ($requiere_habilitacion_turno ?? false) || ($turno_habilitado ?? false));
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
                                <td class="facturas-dia-tabla-acciones text-nowrap">
                                    @if (can('ver-factura-gastronomia', false))
                                        <a href="{{ route('gastronomia_facturas_dia_ver', $verParams) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('gastronomia_facturas_dia_ver', $verParams).'#tab-detalle' }}" class="btn-accion-tabla tooltipsC" title="Ítems e insumos">
                                            <i class="fas fa-boxes text-info"></i>
                                        </a>
                                    @endif
                                    @if (can('cambiar-medio-pago-gastronomia-facturas-dia', false) && $v && $cobDirecta)
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
                                        <a href="{{ route('gastronomia_facturas_dia_ver', ['ventaId' => $ncVentaId]) }}"
                                           class="btn-accion-tabla tooltipsC"
                                           title="Ver nota de crédito generada">
                                            <i class="fas fa-undo text-muted"></i>
                                        </a>
                                    @endif
                                    @if ($v)
                                        @if ($v->tiene_canje_premio ?? false)
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-fd-canjes-premio"
                                                data-venta-id="{{ $v->id }}"
                                                data-placement="left"
                                                title="Canjes de premios Wigos">
                                                <i class="fa fa-gift text-warning"></i>
                                            </button>
                                        @endif
                                        @if ($v->tiene_canje_fidelidad ?? false)
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-fd-canjes-fidelidad"
                                                data-venta-id="{{ $v->id }}"
                                                data-placement="left"
                                                title="Canje fidelidad (tarjeta Wigos)">
                                                <i class="fa fa-id-card text-warning"></i>
                                            </button>
                                        @endif
                                        @if ($v->tiene_ticket_tarjeta ?? false)
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-fd-tickets-tarjeta"
                                                data-venta-id="{{ $v->id }}"
                                                data-placement="left"
                                                title="Tickets tarjeta canjeados">
                                                <i class="fa fa-barcode text-info"></i>
                                            </button>
                                        @endif
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

@include('ventas.gastronomia.facturas_dia.partials.modal_generar_nc')
@if (can('cambiar-medio-pago-gastronomia-facturas-dia', false))
    @include('ventas.gastronomia.facturas_dia.partials.modal_cambiar_medio_pago')
    @include('includes.caja.modalconsultacuentacaja')
@endif

<div class="modal fade" id="modal-fd-tickets-tarjeta" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Tickets tarjeta canjados</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="fd-tickets-tarjeta-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Movimiento</th>
                                <th>Nº ticket</th>
                                <th>Documento</th>
                                <th class="text-right">Importe</th>
                                <th>Fecha emisión</th>
                                <th>Canje ERP</th>
                            </tr>
                        </thead>
                        <tbody id="fd-tickets-tarjeta-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-fd-canjes-fidelidad" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Canje fidelidad — tarjeta Wigos</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="fd-canjes-fidelidad-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nro. tarjeta</th>
                                <th>Trackdata</th>
                                <th>DNI</th>
                                <th>Titular</th>
                                <th>Categoría</th>
                                <th>SKU</th>
                                <th>Artículo canjeado</th>
                                <th>Fecha canje</th>
                            </tr>
                        </thead>
                        <tbody id="fd-canjes-fidelidad-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-fd-canjes-premio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Canjes de premios Wigos</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="fd-canjes-premio-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Cupón</th>
                                <th>SKU</th>
                                <th>Artículo</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Puntos</th>
                                <th>Cliente Wigos</th>
                                <th>Documento</th>
                                <th>Fecha canje</th>
                            </tr>
                        </thead>
                        <tbody id="fd-canjes-premio-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
