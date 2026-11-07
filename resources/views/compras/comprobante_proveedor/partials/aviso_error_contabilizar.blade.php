@php
    $errorSyncMsg = trim((string) (($data->anita_sync_error ?? '') ?: ''));
    $syncEstado = (string) ($data->anita_sync_estado ?? '');
    $esErrorAnita = \App\Support\Compras\ComprobanteProveedorAnitaSyncEstado::esErrorAnita($syncEstado);
    $esErrorErp = \App\Support\Compras\ComprobanteProveedorAnitaSyncEstado::esErrorErp($syncEstado);
    $contabilizadoConErrorAnita = $esErrorAnita
        && ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO;
    $horasBridgeAviso = config('comprobante_proveedor_anita.sync_bridge.horas', ['13:00', '16:00']);
    if (! is_array($horasBridgeAviso) || $horasBridgeAviso === []) {
        $horasBridgeAviso = ['13:00', '16:00'];
    }
    $textoHorasBridgeAviso = implode(' y ', $horasBridgeAviso);
@endphp
@if (($esErrorAnita || $esErrorErp) && $errorSyncMsg !== '')
<div id="cp-alerta-contabilizar" class="alert alert-danger">
    @if ($esErrorErp)
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> Rechazo del ERP</h4>
    <p class="mb-1">{{ $errorSyncMsg }}</p>
    <p class="mb-0">
        No es un error de Anita: falló la validación o el asiento en el ERP
        (por ejemplo centro de costo no habilitado en la cuenta).
        El comprobante quedó en <strong>{{ $data->estado ?? 'BORRADOR' }}</strong>.
        Corrija la imputación y vuelva a guardar / contabilizar.
    </p>
    @elseif ($contabilizadoConErrorAnita)
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> No se pudo sincronizar a Anita</h4>
    <p class="mb-1">{{ $errorSyncMsg }}</p>
    <p class="mb-0">
        El comprobante quedó <strong>contabilizado</strong> en el ERP.
        El barrido de las {{ $textoHorasBridgeAviso }} vuelve a intentar.
    </p>
    @else
    <h4 class="mb-2"><i class="icon fa fa-ban"></i> No se pudo contabilizar (Anita)</h4>
    <p class="mb-1">{{ $errorSyncMsg }}</p>
    <p class="mb-0">
        El comprobante quedó en <strong>{{ $data->estado ?? 'BORRADOR' }}</strong>.
        Corrija el problema y pulse <strong>Contabilizar</strong> de nuevo.
        Este aviso permanece hasta que la contabilización termine bien.
    </p>
    @endif
</div>
@endif
