@php
    $modo = $modo ?? 'toolbar';
@endphp
@if (can('imprimir-recuento', false))
    @if ($modo === 'fila')
        <a href="{{ route('imprimir_pdf_recuento', ['id' => $recuento->id]) }}"
           class="btn-accion-tabla tooltipsC"
           title="Inventario (PDF)"
           target="_blank"
           rel="noopener">
            <i class="fas fa-file-pdf text-danger"></i>
        </a>
        <a href="{{ route('exportar_excel_recuento', ['id' => $recuento->id]) }}"
           class="btn-accion-tabla tooltipsC"
           title="Inventario (Excel)">
            <i class="fas fa-file-excel text-success"></i>
        </a>
    @else
        <a href="{{ route('imprimir_pdf_recuento', ['id' => $recuento->id]) }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
        <a href="{{ route('exportar_excel_recuento', ['id' => $recuento->id]) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel"></i> Excel
        </a>
    @endif
@endif
