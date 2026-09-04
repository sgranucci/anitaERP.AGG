@php
    $estadoReqMarcar = $data->estado ?? '';
    $puedeMarcarCumplida = can('actualizar-requisicion', false)
        && (
            $estadoReqMarcar === ($estado_aprobada_requisicion ?? 'APROBADA')
            || $estadoReqMarcar === ($estado_genero_oc_requisicion ?? 'GENERO ORDEN COMPRA')
            || $estadoReqMarcar === 'GENERO OC'
        );
@endphp
@if ($puedeMarcarCumplida)
<form action="{{ route('marcar_cumplida_requisicion', ['id' => $data->id] + ($filtrosQuery ?? [])) }}"
      class="d-inline form-marcar-cumplida-requisicion"
      method="POST"
      data-confirm-msg="&iquest;Marcar la requisici&oacute;n {{ $data->numerorequisicion }} como CUMPLIDA? Se cerrar&aacute;n los &iacute;tems pendientes sin orden de compra y no podr&aacute; generar m&aacute;s OC.">
    @csrf
    <button type="submit"
            class="{{ $claseBoton ?? 'btn btn-secondary btn-sm ml-1' }}"
            title="Marcar como CUMPLIDA (dar de baja / cerrar proceso)">
        <i class="fa fa-check-double"></i>
        @if (empty($soloIcono))
            Marcar CUMPLIDA
        @endif
    </button>
</form>
@endif
