@php
    $referenciasAplicacion = \App\Support\Ventas\ClienteCuentacorrienteGrillaSupport::referenciasAplicacion($data);
    $esExportacion = ! empty($para_pdf) || ! empty($para_excel);
@endphp
@if ($referenciasAplicacion !== [])
    @if ($esExportacion)
        <br>Aplica a: {{ implode(' · ', $referenciasAplicacion) }}
    @else
        <br><small class="text-muted">Aplica a: {{ implode(' · ', $referenciasAplicacion) }}</small>
    @endif
@endif
