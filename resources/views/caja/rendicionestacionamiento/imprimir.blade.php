<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $datos['titulo'] ?? 'Rendición estacionamiento' }}</title>
    @include('caja.rendicionestacionamiento.partials.estilos_comprobante_pdf')
    <style>
        body { margin: 12px 16px; }
    </style>
</head>
<body>
<div class="barra-acciones no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="{{ $urlPdf }}" class="btn-link" target="_blank" rel="noopener">Ver PDF (vertical)</a>
    @if (! empty($urlVolver))
    <a href="{{ $urlVolver }}" class="btn-link">Volver a edición</a>
    @endif
    <a href="{{ route('rendicionestacionamiento') }}" class="btn-link">Listado</a>
    <a href="javascript:window.close()" class="btn-link">Cerrar</a>
</div>

@include('caja.rendicionestacionamiento.partials.comprobante_contenido')

</body>
</html>
