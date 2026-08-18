@php
    $valor = $nodo['valor'];
    if (is_bool($valor)) {
        $valorTxt = $valor ? 'verdadero' : 'falso';
    } elseif (is_numeric($valor)) {
        $valorTxt = (floor((float) $valor) == (float) $valor)
            ? number_format((float) $valor, 0, ',', '.')
            : number_format((float) $valor, 4, ',', '.');
    } elseif (is_array($valor)) {
        $valorTxt = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    } else {
        $valorTxt = (string) $valor;
    }
    $tipoColor = [
        'var' => 'text-primary', 'call' => 'text-info', 'bin' => 'text-dark',
        'un' => 'text-dark', 'ter' => 'text-warning',
    ][$nodo['tipo']] ?? 'text-muted';
@endphp
<li>
    <code class="{{ $tipoColor }}">{{ $nodo['expr'] }}</code>
    <span class="text-muted">=</span>
    <strong>{{ $valorTxt }}</strong>
    @if (! empty($nodo['detalle']))
        <span class="badge badge-light border text-muted">{{ $nodo['detalle'] }}</span>
    @endif
    @if (! empty($nodo['hijos']))
        <ul>
            @foreach ($nodo['hijos'] as $hijo)
                @include('sueldos.liquidacion.partials.rastro_nodo', ['nodo' => $hijo])
            @endforeach
        </ul>
    @endif
</li>
