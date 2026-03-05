<template id="template-renglon-voucher-reserva">
    <tr class="item-voucher-reserva">
        <td>
            <div class="form-group row" id="reserva">
                <input type="hidden" name="reserva[]" class="form-control iireserva" readonly value="1" />
                <input type="hidden" class="reserva_id" name="reserva_ids[]" value="" >
                <input type="hidden" class="reserva_id_previa" name="reserva_id_previa[]" value="" >
                <button type="button" title="Consulta reservas" style="padding:1;" class="btn-accion-tabla consultareserva tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoreserva form-control" name="codigoreservas[]" value="" >
                <input type="hidden" class="codigo_previo_reserva" name="codigo_previo_reservas[]" value="" >
                <input type="hidden" class="carga_reserva_manual" name="carga_reserva_manuales[]" value="" >
            </div>
        </td>	
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrepasajero form-control" name="nombrepasajeros[]" value="" readonly>
            <input type="hidden" name="pasajero_ids[]" class="form-control pasajero_id" value="">
        </td>
        <td>
            <input type="date" name="fechaarribos[]" class="form-control fechaarribo" value="">
        </td>
        <td>
            <input type="date" name="fechapartidas[]" class="form-control fechapartida" value="">
        </td>
        <td>
            <input type="number" name="paxs[]" class="form-control pax" min="0" step="1" value="0">
            <input type="hidden" name="limitepaxs[]" class="form-control limitepax" value="0">
        </td>
        <td>
            <input type="number" name="frees[]" class="form-control free" min="0" step="1" value="0">
            <input type="hidden" name="limitefrees[]" class="form-control limitefree" value="0">
        </td>
        <td>
            <input type="number" name="incluidos[]" class="form-control incluido" min="0" step="1" value="0">
        </td>
        <td>
            <input type="number" name="opcionales[]" class="form-control opcional" min="0" step="1" value="0">
        </td>						
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_voucher_reserva tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>