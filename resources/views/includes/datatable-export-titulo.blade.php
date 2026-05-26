{{-- Definir antes de admin/index.js (incluye el script con versión para evitar caché del navegador) --}}
@php
    $rutaAdminIndexJs = public_path('assets/pages/scripts/admin/index.js');
    $versionAdminIndexJs = file_exists($rutaAdminIndexJs) ? filemtime($rutaAdminIndexJs) : time();
@endphp
<script>
    window.tituloExportListado = @json($titulo ?? '');
    @isset($nombreArchivo)
    window.nombreArchivoExportListado = @json($nombreArchivo);
    @endisset
</script>
@if ($cargarAdminIndex ?? true)
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}?v={{ $versionAdminIndexJs }}" type="text/javascript"></script>
@endif
