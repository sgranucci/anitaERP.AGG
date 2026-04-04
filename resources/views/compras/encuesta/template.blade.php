<template id="template-renglon-encuesta-pregunta">
    <tr class="item-encuesta-pregunta">
        <td>
            <input type="hidden" class="id form-control" name="ids[]" value="">
            <input type="text" name="items[]" class="form-control iiencuesta_pregunta" readonly value="1" />
        </td>
        <td>
            <input type="text" class="nombre form-control" name="nombres[]" value="" required>
        </td>
        <td>
            <input type="number" class="desdepuntaje form-control" name="desdepuntajes[]" min="1" max="100" value="1">
        </td>
        <td>
            <input type="number" class="hastapuntaje form-control" name="hastapuntajes[]" min="1" max="100" value="1">
        </td>                    
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_encuesta_pregunta tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>         
    </tr>
</template>