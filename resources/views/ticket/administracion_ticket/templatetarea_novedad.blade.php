<template id="template-renglon-tarea-novedad">
    <tr class="item-tarea-novedad">
        <td>
            <input type="hidden" name="tarea_novedad[]" class="form-control iitarea_novedad" value="1" />
            <input type="hidden" name="ids" class="ticket_tarea_novedad_id" value="">
            <input type="hidden" name="ticket_tarea_ids[]" class="ticket_tarea_id" value="">
            <input type="date" name="desdefechas[]" class="desdefecha" value="{{date('Y-m-d')}}">
        </td>
        <td>
            <input type="date" name="hastafechas[]" class="hastafecha" value="{{date('Y-m-d')}}">
        </td>
        <td>
            <input type="text" style="WIDTH: 450px;HEIGHT: 29px" name="comentarios[]" class="comentario" value="">
        </td>		
        <td>
            <div class="form-group row">
                <select id="estado" name="estados[]" style="WIDTH: 170px;HEIGHT: 29px" class="estado" required>
                    <option value="">-- Elija estado --</option>
                    @foreach($estado_novedad_enum as $estado)
                        @if ($estado['valor'] == old('estados',$data->estado??''))
                            <option value="{{ $estado['nombre'] }}" selected>{{ $estado['nombre'] }}</option>    
                        @else
                            <option value="{{ $estado['nombre'] }}">{{ $estado['nombre'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>            
        </td>	
        <td>
            <input type="text" style="WIDTH: 80px;HEIGHT: 29px" name="nombreusuarios[]" class="usuario" value="{{ Auth::user()->usuario }}" readonly>
        </td>
        <td>
            <button style="width: 6%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tarea_novedad tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
            <input type="hidden" name="usuario_ids[]" class="form-control usuario_id" value="{{ auth()->id() }}" />
        </td>
    </tr>
</template>