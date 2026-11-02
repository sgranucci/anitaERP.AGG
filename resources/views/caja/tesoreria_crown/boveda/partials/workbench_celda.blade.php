@switch ($key)
    @case('numero')
        <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $mov->id) }}">
            {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}
        </a>
        @break
    @case('tipo')
        {{ $mov->tipo->etiqueta() ?? '' }}
        @if ($mov->revierte_id)
            <span class="text-muted small">(reversa)</span>
        @endif
        @break
    @case('efecto')
        {{ \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto((string) ($mov->tipo->signo ?? '2')) }}
        @break
    @case('estado')
        <span class="{{ $mov->estado === 'N' ? 'text-muted' : '' }}">{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::estado($mov->estado) }}</span>
        @if ($mov->reversa)
            <div class="small text-muted">Revertido</div>
        @endif
        @break
    @case('valor')
        <div class="tc-boveda-valor text-right">
            @foreach (\App\Support\Caja\TesoreriaCrownBovedaSupport::importesMovimiento($mov) as $importe)
                <div class="{{ (float) $importe['monto'] < 0 ? 'text-danger' : '' }}">
                    {{ number_format((float) $importe['monto'], 2, ',', '.') }} {{ $importe['codigo'] }}
                </div>
            @endforeach
        </div>
        @break
    @case('comprobante')
        @if ($mov->comprobante)
            <a class="text-primary" target="_blank" rel="noopener" href="{{ route('editar_tesoreria_crown_movimiento', ['id' => $mov->comprobante_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                {{ \App\Support\Caja\TesoreriaCrownBovedaListadoColumnas::textoComprobante($mov) }}
            </a>
        @endif
        @break
    @case('comentario')
        {{ $mov->comentario }}
        @if ($mov->motivo)
            <div class="small text-muted">{{ $mov->motivo }}</div>
        @endif
        @break
    @case('usuario')
        {{ $mov->usuario->nombre ?? '' }}
        @break
    @case('confirmado')
        {{ $mov->confirmado_at?->format('d/m/Y H:i') }}
        @break
@endswitch
