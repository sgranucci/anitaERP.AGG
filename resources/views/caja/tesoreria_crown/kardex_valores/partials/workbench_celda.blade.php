@switch ($key)
    @case('fecha')
        {{ $fila->fecha instanceof \DateTimeInterface ? $fila->fecha->format('d/m/Y') : \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') }}
        @break
    @case('deposito')
        {{ $fila->deposito_codigo }} <span class="text-muted">{{ $fila->deposito_tipo }}</span>
        @break
    @case('abreviatura')
        {{ $fila->abreviatura }}
        @break
    @case('numero')
        <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $fila->boveda_id) }}?origen=modal_consulta&vista=consulta" target="_blank" rel="noopener">
            {{ $fila->numero_visible }}
        </a>
        @break
    @case('tipo')
        {{ $fila->tipo_nombre }}
        @break
    @case('subtipo')
        {{ $fila->subtipo_etiqueta ?: '—' }}
        @break
    @case('valor')
        {{ number_format((float) $fila->denominacion, 0, ',', '.') }} {{ $fila->moneda }}
        @break
    @case('entrada')
        <span class="d-block text-right">{{ $fila->entrada !== null ? number_format($fila->entrada, 0, ',', '.') : '' }}</span>
        @break
    @case('salida')
        <span class="d-block text-right">{{ $fila->salida !== null ? number_format($fila->salida, 0, ',', '.') : '' }}</span>
        @break
    @case('monto')
        <span class="d-block text-right {{ (float) $fila->monto < 0 ? 'text-danger' : '' }}">{{ number_format((float) $fila->monto, 2, ',', '.') }}</span>
        @break
    @case('comentario')
        {{ $fila->comentario }}
        @break
    @case('efecto')
        {{ $fila->efecto_texto }}
        @break
    @case('operacion')
        {{ $fila->operacion }}
        @break
    @case('moneda')
        {{ $fila->moneda }}
        @break
@endswitch
