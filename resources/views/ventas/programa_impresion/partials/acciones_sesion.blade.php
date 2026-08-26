@php
    $urlPdfSesion = ! empty($resultado['pdf_sesion'] ?? null)
        ? route('descargar_impresion_sesion', ['t' => basename((string) $resultado['pdf_sesion'])])
        : null;
    $puedeEjecutar = ! empty($sesion['pack']);
    $idBotonEjecutar = ! empty($botonEjecutarId) ? $botonEjecutarId : null;
@endphp
<div class="d-flex flex-wrap align-items-center {{ $claseContenedor ?? 'mb-3' }}" style="gap: 8px;">
    <button type="submit" form="form-ejecutar-sesion"
        class="btn btn-primary btn-ejecutar-sesion"
        @if ($idBotonEjecutar)
            id="{{ $idBotonEjecutar }}"
        @endif
        {{ $puedeEjecutar ? '' : 'disabled' }}>
        <i class="fa fa-print"></i> Ejecutar sesión
    </button>
    @if ($urlPdfSesion)
        <a href="{{ $urlPdfSesion }}" class="btn btn-outline-primary">
            <i class="fa fa-file-pdf"></i> Descargar PDF
        </a>
    @endif
</div>
