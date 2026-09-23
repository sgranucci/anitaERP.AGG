@extends("theme.$theme.layout")
@section('titulo')
    Conciliación depósitos CHT
@endsection

@section('scripts')
@if ($puede_acreditar ?? false)
<script>
window.chequeAcreditarUrls = {
    acreditar: @json(url('caja/cheque/:id/acreditar')),
    acreditarMasivo: @json(route('acreditar_masivo_cheque'))
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/conciliacion.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $filtrosQuery = $filtrosQuery ?? [];
    $puedeEditarCheque = can('editar-cheque', false);
    $puedeEditarCliente = can('editar-clientes', false) || can('editar-cliente', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conciliación depósitos — tránsito / acreditados</h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    @if ($puede_acreditar ?? false)
                    <button type="button" id="btn-acreditar-masivo" class="btn btn-outline-success btn-sm mr-2" disabled>
                        <i class="fa fa-check"></i> Acreditar sel.
                    </button>
                    @endif
                    <a href="{{ route('historial_deposito_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Historial boletas de depósito">
                        <i class="fa fa-university"></i> Historial depósitos
                    </a>
                    <a href="{{ route('cheque') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('conciliacion_deposito_cheque') }}" class="mb-3" id="form-conciliacion-cheque">
                    <div class="form-row align-items-end">
                        @if (($empresa_query ?? collect())->count() > 1)
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Cuenta depósito</label>
                            <select name="cuentacaja_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($cuentacaja_query ?? [] as $cc)
                                    <option value="{{ $cc->id }}" @selected((int)($filtros['cuentacaja_id'] ?? 0) === (int)$cc->id)>{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Boleta</label>
                            <input type="text" name="boleta" value="{{ $filtros['boleta'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Desde dep.</label>
                            <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Hasta dep.</label>
                            <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Buscar</label>
                            <input type="text" name="texto" value="{{ $filtros['texto'] ?? '' }}" class="form-control form-control-sm" placeholder="Nro / cliente…">
                        </div>
                    </div>
                    <input type="hidden" name="estado" id="conc-estado" value="{{ $filtros['estado'] ?? '' }}">
                    <div class="mb-2">
                        <button type="submit" class="btn btn-sm mr-1 mb-1 {{ ($filtros['estado'] ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="document.getElementById('conc-estado').value='';">
                            Todos
                        </button>
                        @foreach ($resumen['buckets'] as $key => $b)
                            <button type="submit" class="btn btn-sm mr-1 mb-1 {{ ($filtros['estado'] ?? '') === $key ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="document.getElementById('conc-estado').value='{{ $key }}';">
                                {{ $b['label'] }} ({{ $b['cantidad'] }})
                            </button>
                        @endforeach
                        <button type="submit" class="btn btn-info btn-sm mb-1 ml-2">Consultar</button>
                    </div>
                </form>

                <div class="row mb-3">
                    @foreach ($resumen['buckets'] as $key => $b)
                        <div class="col-md-4 col-sm-6 mb-2">
                            <div class="border rounded p-2 h-100 {{ ($filtros['estado'] ?? '') === $key ? 'border-primary' : '' }}">
                                <div class="small text-muted">{{ $b['label'] }}</div>
                                <div><strong>{{ $b['cantidad'] }}</strong> cheques</div>
                                <div>{{ number_format($b['monto'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (!empty($resumen['boletas']))
                <div class="mb-3 small">
                    <strong>Por boleta (top):</strong>
                    @foreach (array_slice($resumen['boletas'], 0, 8) as $bol)
                        <span class="badge badge-light border mr-1 mb-1">
                            {{ $bol['boleta'] }}: {{ $bol['cantidad'] }} /
                            {{ number_format($bol['monto'], 2, ',', '.') }}
                        </span>
                    @endforeach
                </div>
                @endif

                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <p class="mb-0">
                        Filtrado: <strong>{{ $resumen['total_cantidad'] }}</strong> —
                        {{ number_format($resumen['total_monto'], 2, ',', '.') }}
                    </p>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_conciliacion_deposito_cheque',
                        'queryparams' => $filtrosQuery,
                        'variant' => 'compact',
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @if ($puede_acreditar ?? false)
                                <th class="text-center" style="width:2.2rem;">
                                    <input type="checkbox" id="conc-select-all" title="Seleccionar página" />
                                </th>
                                @endif
                                <th>ID</th>
                                <th>Número</th>
                                <th>Int.</th>
                                <th>Depósito</th>
                                <th>Acreditación</th>
                                <th>Boleta</th>
                                <th class="text-right">Monto</th>
                                <th>Banco</th>
                                <th>Cliente</th>
                                <th>Cuenta</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginator as $f)
                                <tr>
                                    @if ($puede_acreditar ?? false)
                                    <td class="text-center">
                                        @if ($f['puede_acreditar'] ?? false)
                                            <input type="checkbox" class="conc-select-row" value="{{ $f['id'] }}" />
                                        @endif
                                    </td>
                                    @endif
                                    <td>
                                        @if ($puedeEditarCheque)
                                            <a class="text-primary" href="{{ route('editar_cheque', $f['id']) }}" target="_blank" rel="noopener">{{ $f['id'] }}</a>
                                        @else
                                            {{ $f['id'] }}
                                        @endif
                                    </td>
                                    <td>{{ $f['numerocheque'] }}</td>
                                    <td>{{ $f['nro_interno_anita'] }}</td>
                                    <td>{{ $f['fecha_deposito'] }}</td>
                                    <td>{{ $f['fecha_acreditacion'] }}</td>
                                    <td>{{ $f['nro_boleta'] }}</td>
                                    <td class="text-right">{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
                                    <td>{{ $f['banco'] }}</td>
                                    <td>
                                        @if (!empty($f['cliente_id']) && $puedeEditarCliente)
                                            <a class="text-primary" href="{{ route('editar_cliente', $f['cliente_id']) }}?origen=modal_consulta&vista=consulta" target="_blank" rel="noopener">{{ $f['cliente'] }}</a>
                                        @else
                                            {{ $f['cliente'] }}
                                        @endif
                                    </td>
                                    <td class="small">{{ $f['cuenta'] }}</td>
                                    <td>
                                        <span class="badge badge-{{ $f['estado'] === 'acreditado' ? 'success' : ($f['estado'] === 'rechazado' ? 'danger' : 'warning') }}">
                                            {{ $f['estado_label'] }}
                                        </span>
                                        @if ($f['puede_acreditar'] ?? false)
                                            <button type="button" class="btn-accion-tabla btn-acreditar-cheque" data-cheque-id="{{ $f['id'] }}" title="Acreditar">
                                                <i class="fa fa-check text-success"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="text-center text-muted">Sin depósitos para los filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $paginator->appends($filtrosQuery)->links() }}
            </div>
        </div>
    </div>
</div>
@if ($puede_acreditar ?? false)
<div class="modal fade" id="modalAcreditarCheque" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Acreditar depósito</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="acreditar-modo-masivo" value="0" />
                <p>Cheque(s): <strong id="acreditar-cheque-ref"></strong></p>
                <div class="form-group mb-0">
                    <label for="acreditar_fecha">Fecha acreditación</label>
                    <input type="date" id="acreditar_fecha" class="form-control form-control-sm" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="acreditar_cheque_confirmar">
                    <i class="fa fa-check"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
