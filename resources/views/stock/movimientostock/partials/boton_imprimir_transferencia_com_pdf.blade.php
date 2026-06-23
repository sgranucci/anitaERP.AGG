@php
    $transferenciaId = (int) ($transferenciaId ?? 0);
    $modo = $modo ?? 'boton';
    $clase = $clase ?? 'btn btn-outline-success btn-sm';
    $etiqueta = $etiqueta ?? 'Comprobante transferencia (PDF)';
    $titulo = $titulo ?? 'Comprobante de transferencia — PDF con logos y detalle de ítems';
@endphp
@if($transferenciaId > 0 && can('listar-movimientos-de-stock', false))
    @if($modo === 'tabla')
        <a href="{{ route('transferencia_movimientostock_com_pdf', ['id' => $transferenciaId, 'inline' => 1]) }}"
           class="btn-accion-tabla tooltipsC"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o text-success"></i>
        </a>
    @else
        <a href="{{ route('transferencia_movimientostock_com_pdf', ['id' => $transferenciaId, 'inline' => 1]) }}"
           class="{{ $clase }}"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o"></i> {{ $etiqueta }}
        </a>
    @endif
@endif
