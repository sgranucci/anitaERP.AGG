@extends("theme.$theme.layout")
@section('titulo')
    Movimientos Interbanking persistidos
@endsection

@section("scripts")
<script src="{{ asset("assets/pages/scripts/admin/index.js") }}" type="text/javascript"></script>
@if (can('sincronizar-interbanking-movimientos', false))
<script type="text/javascript">
$(function () {
    var $panel = $('#ib-movimientos-sync-panel');
    var $btn = $('#ib-btn-toggle-sync-movimientos');
    var labelAbrir = '<i class="fa fa-cloud-download"></i> Consultar Interbanking y guardar movimientos…';
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
});
</script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos persistidos</h3>
                <div class="card-tools">
                    <a href="{{ route('interbanking') }}" class="btn btn-tool btn-sm">Saldos en vivo</a>
                    @if (can('listar-saldos-interbanking-historico', false))
                        <a href="{{ route('interbanking_saldos_historicos') }}" class="btn btn-tool btn-sm">Saldos históricos</a>
                    @endif
                    @if (can('listar-interbanking-transferencias-persistidas', false))
                        <a href="{{ route('interbanking_transferencias_persistidas') }}" class="btn btn-tool btn-sm">Transferencias persistidas</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                    <div class="d-flex flex-wrap align-items-end mb-2 mr-2">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_interbanking_movimientos_historicos',
                            'queryparams' => request()->only(['empresa_id', 'fecha_desde', 'fecha_hasta', 'currency', 'movement_type', 'account_number', 'bank_number']),
                        ])
                    </div>
                    <form method="get" action="{{ route('interbanking_movimientos_persistidos') }}" class="d-flex flex-wrap align-items-end justify-content-end ml-auto">
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
                            <label for="fecha_desde" class="mr-1">Proceso desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $fechaDesde->format('Y-m-d') }}">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="fecha_hasta" class="mr-1">Proceso hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $fechaHasta->format('Y-m-d') }}">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="currency" class="mr-1">Moneda</label>
                            <select name="currency" id="currency" class="form-control">
                                <option value="">Todas</option>
                                <option value="ARS" @selected(request('currency') === 'ARS')>ARS</option>
                                <option value="USD" @selected(request('currency') === 'USD')>USD</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="movement_type" class="mr-1">Tipo API</label>
                            <select name="movement_type" id="movement_type" class="form-control">
                                <option value="">Todos</option>
                                <option value="dia" @selected(request('movement_type') === 'dia')>Día</option>
                                <option value="diferidos" @selected(request('movement_type') === 'diferidos')>Diferidos</option>
                                <option value="anteriores" @selected(request('movement_type') === 'anteriores')>Anteriores</option>
                                <option value="zughus" @selected(request('movement_type') === 'zughus')>ZUGHUS</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="account_number" class="mr-1">Nº cuenta</label>
                            <input type="text" name="account_number" id="account_number" class="form-control"
                                value="{{ request('account_number') }}" placeholder="Contiene…">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="bank_number" class="mr-1">Nº banco</label>
                            <input type="text" name="bank_number" id="bank_number" class="form-control"
                                value="{{ request('bank_number') }}" placeholder="3 dígitos">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Filtrar listado</button>
                    </form>
                </div>

                @if (can('sincronizar-interbanking-movimientos', false))
                <div class="alert alert-light border mb-3 py-2">
                    <p class="small text-muted mb-2">
                        <strong>Filtro del listado:</strong> busca únicamente movimientos ya guardados en el ERP (no consulta Interbanking en ese momento).
                        Para leer movimientos en la API de Interbanking y almacenarlos en la base local, use el botón siguiente; es un proceso aparte del filtro.
                    </p>
                    <button type="button"
                        class="btn btn-outline-success btn-sm"
                        id="ib-btn-toggle-sync-movimientos"
                        aria-expanded="false"
                        aria-controls="ib-movimientos-sync-panel">
                        <i class="fa fa-cloud-download"></i> Consultar Interbanking y guardar movimientos…
                    </button>
                </div>
                <div class="card card-outline card-secondary mb-3 d-none" id="ib-movimientos-sync-panel">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Lectura en Interbanking (API) y persistencia</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Consulta la API de Interbanking con los parámetros indicados y guarda o actualiza los movimientos en la tabla local. No modifica el filtro del listado superior.</p>
                        <form method="post" action="{{ route('interbanking_movimientos_sincronizar') }}" class="form-row align-items-end flex-wrap">
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
                                <label for="sync_account_number">Nº cuenta</label>
                                <input type="text" name="account_number" id="sync_account_number" class="form-control" required
                                    value="{{ $prefill['account_number'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_bank_number">Banco</label>
                                <input type="text" name="bank_number" id="sync_bank_number" class="form-control" maxlength="8" required
                                    value="{{ $prefill['bank_number'] ?? '' }}" placeholder="011">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_account_type">Tipo</label>
                                <select name="account_type" id="sync_account_type" class="form-control">
                                    <option value="CC" @selected(($prefill['account_type'] ?? 'CC') === 'CC')>CC</option>
                                    <option value="CA" @selected(($prefill['account_type'] ?? '') === 'CA')>CA</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_currency">Moneda</label>
                                <select name="currency" id="sync_currency" class="form-control">
                                    <option value="ARS" @selected(($prefill['currency'] ?? 'ARS') === 'ARS')>ARS</option>
                                    <option value="USD" @selected(($prefill['currency'] ?? '') === 'USD')>USD</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="sync_movement_type">Consulta API</label>
                                <select name="movement_type" id="sync_movement_type" class="form-control" required>
                                    @php $mtSync = request('movement_type', 'dia'); @endphp
                                    <option value="dia" @selected($mtSync === 'dia')>Día</option>
                                    <option value="diferidos" @selected($mtSync === 'diferidos')>Diferidos</option>
                                    <option value="anteriores" @selected($mtSync === 'anteriores')>Anteriores</option>
                                    <option value="zughus" @selected($mtSync === 'zughus')>ZUGHUS</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_date_since">Desde</label>
                                <input type="date" name="date_since" id="sync_date_since" class="form-control"
                                    value="{{ request('date_since') }}">
                            </div>
                            <div class="form-group col-md-1">
                                <label for="sync_date_until">Hasta</label>
                                <input type="date" name="date_until" id="sync_date_until" class="form-control"
                                    value="{{ request('date_until') }}">
                            </div>
                            <div class="form-group col-md-1 mb-0">
                                <button type="submit" class="btn btn-success btn-block">Sincronizar</button>
                            </div>
                        </form>
                        <p class="text-muted small mb-0 mt-2">Descarga paginada desde la API (mismo criterio que en saldos en vivo) y guarda/actualiza en base. Para <strong>anteriores</strong> / <strong>zughus</strong> suele ser necesario indicar fechas.</p>
                    </div>
                </div>
                @endif

                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Fecha proceso</th>
                                <th>Empresa</th>
                                <th>Banco</th>
                                <th>Cuenta</th>
                                <th>Moneda</th>
                                <th>Tipo API</th>
                                <th>D/C</th>
                                <th class="text-right">Importe</th>
                                <th>Descripción</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $r)
                            <tr>
                                <td>{{ $r->process_date ? $r->process_date->format('d/m/Y H:i') : '—' }}</td>
                                <td>{{ $r->empresa->nombre ?? '' }}</td>
                                <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                                <td>{{ $r->account_number }}</td>
                                <td>{{ $r->currency }}</td>
                                <td>{{ $r->movement_type }}</td>
                                <td>{{ $r->debit_credit_type }}</td>
                                <td class="text-right">{{ number_format((float) $r->amount, 2) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) ($r->code_description_bank ?? ''), 80) }}</td>
                                <td>{{ $r->voucher_number ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No hay movimientos persistidos para el criterio.@if (can('sincronizar-interbanking-movimientos', false)) Puede traer datos desde Interbanking con el botón «Consultar Interbanking y guardar movimientos».@endif</td>
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
@endsection
