@php
    $errorAnita = trim((string) (($data->anita_sync_error ?? '') ?: ''));
    $estadoAnitaError = ($data->anita_sync_estado ?? '') === \App\Support\Compras\ComprobanteProveedorAnitaSyncEstado::ERROR;
@endphp
@if ($estadoAnitaError && $errorAnita !== '')
<div id="cp-alerta-contabilizar" class="alert alert-danger">
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> No se pudo contabilizar / sincronizar con Anita</h4>
    <p class="mb-1">{{ $errorAnita }}</p>
    <p class="mb-0">
        El comprobante quedó en <strong>{{ $data->estado ?? 'BORRADOR' }}</strong>.
        Corrija el problema y pulse <strong>Contabilizar</strong> de nuevo.
        Este aviso permanece hasta que la contabilización termine bien.
    </p>
</div>
@endif
