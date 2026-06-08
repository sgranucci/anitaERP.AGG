@extends("theme.$theme.layout")
@section('titulo')
    Consulta de saldos Interbanking (histórico)
@endsection

@section("scripts")
<script src="{{ asset("assets/pages/scripts/admin/index.js") }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Saldos diarios persistidos</h3>
                <div class="card-tools">
                    @if (can('listar-interbanking-movimientos-persistidos', false))
                        <a href="{{ route('interbanking_movimientos_persistidos') }}" class="btn btn-tool btn-sm">Movimientos persistidos</a>
                    @endif
                    <a href="{{ route('interbanking') }}" class="btn btn-tool btn-sm">Volver a saldos en vivo</a>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                    <div class="d-flex flex-wrap align-items-end mb-2 mr-2">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_interbanking_saldos_historicos',
                            'queryparams' => request()->only(['empresa_id', 'fecha_desde', 'fecha_hasta', 'currency', 'account_number']),
                        ])
                    </div>
                    <form method="get" action="{{ route('interbanking_saldos_historicos') }}" class="d-flex flex-wrap align-items-end justify-content-end ml-auto">
                        @include('includes.listado.filtro_empresa_asignada_campo', [
                            'empresas' => $empresas,
                            'empresa_id' => $empresaId ?? null,
                            'label_class' => 'mr-1',
                            'permite_todas' => true,
                        ])
                        <div class="form-group mr-2 mb-2">
                            <label for="fecha_desde" class="mr-1">Desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $fechaDesde->format('Y-m-d') }}">
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <label for="fecha_hasta" class="mr-1">Hasta</label>
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
                            <label for="account_number" class="mr-1">Nº cuenta</label>
                            <input type="text" name="account_number" id="account_number" class="form-control"
                                value="{{ request('account_number') }}" placeholder="Contiene…">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Filtrar</button>
                    </form>
                </div>

                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Empresa</th>
                                <th>Banco</th>
                                <th>Moneda</th>
                                <th>Cuenta</th>
                                <th>Tipo</th>
                                <th>Etiqueta</th>
                                <th>Nombre</th>
                                <th class="text-right">Débitos día</th>
                                <th class="text-right">Créditos día</th>
                                <th class="text-right">Saldo día</th>
                                <th class="text-right">Balance actual (snapshot)</th>
                                @if (can('listar-interbanking-movimientos-persistidos', false))
                                    <th class="width100" data-orderable="false">Movimientos</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $r)
                            <tr>
                                <td>{{ $r->fecha->format('d/m/Y') }}</td>
                                <td>{{ $r->empresa->nombre ?? '' }}</td>
                                <td>{{ $r->getAttribute('nombrebanco') ?? '' }}</td>
                                <td>{{ $r->currency }}</td>
                                <td>{{ $r->account_number }}</td>
                                <td>{{ $r->account_type }}</td>
                                <td>{{ $r->account_label }}</td>
                                <td>{{ $r->account_name }}</td>
                                <td class="text-right">{{ number_format((float) $r->total_debits, 2) }}</td>
                                <td class="text-right">{{ number_format((float) $r->total_credits, 2) }}</td>
                                <td class="text-right">{{ number_format((float) $r->day_balance, 2) }}</td>
                                <td class="text-right">
                                    @if($r->current_operating_balance !== null)
                                        {{ number_format((float) $r->current_operating_balance, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                @if (can('listar-interbanking-movimientos-persistidos', false))
                                    <td class="text-center">
                                        <a href="{{ route('interbanking_movimientos_persistidos', array_filter([
                                            'empresa_id' => $r->empresa_id,
                                            'account_number' => $r->account_number,
                                            'bank_number' => $r->bank_number,
                                            'currency' => $r->currency,
                                            'account_type' => $r->account_type,
                                            'fecha_desde' => $r->fecha->format('Y-m-d'),
                                            'fecha_hasta' => $r->fecha->copy()->addDays(14)->format('Y-m-d'),
                                        ])) }}" class="btn btn-sm btn-outline-secondary" title="Ver movimientos persistidos de esta cuenta">Persistidos</a>
                                    </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ can('listar-interbanking-movimientos-persistidos', false) ? '13' : '12' }}" class="text-center text-muted">No hay registros para el criterio seleccionado.</td>
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
