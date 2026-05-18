@extends("theme.$theme.layout")
@section('titulo')
    Transferencias Interbanking persistidas
@endsection

@section('styles')
<style>
    .ib-tabla-transferencias-scroll {
        overflow-x: auto;
        max-width: 100%;
    }
    .ib-tabla-transferencias-scroll table.ib-tabla-transferencias {
        width: 100%;
        max-width: 100%;
        table-layout: fixed;
        margin-bottom: 0;
        font-size: 0.8rem;
    }
    .ib-tabla-transferencias-scroll table.ib-tabla-transferencias th,
    .ib-tabla-transferencias-scroll table.ib-tabla-transferencias td {
        padding: 0.35rem 0.4rem;
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ib-tabla-transferencias-scroll td.ib-col-tipo,
    .ib-tabla-transferencias-scroll td.ib-col-denom,
    .ib-tabla-transferencias-scroll td.ib-col-empresa {
        white-space: normal;
        word-break: break-word;
        line-height: 1.25;
    }
    .ib-tabla-transferencias-scroll td.ib-col-cbu {
        font-size: 0.72rem;
        letter-spacing: -0.02em;
    }
    .ib-detalle-etiqueta {
        width: 38%;
        background: #f4f6f9;
        font-weight: 600;
    }
    #ib-detalle-transferencia-body .ib-detalle-transferencia-tabla td {
        word-break: break-word;
    }
</style>
@endsection

@section("scripts")
<script src="{{ asset("assets/pages/scripts/admin/index.js") }}" type="text/javascript"></script>
<script type="text/javascript">
$(function () {
    var urlDetalleTpl = @json(route('interbanking_transferencia_detalle', ['id' => 999999999]));
    urlDetalleTpl = urlDetalleTpl.replace('999999999', '__ID__');

    $(document).on('click', '.ib-ver-detalle-transferencia', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var titulo = $(this).data('titulo') || 'Detalle de transferencia';
        var $modal = $('#ibDetalleTransferenciaModal');
        var $body = $('#ib-detalle-transferencia-body');

        $modal.find('.modal-title').text(titulo);
        $body.html('<p class="text-muted mb-0">Cargando…</p>');
        $modal.modal('show');

        $.get(urlDetalleTpl.replace('__ID__', String(id)), function (resp) {
            if (resp.ok && resp.html) {
                $modal.find('.modal-title').text(resp.titulo || titulo);
                $body.html(resp.html);
            } else {
                $body.html('<p class="text-danger mb-0">No se pudo cargar el detalle.</p>');
            }
        }).fail(function () {
            $body.html('<p class="text-danger mb-0">Error al consultar el detalle.</p>');
        });
    });

    @if (can('sincronizar-interbanking-transferencias', false))
    var $panel = $('#ib-transferencias-sync-panel');
    var $btn = $('#ib-btn-toggle-sync-transferencias');
    var labelAbrir = '<i class="fa fa-cloud-download"></i> Consultar Interbanking y guardar transferencias…';
    var labelCerrar = '<i class="fa fa-times"></i> Ocultar consulta a Interbanking';

    function setPanelAbierto(abierto) {
        $panel.toggleClass('d-none', !abierto);
        $btn.attr('aria-expanded', abierto ? 'true' : 'false');
        $btn.html(abierto ? labelCerrar : labelAbrir);
    }

    $btn.on('click', function () {
        setPanelAbierto($panel.hasClass('d-none'));
    });

    @if (request()->boolean('abrir_sincronizacion') || session()->has('errores'))
    setPanelAbierto(true);
    @endif
    @endif
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Transferencias persistidas</h3>
                <div class="card-tools">
                    <a href="{{ route('interbanking') }}" class="btn btn-tool btn-sm">Saldos en vivo</a>
                    @if (can('listar-interbanking-movimientos-persistidos', false))
                        <a href="{{ route('interbanking_movimientos_persistidos') }}" class="btn btn-tool btn-sm">Movimientos persistidos</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                    <div class="d-flex flex-wrap align-items-end mb-2 mr-2">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_interbanking_transferencias_historicas',
                            'queryparams' => request()->only(['empresa_id', 'fecha_desde', 'fecha_hasta', 'debit_currency', 'currency', 'debit_account_number', 'debit_bank_number']),
                        ])
                    </div>
                    <form method="get" action="{{ route('interbanking_transferencias_persistidas') }}" class="d-flex flex-wrap align-items-end justify-content-end ml-auto">
                        <div class="form-group mr-2 mb-2">
                            <label for="empresa_id" class="mr-1">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control">
                                <option value="">Todas</option>
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}" @selected((int)($empresaId ?? 0) === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="fecha_desde" class="mr-1">Transferencia desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $fechaDesde->format('Y-m-d') }}">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="fecha_hasta" class="mr-1">Transferencia hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $fechaHasta->format('Y-m-d') }}">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="debit_currency" class="mr-1">Mon. filtro débito</label>
                            <select name="debit_currency" id="debit_currency" class="form-control">
                                <option value="">Todas</option>
                                <option value="ARS" @selected(request('debit_currency') === 'ARS')>ARS</option>
                                <option value="USD" @selected(request('debit_currency') === 'USD')>USD</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="currency" class="mr-1">Mon. transferencia</label>
                            <select name="currency" id="currency" class="form-control">
                                <option value="">Todas</option>
                                <option value="ARS" @selected(request('currency') === 'ARS')>ARS</option>
                                <option value="USD" @selected(request('currency') === 'USD')>USD</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="debit_account_number" class="mr-1">Nº cuenta débito</label>
                            <input type="text" name="debit_account_number" id="debit_account_number" class="form-control"
                                value="{{ request('debit_account_number') }}" placeholder="Contiene…">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="debit_bank_number" class="mr-1">Nº banco</label>
                            <input type="text" name="debit_bank_number" id="debit_bank_number" class="form-control"
                                value="{{ request('debit_bank_number') }}" placeholder="3 dígitos">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Filtrar listado</button>
                    </form>
                </div>

                @if (can('sincronizar-interbanking-transferencias', false))
                <div class="alert alert-light border mb-3 py-2">
                    <p class="small text-muted mb-2">
                        <strong>Filtro del listado:</strong> busca únicamente transferencias ya guardadas en el ERP (no consulta Interbanking en ese momento).
                        Para leer comprobantes en la API de Interbanking y almacenarlos en la base local, use el botón siguiente; es un proceso aparte del filtro.
                    </p>
                    <button type="button"
                        class="btn btn-outline-success btn-sm"
                        id="ib-btn-toggle-sync-transferencias"
                        aria-expanded="false"
                        aria-controls="ib-transferencias-sync-panel">
                        <i class="fa fa-cloud-download"></i> Consultar Interbanking y guardar transferencias…
                    </button>
                </div>
                <div class="card card-outline card-secondary mb-3 d-none" id="ib-transferencias-sync-panel">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Lectura en Interbanking (API) y persistencia</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Consulta la API de Interbanking con los parámetros indicados y guarda o actualiza los comprobantes en la tabla local. No modifica el filtro del listado superior.</p>
                        <form method="post" action="{{ route('interbanking_transferencias_sincronizar') }}" class="form-row align-items-end flex-wrap">
                            @csrf
                            <input type="hidden" name="fecha_desde" value="{{ request('fecha_desde', $fechaDesde->format('Y-m-d')) }}">
                            <input type="hidden" name="fecha_hasta" value="{{ request('fecha_hasta', $fechaHasta->format('Y-m-d')) }}">
                            <div class="form-group col-md-2">
                                <label for="sync_empresa_id">Empresa</label>
                                <select name="empresa_id" id="sync_empresa_id" class="form-control" required>
                                    <option value="">—</option>
                                    @foreach ($empresas as $e)
                                        <option value="{{ $e->id }}" @selected((int)($prefill['empresa_id'] ?? 0) === (int)$e->id)>{{ $e->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="sync_debit_account_number">Cuenta débito</label>
                                <input type="text" name="debit_account_number" id="sync_debit_account_number" class="form-control"
                                    value="{{ $prefill['debit_account_number'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_debit_bank_number">Banco</label>
                                <input type="text" name="debit_bank_number" id="sync_debit_bank_number" class="form-control" maxlength="8"
                                    value="{{ $prefill['debit_bank_number'] ?? '' }}" placeholder="011">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_debit_account_type">Tipo</label>
                                <select name="debit_account_type" id="sync_debit_account_type" class="form-control">
                                    <option value="CC" @selected(($prefill['debit_account_type'] ?? 'CC') === 'CC')>CC</option>
                                    <option value="CA" @selected(($prefill['debit_account_type'] ?? '') === 'CA')>CA</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_debit_currency">Moneda</label>
                                <select name="debit_currency" id="sync_debit_currency" class="form-control">
                                    <option value="ARS" @selected(($prefill['debit_currency'] ?? 'ARS') === 'ARS')>ARS</option>
                                    <option value="USD" @selected(($prefill['debit_currency'] ?? '') === 'USD')>USD</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_date_since">Desde</label>
                                <input type="date" name="date_since" id="sync_date_since" class="form-control"
                                    value="{{ request('date_since', $fechaDesde->format('Y-m-d')) }}">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_date_until">Hasta</label>
                                <input type="date" name="date_until" id="sync_date_until" class="form-control"
                                    value="{{ request('date_until', $fechaHasta->format('Y-m-d')) }}">
                            </div>
                            <div class="form-group col-md-1 mb-0">
                                <button type="submit" class="btn btn-success btn-block">Sincronizar</button>
                            </div>
                        </form>
                        <p class="text-muted small mb-0 mt-2">La API admite rangos de hasta 60 días por consulta (máx. 180 días hacia atrás). Indique fechas y, si aplica, la cuenta débito a filtrar.</p>
                    </div>
                </div>
                @endif

                <div class="table-responsive ib-tabla-transferencias-scroll p-0">
                    <table class="table table-sm table-striped table-bordered table-hover ib-tabla-transferencias">
                        <thead>
                            <tr>
                                <th style="width: 7%;">Fecha</th>
                                <th style="width: 4%;">Hora</th>
                                <th style="width: 9%;">Empresa</th>
                                <th style="width: 8%;">Banco</th>
                                <th style="width: 9%;">Tipo</th>
                                <th style="width: 7%;" class="text-right">Importe</th>
                                <th style="width: 3%;">Mon.</th>
                                <th style="width: 11%;">CBU déb.</th>
                                <th style="width: 8%;">Banco créd.</th>
                                <th style="width: 11%;">Denominación</th>
                                <th style="width: 7%;">CUIT</th>
                                <th style="width: 11%;">CBU créd.</th>
                                <th style="width: 7%;">ID</th>
                                <th style="width: 5%;" data-orderable="false">Acc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $r)
                            <tr>
                                <td>{{ $r->request_date ? $r->request_date->format('d/m/Y') : '—' }}</td>
                                <td>{{ $r->request_date ? $r->request_date->format('H:i') : '—' }}</td>
                                <td class="ib-col-empresa" title="{{ $r->empresa->nombre ?? '' }}">{{ $r->empresa->nombre ?? '' }}</td>
                                <td title="{{ $r->getAttribute('nombrebanco') ?? '' }}">{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                                <td class="ib-col-tipo">{{ (string) ($r->transfer_type_description ?? $r->transfer_type_code ?? '') }}</td>
                                <td class="text-right">{{ number_format((float) $r->amount, 2) }}</td>
                                <td>{{ $r->currency ?? '—' }}</td>
                                <td class="ib-col-cbu" title="{{ $r->debito_cbu ?? '' }}">{{ $r->debito_cbu ?? '—' }}</td>
                                <td title="{{ $r->credito_banco ?? '' }}">{{ $r->credito_banco ?? '—' }}</td>
                                <td class="ib-col-denom" title="{{ $r->credito_denominacion ?? '' }}">{{ $r->credito_denominacion ?? '—' }}</td>
                                <td>{{ $r->credito_cuit ?? '—' }}</td>
                                <td class="ib-col-cbu" title="{{ $r->credito_cbu ?? '' }}">{{ $r->credito_cbu ?? '—' }}</td>
                                <td>{{ $r->transfer_id ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('interbanking_transferencia_comprobante', ['id' => $r->id]) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn-accion-tabla tooltipsC"
                                        title="Imprimir comprobante (PDF)">
                                        <i class="fa fa-print text-primary"></i>
                                    </a>
                                    <button type="button"
                                        class="btn-accion-tabla ib-ver-detalle-transferencia tooltipsC"
                                        data-id="{{ $r->id }}"
                                        data-titulo="Transferencia #{{ $r->transfer_id ?? $r->id }}"
                                        title="Ver datos completos de cuentas">
                                        <i class="fa fa-info-circle text-info"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">No hay transferencias persistidas para el criterio.@if (can('sincronizar-interbanking-transferencias', false)) Puede traer datos desde Interbanking con el botón «Consultar Interbanking y guardar transferencias».@endif</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $registros->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.interbanking.partials.modal_detalle_transferencia')
@endsection
