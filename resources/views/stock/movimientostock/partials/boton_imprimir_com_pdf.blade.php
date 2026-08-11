@php
    $movimientoStockId = (int) ($movimientoStockId ?? 0);
    $modo = $modo ?? 'boton';
    $clase = $clase ?? 'btn btn-outline-success btn-sm';
    $etiqueta = $etiqueta ?? 'Comprobante (PDF)';
    $titulo = $titulo ?? 'Comprobante de movimiento de stock — PDF con logos y detalle de ítems';
@endphp
@if($movimientoStockId > 0 && \App\Support\Stock\Surmar\MovimientoSurmarPermisoSupport::puedeListar(false))
    @if($modo === 'tabla')
        <a href="{{ route('movimientostock_com_pdf', ['id' => $movimientoStockId, 'inline' => 1]) }}"
           class="btn-accion-tabla tooltipsC"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o text-success"></i>
        </a>
    @else
        <a href="{{ route('movimientostock_com_pdf', ['id' => $movimientoStockId, 'inline' => 1]) }}"
           class="{{ $clase }}"
           title="{{ $titulo }}"
           target="_blank"
           rel="noopener">
            <i class="fa fa-file-pdf-o"></i> {{ $etiqueta }}
        </a>
    @endif
@endif
