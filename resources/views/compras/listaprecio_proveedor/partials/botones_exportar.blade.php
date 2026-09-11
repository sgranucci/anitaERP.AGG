@php
    $listaId = (int) ($listaId ?? 0);
    $variant = $variant ?? 'header';
    $puedeExportar = \App\Support\Compras\ListaprecioProveedorConsultaDesdeModal::puedeConsultar();
    $urlPdf = $listaId > 0 ? route('exportar_listaprecio_proveedor', ['id' => $listaId, 'formato' => 'PDF']) : '';
    $urlExcel = $listaId > 0 ? route('exportar_listaprecio_proveedor', ['id' => $listaId, 'formato' => 'EXCEL']) : '';
@endphp
@if ($puedeExportar && $listaId > 0)
    @if ($variant === 'row')
        <a href="{{ $urlPdf }}" class="btn-accion-tabla tooltipsC text-danger" title="Exportar precios a PDF" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-file-pdf-o"></i>
        </a>
        <a href="{{ $urlExcel }}" class="btn-accion-tabla tooltipsC text-primary" title="Exportar precios a Excel">
            <i class="fa fa-file-excel-o"></i>
        </a>
    @else
        <a href="{{ $urlPdf }}" class="btn btn-outline-danger btn-sm" title="Exportar precios a PDF" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-file-pdf-o"></i> PDF
        </a>
        <a href="{{ $urlExcel }}" class="btn btn-outline-success btn-sm ml-1" title="Exportar precios a Excel">
            <i class="fa fa-file-excel-o"></i> Excel
        </a>
    @endif
@endif
