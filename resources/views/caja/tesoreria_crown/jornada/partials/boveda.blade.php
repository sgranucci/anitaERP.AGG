@if (! empty($boveda) && can('listar-tesoreria-crown-boveda', false))
    <h5 class="mt-4">Bóveda</h5>
    <p class="text-muted small mb-2">Saldo confirmado hasta esta jornada. El detalle y los movimientos están en la bóveda.</p>
    @php
        $bovedas = ($boveda['lugares'] ?? collect())->filter(fn ($lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');
    @endphp
    @if ($bovedas->isEmpty())
        <p class="mb-2">
            Esta sala no tiene un depósito de tipo Bóveda.
            <a class="text-primary" href="{{ route('tesoreria_crown_deposito', ['sala_id' => $sala->id]) }}">Cargalo en Depósitos</a>.
        </p>
    @else
        <ul class="mb-2">
            @foreach ($bovedas as $lugarBoveda)
                <li>
                    <a class="text-primary" href="{{ route('tesoreria_crown_boveda', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugarBoveda->id]) }}">
                        {{ $lugarBoveda->codigo }} — {{ $lugarBoveda->nombre }}
                    </a>
                    @php $gruposBoveda = $boveda['saldos'][$lugarBoveda->id] ?? []; @endphp
                    @if ($gruposBoveda === [])
                        <span class="text-muted">sin saldo confirmado</span>
                    @else
                        @foreach ($gruposBoveda as $grupoBoveda)
                            <span class="mr-2">{{ $grupoBoveda['titulo'] }} {{ number_format($grupoBoveda['monto'], 2, ',', '.') }}</span>
                        @endforeach
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if (($boveda['movimientos'] ?? collect())->isNotEmpty())
        <ul class="mb-0">
            @foreach ($boveda['movimientos'] as $movBoveda)
                <li>
                    <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $movBoveda->id) }}">
                        {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movBoveda->numero) }}
                        · {{ $movBoveda->tipo->abreviatura ?? '' }}
                        · {{ \App\Support\Caja\TesoreriaCrownBovedaSupport::estado($movBoveda->estado) }}
                        · {{ $movBoveda->deposito->codigo ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endif
