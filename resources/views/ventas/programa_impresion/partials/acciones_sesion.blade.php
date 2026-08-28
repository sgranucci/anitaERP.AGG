@php
    $puedeDescargarPdf = ! empty($sesion['pack']);
    $urlPdfSesion = $puedeDescargarPdf
        ? route('descargar_impresion_sesion', ['t' => 'papel'])
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
        <a href="{{ $urlPdfSesion }}" class="btn btn-outline-primary link-descargar-pdf-sesion" title="Solo las copias de papel marcadas. El NAS no está en este archivo.">
            <i class="fa fa-file-pdf"></i> Descargar PDF
        </a>
        <span class="small text-muted">Acrobat: solo papel, sin el duplicado NAS.</span>
    @endif
</div>
