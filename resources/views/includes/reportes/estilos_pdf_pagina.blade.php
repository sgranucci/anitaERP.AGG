{{--
    Márgenes de página para DomPDF.
    @page { margin } no se aplica en este motor: las tablas al 100% llegan al borde.
    Tampoco usar padding en body: el width:100% se mide sobre la hoja y el contenido
    se corre a la derecha (impresión “desplazada”).
    El aire lateral va en columnas vacías de table.marco-pdf (mm reales).
--}}
@php
    $pdfSize = $pdf_size ?? 'legal landscape';
    $pdfLateral = $pdf_lateral ?? '16mm';
    $pdfVertical = $pdf_vertical ?? '12mm';
@endphp
@page { size: {{ $pdfSize }}; margin: 0; }
html, body { margin: 0; padding: 0; }
table.marco-pdf { width: 100%; border-collapse: collapse; margin: 0; }
table.marco-pdf > tbody > tr > td { border: none; padding: 0; vertical-align: top; }
table.marco-pdf td.marco-lat { width: {{ $pdfLateral }}; }
table.marco-pdf td.marco-centro { padding: {{ $pdfVertical }} 0; }
