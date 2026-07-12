@php
    $npuValor = (string) ($numeroparte ?? '');
    $puedeConsultarNpu = can('crear-movimientos-de-stock', false) || can('editar-movimientos-de-stock', false);
@endphp
<td class="align-middle ms-col-npu-baja" style="display:none;">
    <div class="d-flex align-items-center flex-nowrap">
        @if($puedeConsultarNpu)
        <button type="button" title="Consulta NPU activos" class="btn-accion-tabla consultanpubaja flex-shrink-0" style="padding:1px 4px;">
            <i class="fa fa-search text-primary"></i>
        </button>
        @endif
        <input type="text"
               name="numeropartes[]"
               class="form-control form-control-sm numeroparte-baja-linea text-monospace flex-grow-1"
               value="{{ $npuValor }}"
               autocomplete="off"
               placeholder="NPU"
               title="N&uacute;mero de parte &uacute;nica (escanear, tipear + Enter o consulta)">
    </div>
</td>
