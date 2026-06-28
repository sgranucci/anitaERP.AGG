<template id="template-renglon-cheque-emitido">
    <tr class="item-cheque-emitido">
        <td>
            <input type="hidden" name="cheque_emitido_ids[]" class="cheque_emitido_id" value="">
            <input type="hidden" name="cuentacaja_emitido_ids[]" class="cuentacaja_emitido_id" value="">
            <button type="button" class="btn-accion-tabla consultacuentacaja_emitido tooltipsC" title="Consulta cuenta banco">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="codigo_emitido form-control d-inline-block" style="width:90px" name="codigo_emitido[]" value="">
            <input type="text" class="nombre_emitido form-control d-inline-block" style="width:140px" readonly value="">
        </td>
        <td>
            <select name="chequera_emitido_ids[]" class="form-control chequera_emitido_id">
                <option value="">--</option>
                @foreach ($chequera_query as $ch)
                    <option value="{{ $ch->id }}">{{ $ch->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="numerocheque_emitidos[]" class="form-control numerocheque_emitido" value=""></td>
        <td><input type="date" name="fechapago_emitidos[]" class="form-control fechapago_emitido" value=""></td>
        <td>
            <select name="caracter_emitidos[]" class="form-control caracter_emitido">
                @foreach ($caracter_enum as $car)
                    @if ($car['valor'] !== 'R')
                        <option value="{{ $car['valor'] }}">{{ $car['nombre'] }}</option>
                    @endif
                @endforeach
            </select>
        </td>
        <td><input type="text" name="anombrede_emitidos[]" class="form-control anombrede_emitido" value=""></td>
        <td>
            <select name="moneda_emitido_ids[]" class="form-control moneda_emitido_id">
                @foreach ($moneda_query as $m)
                    <option value="{{ $m->id }}">{{ $m->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="montocheque_emitidos[]" class="form-control montocheque_emitido" min="0" step="0.01" value=""></td>
        <td><input type="number" name="cotizacioncheque_emitidos[]" class="form-control cotizacioncheque_emitido" step="0.0001" value="0"></td>
        <td>
            <button type="button" class="btn-accion-tabla eliminar_cheque_emitido tooltipsC" title="Eliminar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
