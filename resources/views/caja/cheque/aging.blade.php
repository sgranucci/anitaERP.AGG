@extends("theme.$theme.layout")
@section('titulo')
    Aging cheques en cartera
@endsection

@section('scripts')
@if ($puede_depositar_cheque ?? false)
<script>
window.chequeDepositoUrls = {
    depositar: @json(url('caja/cheque/:id/depositar')),
    depositarMasivo: @json(route('depositar_masivo_cheque'))
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/deposito.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
@php
    $filtrosQuery = $filtrosQuery ?? [];
    $puedeEditarCheque = can('editar-cheque', false);
    $puedeEditarCliente = can('editar-clientes', false) || can('editar-cliente', false);
    $puedeDepositar = $puede_depositar_cheque ?? false;
    $paraDepositarActiva = ! empty($filtros['para_depositar']);
    $urlAging = function (array $extra = []) use ($filtrosQuery) {
        $q = $filtrosQuery;
        unset($q['para_depositar'], $q['bucket']);
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $q[$k] = $v;
        }

        return route('aging_cheque_cartera', $q);
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Aging — cheques de terceros en cartera</h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    <a href="{{ route('cheque', ['cartera' => 1]) }}" class="btn btn-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('aging_cheque_cartera') }}" class="mb-3" id="form-aging-cheque">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-0">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Hasta (fecha cheque ≤)</label>
                            <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? $hasta }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-0">Buscar (nro / cliente / banco)</label>
                            <input type="text" name="texto" value="{{ $filtros['texto'] ?? '' }}" class="form-control form-control-sm" placeholder="Texto…">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="aging-para-depositar"
                                       name="para_depositar" value="1" @checked($paraDepositarActiva)>
                                <label class="custom-control-label small" for="aging-para-depositar">Solo para depositar</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <button type="submit" class="btn btn-info btn-sm">Consultar</button>
                        </div>
                    </div>
                    <input type="hidden" name="bucket" id="aging-bucket" value="{{ $filtros['bucket'] ?? '' }}">
                    <div class="mb-2">
                        <a href="{{ $urlAging(['para_depositar' => 1]) }}"
                           class="btn btn-sm mr-1 mb-1 {{ $paraDepositarActiva ? 'btn-info' : 'btn-outline-info' }}">
                            Para depositar (pago ≤ fecha)
                        </a>
                        <button type="submit" class="btn btn-sm mr-1 mb-1 {{ (! $paraDepositarActiva && ($filtros['bucket'] ?? '') === '') ? 'btn-primary' : 'btn-outline-secondary' }}"
                                onclick="document.getElementById('aging-bucket').value=''; document.getElementById('aging-para-depositar').checked=false;">
                            Todos aging
                        </button>
                        @foreach ($resumen['buckets'] as $key => $b)
                            <button type="submit" class="btn btn-sm mr-1 mb-1 {{ (! $paraDepositarActiva && ($filtros['bucket'] ?? '') === $key) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    onclick="document.getElementById('aging-bucket').value='{{ $key }}'; document.getElementById('aging-para-depositar').checked=false;">
                                {{ $b['label'] }} ({{ $b['cantidad'] }})
                            </button>
                        @endforeach
                    </div>
                </form>

                @if ($paraDepositarActiva)
                <p class="small text-muted mb-2">
                    Mostrando CHT en cartera con <strong>fecha de cheque ≤ {{ $hasta }}</strong>.
                    Marcá checkboxes y usá <strong>Depositar sel.</strong>
                </p>
                @endif

                <div class="row mb-3">
                    @foreach ($resumen['buckets'] as $key => $b)
                        <div class="col-md-2 col-sm-4 mb-2">
                            <div class="border rounded p-2 h-100 {{ (! $paraDepositarActiva && ($filtros['bucket'] ?? '') === $key) ? 'border-primary' : '' }}">
                                <div class="small text-muted">{{ $b['label'] }}</div>
                                <div><strong>{{ $b['cantidad'] }}</strong> cheques</div>
                                <div>{{ number_format($b['monto'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <p class="mb-0">
                        Filtrado: <strong>{{ $resumen['total_cantidad'] }}</strong> —
                        {{ number_format($resumen['total_monto'], 2, ',', '.') }}
                        (ref. {{ $hasta }})
                        @if (($resumen['total_cantidad_cartera'] ?? null) !== null && (($filtros['bucket'] ?? '') !== '' || $paraDepositarActiva))
                            <span class="text-muted small">· cartera: {{ $resumen['total_cantidad_cartera'] }}</span>
                        @endif
                    </p>
                    <div class="d-flex flex-wrap align-items-center">
                        @if ($puedeDepositar)
                        <button type="button" id="btn-deposito-masivo" class="btn btn-success btn-sm mr-2 disabled"
                                aria-disabled="true"
                                title="Seleccioná cheques con el checkbox y luego depositá"
                                style="opacity:.55;">
                            <i class="fa fa-university"></i> Depositar sel.
                        </button>
                        @endif
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_aging_cheque',
                            'queryparams' => $filtrosQuery,
                            'variant' => 'compact',
                        ])
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @if ($puedeDepositar)
                                <th class="text-center" style="width:2.2rem;">
                                    <input type="checkbox" id="cheque-select-all" title="Seleccionar página" />
                                </th>
                                @endif
                                <th>ID</th>
                                <th>Número</th>
                                <th>Int.</th>
                                <th>Pago</th>
                                <th>Días</th>
                                <th>Bucket</th>
                                <th>Monto</th>
                                <th>Banco</th>
                                <th>Cliente</th>
                                <th>Empresa</th>
                                @if ($puedeDepositar)
                                <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginator as $f)
                                <tr>
                                    @if ($puedeDepositar)
                                    <td class="text-center">
                                        @if ($f['puede_depositar'] ?? false)
                                            <input type="checkbox" class="cheque-select-row" value="{{ $f['id'] }}" />
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
                                    <td>{{ $f['fechapago'] }}</td>
                                    <td class="text-right">{{ $f['dias'] }}</td>
                                    <td>{{ $f['bucket_label'] }}</td>
                                    <td class="text-right">{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
                                    <td>{{ $f['banco'] }}</td>
                                    <td>
                                        @if (!empty($f['cliente_id']) && $puedeEditarCliente)
                                            <a class="text-primary" href="{{ route('editar_cliente', $f['cliente_id']) }}" target="_blank" rel="noopener">{{ $f['cliente'] }}</a>
                                        @else
                                            {{ $f['cliente'] }}
                                        @endif
                                    </td>
                                    <td>{{ $f['empresa'] }}</td>
                                    @if ($puedeDepositar)
                                    <td>
                                        @if ($f['puede_depositar'] ?? false)
                                            <button type="button"
                                                    class="btn-accion-tabla tooltipsC btn-deposito-cheque"
                                                    title="Depositar"
                                                    data-cheque-id="{{ $f['id'] }}"
                                                    data-cheque-ref="{{ $f['numerocheque'] }} / {{ $f['banco'] }}">
                                                <i class="fa fa-university text-primary"></i>
                                            </button>
                                        @endif
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="12" class="text-center text-muted">Sin cheques en cartera.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($paginator->total() > 0)
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="small text-muted">
                            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
                        </div>
                        {{ $paginator->appends($filtrosQuery)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@if ($puedeDepositar)
    @include('caja.cheque.modal_deposito')
@endif
@endsection
