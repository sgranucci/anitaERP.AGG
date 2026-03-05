<template id="template-renglon-tarea-ticket">
    <tr class="item-tarea-ticket">
        <td>
            <input type="hidden" name="tarea_ticket[]" class="form-control iitarea_ticket" value="1" />
            <input type="hidden" name="ticket_tarea_ids[]" class="form-control ticket_tarea_id" value="" />
            <div class="form-group row" id="tarea_ticket">
                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="tarea_ticket_id" name="tarea_ticket_ids[]" value="" >
                <input type="hidden" class="tarea_ticket_id_previa" name="tarea_ticket_id_previa[]" value="" >
                <button type="button" title="Consulta tareas" style="padding:1;" class="btn-accion-tabla consultatarea_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 250px;HEIGHT: 38px" class="nombretarea_ticket form-control" name="nombretarea_tickets[]" value="" >
            </div>
        </td>
        <td>
            <input type="date" name="fechacargas[]" class="form-control fechacarga requerido" value="{{date('Y-m-d')}}" readonly>
        </td>
        <td>
            <input type="date" name="fechaprogramaciones[]" class="form-control fechaprogramacion requerido" value="{{date('Y-m-d')}}" required>
        </td>
        <td>
            <div class="form-group row" id="tecnico_ticket">
                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="tecnico_ticket_id" name="tecnico_ticket_ids[]" value="" >
                <input type="hidden" class="tecnico_ticket_id_previa" name="tecnico_ticket_id_previa[]" value="" >
                <button type="button" title="Consulta técnicos" style="padding:1;" class="btn-accion-tabla consultatecnico_ticket tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 160px;HEIGHT: 38px" class="nombretecnico_ticket form-control" name="nombretecnico_tickets[]" value="" >
            </div>
        </td>
        <td>
            <select name="turno_ids[]" data-placeholder="Turno" class="turno_id form-control" data-fouc>
                <option value="">-- Seleccionar Turno --</option>
                @foreach($turno_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
            <input type="hidden" name="creousuario_ids[]" class="form-control creousuario_id" value="{{ auth()->id() }}" />
        </td>
        <td>
            <input type="date" name="fechafinalizaciones[]" class="form-control fechafinalizacion requerido" value="" readonly>
        </td>          
        <td>
            <input type="text" name="tiempoinsumidos[]" class="form-control tiempoinsumido" value="">
        </td> 
        <td>
            <input type="text" name="estadotareas[]" class="form-control estadotarea" value="" readonly>
        </td>  
        <td>
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tarea_ticket tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>