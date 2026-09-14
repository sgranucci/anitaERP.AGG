@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'cheque';
    $carteraActiva = ! empty($filtros['cartera']);
    $paraDepositarActiva = ! empty($filtros['para_depositar']);
    $paraDepositarHasta = (string) ($filtros['para_depositar_hasta'] ?? date('Y-m-d'));
    if ($paraDepositarHasta === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paraDepositarHasta)) {
        $paraDepositarHasta = date('Y-m-d');
    }
    $origenActivo = (string) ($filtros['origen'] ?? '');
    $estadoActivo = (string) ($filtros['estado'] ?? '');

    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route($rutaIndex, $q);
    };

    $urlQuick = function (array $extra) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['cartera'], $q['para_depositar'], $q['para_depositar_hasta'], $q['origen'], $q['estado']);
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $q[$k] = $v;
        }

        return route($rutaIndex, $q);
    };

    $esTodos = ! $carteraActiva && ! $paraDepositarActiva && $origenActivo === '' && $estadoActivo === '';
@endphp
<div class="card-body py-2 border-bottom bg-white">
    @if (($empresa_query ?? collect())->count() > 1)
    <div class="d-flex flex-wrap align-items-center mb-2">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas mis empresas
                </a>
            </div>
        </div>
    </div>
    @endif
    <div class="d-flex flex-wrap align-items-center">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Vista rápida:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtros rápidos de cheques">
                <a href="{{ $urlQuick(['para_depositar' => 1, 'para_depositar_hasta' => $paraDepositarHasta]) }}"
                   class="btn {{ $paraDepositarActiva ? 'btn-info' : 'btn-outline-info' }}"
                   title="CHT en cartera con fecha de cheque ≤ fecha de corte">
                    Para depositar
                </a>
                <a href="{{ $urlQuick(['cartera' => 1]) }}"
                   class="btn {{ $carteraActiva ? 'btn-success' : 'btn-outline-success' }}">
                    Cartera
                </a>
                <a href="{{ $urlQuick([]) }}"
                   class="btn {{ $esTodos ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Todos
                </a>
                <a href="{{ $urlQuick(['origen' => 'E']) }}"
                   class="btn {{ (! $carteraActiva && ! $paraDepositarActiva && $origenActivo === 'E') ? 'btn-primary' : 'btn-outline-primary' }}">
                    Emitidos
                </a>
                <a href="{{ $urlQuick(['origen' => 'R']) }}"
                   class="btn {{ (! $carteraActiva && ! $paraDepositarActiva && $origenActivo === 'R' && $estadoActivo === '') ? 'btn-info' : 'btn-outline-info' }}">
                    Recibidos
                </a>
                <a href="{{ $urlQuick(['estado' => 'R']) }}"
                   class="btn {{ (! $carteraActiva && ! $paraDepositarActiva && $estadoActivo === 'R') ? 'btn-danger' : 'btn-outline-danger' }}">
                    Rechazados
                </a>
            </div>
        </div>
    </div>
    @if ($paraDepositarActiva)
    <form method="get" action="{{ route('cheque') }}" class="form-inline mt-2 mb-0">
        @foreach ($baseQ as $k => $v)
            @if (! in_array($k, ['para_depositar', 'para_depositar_hasta'], true) && $v !== null && $v !== '')
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach
        <input type="hidden" name="para_depositar" value="1">
        <label class="small text-muted mr-2 mb-0" for="para_depositar_hasta">Fecha cheque ≤</label>
        <input type="date" name="para_depositar_hasta" id="para_depositar_hasta"
               value="{{ $paraDepositarHasta }}"
               class="form-control form-control-sm mr-2">
        <button type="submit" class="btn btn-info btn-sm">Aplicar fecha</button>
        @if ($puede_depositar_cheque ?? false)
        <span class="small text-muted ml-3">
            Marcá checkboxes y usá <strong>Depositar sel.</strong>
        </span>
        @endif
    </form>
    @endif
</div>
