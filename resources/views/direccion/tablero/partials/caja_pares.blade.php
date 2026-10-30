@php
    $var = $caja['variacion'];
    $sube = $var !== null && $var > 0.05;
    $baja = $var !== null && $var < -0.05;
    $claseVar = $sube ? 'td-var-up' : ($baja ? 'td-var-down' : 'td-var-flat');
@endphp
<div class="td-par {{ $clase }}">
    <div class="td-par-titulo"><i class="fas {{ $icono }} mr-1"></i>{{ $titulo }}</div>
    <div class="td-par-num">{{ $caja['fmt'] }}</div>
    <div class="td-par-extra">
        @if ($var === null)
            <span class="td-var td-var-flat">sin base</span>
        @else
            <span class="td-var {{ $claseVar }}">{{ $var > 0 ? '+' : '' }}{{ number_format($var, 1, ',', '.') }}%</span>
        @endif
        vs {{ $caja['anterior_fmt'] }}
    </div>
    <div class="td-par-ayuda">{{ $caja['ayuda'] }}</div>
</div>
