@php
    $recepcionId = (int) ($recepcionId ?? 0);
    $modo = $modo ?? 'boton';
    $clase = $clase ?? 'btn btn-outline-danger btn-sm';
    $etiqueta = $etiqueta ?? 'Emitir recepción (PDF)';
    $titulo = $titulo ?? 'Comprobante de recepción (COM) — formato PDF con logos y detalle de ítems';
@endphp
@if($recepcionId > 0 && can('listar-recepcion-proveedor', false))
    @if($modo === 'tabla')
        <a href="{{ route('recepcion_proveedor_com_pdf', ['id' => $recepcionId, 'inline' => 1]) }}"
           class="btn-accion-tabla tooltipsC"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o text-danger"></i>
        </a>
    @else
        <a href="{{ route('recepcion_proveedor_com_pdf', ['id' => $recepcionId, 'inline' => 1]) }}"
           class="{{ $clase }}"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o"></i> {{ $etiqueta }}
        </a>
    @endif
@endif
