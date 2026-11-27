{{-- Tarjeta KPI. Variables: $etiqueta, $valor, $nota (opcional). --}}
<div class="tc-kpi">
    <div class="tc-kpi-etiqueta">{!! $etiqueta !!}</div>
    <div class="tc-kpi-valor">{!! $valor !!}</div>
    @if (! empty($nota))
        <div class="tc-kpi-nota">{!! $nota !!}</div>
    @endif
</div>
