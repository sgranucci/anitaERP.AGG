@php
    $estado = $estado ?? '';
    $esActiva = strtoupper(trim((string) $estado)) === 'ACTIVA';
@endphp
@if ($esActiva)
    <span class="lp-badge-activa">{{ $estado }}</span>
@elseif ($estado !== '')
    <span class="lp-badge-inactiva">{{ $estado }}</span>
@else
    <span class="text-muted">—</span>
@endif
