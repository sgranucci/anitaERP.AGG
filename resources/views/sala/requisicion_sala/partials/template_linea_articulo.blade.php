<script type="text/template" id="template-linea-requisicion-sala">
<tr class="item-requisicion-sala-articulo">
    <td>
        <input type="hidden" class="requisicion_sala_articulo_id" name="requisicion_sala_articulo_ids[]" value="">
        <input type="hidden" class="articulo_id" name="articulo_ids[]" value="">
        <input type="hidden" class="articulo_lleva_npu" value="0">
        <div class="d-flex align-items-center">
            <button type="button" class="btn-accion-tabla consultaarticulo mr-1"><i class="fa fa-search text-primary"></i></button>
            <input type="text" class="codigoarticulo form-control form-control-sm" style="width:7rem;" value="">
        </div>
    </td>
    <td><input type="text" class="descripcionarticulo form-control form-control-sm" readonly value=""></td>
    <td><input type="number" step="0.0001" name="cantidades[]" class="form-control form-control-sm" value="1"></td>
    <td>
        <select name="fueradeservicios[]" class="form-control form-control-sm">
            <option value="N" selected>N</option>
            <option value="S">S</option>
        </select>
    </td>
    <td><input type="text" name="uids[]" class="form-control form-control-sm" value=""></td>
    <td><input type="text" name="numeropartes[]" class="form-control form-control-sm numeroparte-linea" value=""></td>
    <td>
        <select name="destinos[]" class="form-control form-control-sm">
            @foreach ($destino_enum ?? [] as $d)
            <option value="{{ $d['valor'] }}">{{ $d['nombre'] }}</option>
            @endforeach
        </select>
    </td>
    <td><button type="button" class="btn-accion-tabla eliminar_linea_sala"><i class="fa fa-times-circle text-danger"></i></button></td>
</tr>
</script>
