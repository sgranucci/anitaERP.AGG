@if (can('volver-compras-requisicion', false) && ($data->estado ?? '') === ($estado_en_arbol_aprobacion ?? 'EN ARBOL APROBACION'))
<form action="{{ route('volver_compras_requisicion', ['id' => $data->id] + ($filtrosQuery ?? [])) }}"
      class="d-inline form-volver-compras-requisicion"
      method="POST"
      data-confirm-msg="&iquest;Devolver la requisici&oacute;n {{ $data->numerorequisicion }} a compras? Se anular&aacute;n las autorizaciones pendientes del &aacute;rbol y podr&aacute; modificarla antes de volver a enviarla.">
    @csrf
    <button type="submit"
            class="{{ $claseBoton ?? 'btn btn-warning btn-sm ml-1' }}"
            title="Devolver a compras (anula autorizaciones pendientes del &aacute;rbol)">
        <i class="fa fa-undo"></i> Volver a compras
    </button>
</form>
@endif
