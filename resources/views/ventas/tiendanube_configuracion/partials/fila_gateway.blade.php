@php
    $idx = $idx ?? 0;
    $gw = $gw ?? (object) [];
    $cuenta = $gw->cuentacaja ?? null;
    $cuentaId = $gw->cuentacaja_id ?? ($cuenta->id ?? '');
@endphp
<tr class="tn-gateway-row">
    <td class="align-middle">
        <input type="text" name="gateway_key[]" class="form-control form-control-sm tn-gateway-key"
            value="{{ $gw->gateway_key ?? '' }}" placeholder="gocuotas" maxlength="80"
            title="Clave del gateway en la API de Tiendanube">
    </td>
    <td>
        @include('caja.partials.campo_consulta_cuentacaja', [
            'prefix' => 'tn_gw_'.$idx,
            'layout' => 'inline',
            'inputName' => 'gateway_cuentacaja_id[]',
            'inputId' => 'gateway_cuentacaja_id_'.$idx,
            'cuentacajaId' => $cuentaId,
            'codigo' => $cuenta->codigo ?? '',
            'nombre' => $cuenta->nombre ?? '',
            'required' => false,
            'mostrar_editar' => true,
            'label' => '',
        ])
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn-accion-tabla tn-gateway-quitar" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
