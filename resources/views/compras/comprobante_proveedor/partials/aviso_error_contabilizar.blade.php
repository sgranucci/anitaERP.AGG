@php
    $errorAnita = trim((string) (($data->anita_sync_error ?? '') ?: ''));
    $estadoAnitaError = ($data->anita_sync_estado ?? '') === \App\Support\Compras\ComprobanteProveedorAnitaSyncEstado::ERROR;
    $contabilizadoConErrorAnita = $estadoAnitaError
        && ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO;
    $horasBridgeAviso = config('comprobante_proveedor_anita.sync_bridge.horas', ['13:00', '16:00']);
    if (! is_array($horasBridgeAviso) || $horasBridgeAviso === []) {
        $horasBridgeAviso = ['13:00', '16:00'];
    }
    $textoHorasBridgeAviso = implode(' y ', $horasBridgeAviso);
@endphp
@if ($estadoAnitaError && $errorAnita !== '')
<div id="cp-alerta-contabilizar" class="alert alert-danger">
    @if ($contabilizadoConErrorAnita)
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> No se pudo sincronizar a Anita</h4>
    <p class="mb-1">{{ $errorAnita }}</p>
    <p class="mb-0">
        El comprobante quedó <strong>contabilizado</strong> en el ERP.
        El barrido de las {{ $textoHorasBridgeAviso }} vuelve a intentar.
    </p>
    @else
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> No se pudo contabilizar</h4>
    <p class="mb-1">{{ $errorAnita }}</p>
    <p class="mb-0">
        El comprobante quedó en <strong>{{ $data->estado ?? 'BORRADOR' }}</strong>.
        Corrija el problema y pulse <strong>Contabilizar</strong> de nuevo.
        Este aviso permanece hasta que la contabilización termine bien.
    </p>
    @endif
</div>
@endif
