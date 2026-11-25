{{--
  Muestra una fórmula en monospace oscuro.
  Si $pos >= 0, marca solo ese carácter (error de parser).
--}}
@php
    $texto = (string) ($texto ?? '');
    $pos = (int) ($pos ?? -1);
    $conError = ! empty($conError);
@endphp
@if ($texto === '')
    <span class="text-muted">{{ $vacio ?? '—' }}</span>
@elseif ($pos < 0 || $pos > strlen($texto))
    <code class="traza-formula-code {{ $conError ? 'traza-formula-code-error' : '' }}">{{ $texto }}</code>
@else
    @php
        $antes = substr($texto, 0, $pos);
        $marca = substr($texto, $pos, 1);
        if ($marca === '' || $marca === false) {
            $marca = '∎';
        }
        $despues = substr($texto, $pos + 1);
    @endphp
    <code class="traza-formula-code traza-formula-code-error">{{ $antes }}<mark class="traza-pos-error" title="Posición {{ $pos }}">{{ $marca }}</mark>{{ $despues }}</code>
    <div class="small text-danger mt-1">
        <i class="fa fa-map-marker-alt"></i> Error en posici&oacute;n <strong>{{ $pos }}</strong>
        (car&aacute;cter marcado en rojo).
    </div>
@endif
