@php
    $estado = $estado ?? 'confirmado';
@endphp
@if ($estado === 'pendiente')
    <span class="badge badge-warning">Pendiente</span>
@elseif ($estado === 'rechazado')
    <span class="badge badge-danger">Rechazado</span>
@else
    <span class="badge badge-success">Confirmado</span>
@endif
