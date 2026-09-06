@php
    $fuenteEtiqueta = trim((string) ($fuente_etiqueta ?? ''));
    if ($fuenteEtiqueta === '' && is_array($parametros ?? null)) {
        $fuenteEtiqueta = trim((string) ($parametros['fuente_etiqueta'] ?? ''));
    }
@endphp
@if ($fuenteEtiqueta !== '')
    <div class="px-3 py-2 border-bottom bg-light">
        <span class="badge badge-info">Fuente</span>
        <span class="small ml-1">{{ $fuenteEtiqueta }}</span>
    </div>
@endif
