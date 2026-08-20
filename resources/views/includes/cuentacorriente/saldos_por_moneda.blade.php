@php
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;

    $saldosPorMoneda = $saldosPorMoneda ?? [];
    $equivalentePesos = $equivalentePesos ?? ['saldo_cc' => 0.0, 'deuda' => 0.0, 'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal()];
    $monedaId = $monedaId ?? null;
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $queryBase = $queryFiltros ?? [];
    $rutaSaldos = $ruta ?? '';
    $idTercero = $id ?? null;
    $hayVarias = count($saldosPorMoneda) > 1;
    $abrevLocal = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();

    $urlConsulta = static function (array $extra) use ($queryBase, $rutaSaldos, $idTercero) {
        return route($rutaSaldos, array_merge(['id' => $idTercero], $queryBase, $extra));
    };
@endphp
<div class="d-flex flex-wrap align-items-center mb-3" style="gap: 0.75rem 1.25rem;">
    @if ($saldosPorMoneda !== [] && $hayVarias)
        <div class="d-flex flex-wrap align-items-center">
            <span class="text-muted small mr-2">Ver moneda:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de moneda">
                <a href="{{ $urlConsulta(['moneda_id' => 'todas']) }}"
                   class="btn {{ $monedaId === null ? 'btn-info' : 'btn-outline-info' }}">
                    Todas
                </a>
                @foreach ($saldosPorMoneda as $saldoMoneda)
                    <a href="{{ $urlConsulta(['moneda_id' => $saldoMoneda['moneda_id']]) }}"
                       class="btn {{ $monedaId === (int) $saldoMoneda['moneda_id'] ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $saldoMoneda['abreviatura'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
    @if ($saldosPorMoneda !== [])
        <div class="d-flex flex-wrap align-items-center">
            <span class="text-muted small mr-2">Expresar grilla:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Expresión de importes">
                <a href="{{ $urlConsulta(['expresion' => CuentacorrienteSaldosPorMoneda::EXPRESION_ORIGEN]) }}"
                   class="btn {{ ! $enPesos ? 'btn-info' : 'btn-outline-info' }}">
                    Moneda origen
                </a>
                <a href="{{ $urlConsulta(['expresion' => CuentacorrienteSaldosPorMoneda::EXPRESION_PESOS]) }}"
                   class="btn {{ $enPesos ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $abrevLocal }} (TC comprobante)
                </a>
            </div>
        </div>
    @endif
</div>
<div class="row mb-3">
    @if ($saldosPorMoneda === [])
        <div class="col-md-6">
            <div class="info-box mb-2 bg-light">
                <span class="info-box-icon bg-secondary"><i class="fas fa-balance-scale"></i></span>
                <span class="info-box-content">
                    <span class="info-box-text">Cuenta corriente</span>
                    <span class="info-box-number">Sin movimientos</span>
                </span>
            </div>
        </div>
    @else
        @foreach ($saldosPorMoneda as $saldoMoneda)
            @php
                $activa = ! $enPesos && (
                    $monedaId === (int) $saldoMoneda['moneda_id']
                    || (! $hayVarias && $monedaId === null)
                );
            @endphp
            <div class="{{ $hayVarias ? 'col-lg-4 col-md-6' : 'col-md-6' }} mb-2">
                <div class="info-box mb-0 {{ $activa ? 'bg-info' : 'bg-light' }}">
                    <span class="info-box-icon {{ $activa ? 'bg-white' : 'bg-info' }}">
                        <i class="fas fa-balance-scale"></i>
                    </span>
                    <span class="info-box-content">
                        <span class="info-box-text {{ $activa ? 'text-white' : '' }}">
                            Saldo {{ $saldoMoneda['abreviatura'] }}
                        </span>
                        <span class="info-box-number {{ $activa ? 'text-white' : '' }}">
                            {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) $saldoMoneda['saldo_cc'], $saldoMoneda['abreviatura']) }}
                        </span>
                        <span class="small {{ $activa ? 'text-white' : 'text-muted' }}">
                            Deuda: {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) $saldoMoneda['deuda'], $saldoMoneda['abreviatura']) }}
                        </span>
                    </span>
                </div>
            </div>
        @endforeach
        <div class="{{ $hayVarias ? 'col-lg-4 col-md-6' : 'col-md-6' }} mb-2">
            <div class="info-box mb-0 {{ $enPesos ? 'bg-success' : 'bg-light' }}">
                <span class="info-box-icon {{ $enPesos ? 'bg-white' : 'bg-success' }}">
                    <i class="fas fa-exchange-alt"></i>
                </span>
                <span class="info-box-content">
                    <span class="info-box-text {{ $enPesos ? 'text-white' : '' }}">
                        Equivalente {{ $abrevLocal }} (TC comprobante)
                    </span>
                    <span class="info-box-number {{ $enPesos ? 'text-white' : '' }}">
                        {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) ($equivalentePesos['saldo_cc'] ?? 0), $abrevLocal) }}
                    </span>
                    <span class="small {{ $enPesos ? 'text-white' : 'text-muted' }}">
                        Deuda: {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) ($equivalentePesos['deuda'] ?? 0), $abrevLocal) }}
                    </span>
                </span>
            </div>
        </div>
    @endif
</div>
