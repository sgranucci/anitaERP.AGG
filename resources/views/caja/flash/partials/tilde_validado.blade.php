@php
    $validado = ! empty($validado);
    $titulo = $titulo ?? ($validado ? 'Flash validado' : 'Flash pendiente de validación');
    $soloTexto = ! empty($soloTexto);
    $mostrarPendiente = ! empty($mostrarPendiente);
@endphp
@if ($validado)
    @if ($soloTexto)
        <span style="color:#1e7e34;font-weight:bold;" title="{{ $titulo }}"> &#10003;</span>
    @else
        <i class="fa fa-check text-success ml-1" title="{{ $titulo }}"></i>
    @endif
@elseif ($mostrarPendiente)
    @if ($soloTexto)
        <span style="color:#888;" title="{{ $titulo }}"> &#10003;</span>
    @else
        <i class="fa fa-check text-muted ml-1" title="{{ $titulo }}"></i>
    @endif
@endif
