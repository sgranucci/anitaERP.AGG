@php
    /** @var string $campoId e.g. cuenta_ventas_id */
    /** @var string $label */
@endphp
<tr class="item-cuentacontable cfg-cuenta-campo" data-campo-id="{{ $campoId }}">
    <td class="align-middle">{{ $label }}</td>
    <td>
        <input type="hidden" class="empresa" value="">
        <div class="form-group row mb-0" id="cuenta">
            <input type="hidden" class="cuentacontable_id" name="{{ $campoId }}" value="">
            <input type="hidden" class="cuentacontable_id_previa" value="">
            <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacontable tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" style="width:120px;height:38px" class="codigocuentacontable form-control d-inline-block" value="" autocomplete="off">
            <input type="text" style="width:250px;height:38px" class="nombrecuentacontable form-control d-inline-block" value="" readonly>
            <input type="hidden" class="codigo_previo" value="">
        </div>
    </td>
</tr>
