@php
    $omit = $omitFiltro ?? null;
@endphp
@if (($filtroFuente ?? '') !== '' && $omit !== 'fuente')
    <input type="hidden" name="fuente" value="{{ $filtroFuente }}">
@endif
@if (($filtroTipo ?? '') !== '' && $omit !== 'tipo')
    <input type="hidden" name="tipo" value="{{ $filtroTipo }}">
@endif
@if (($filtroQ ?? '') !== '' && $omit !== 'q')
    <input type="hidden" name="q" value="{{ $filtroQ }}">
@endif
@if (($filtroUrgencia ?? '') !== '' && $omit !== 'urgencia')
    <input type="hidden" name="urgencia" value="{{ $filtroUrgencia }}">
@endif
@if (!empty($filtroReemplazo) && $omit !== 'reemplazo')
    <input type="hidden" name="reemplazo" value="1">
@endif
@if (($filtroDiasMin ?? 0) > 0 && $omit !== 'dias_min')
    <input type="hidden" name="dias_min" value="{{ (int) $filtroDiasMin }}">
@endif
@if (($filtroMontoMin ?? 0) > 0 && $omit !== 'monto_min')
    <input type="hidden" name="monto_min" value="{{ $filtroMontoMin }}">
@endif
