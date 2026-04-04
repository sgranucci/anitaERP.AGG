<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 col-form-label requerido">Nombre</label>
            <div class="col-lg-6">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="estado" class="col-lg-4 col-form-label requerido">Estado</label>
            <div class="col-lg-6">
                <select id="estado" name="estado" data-placeholder="Estado del árbol" class="col-lg-4 form-control" required>
                    @foreach($estado_enum as $estado)
                        @if ($estado['nombre'] == old('estado',$data->estado??''))
                            <option value="{{ $estado['nombre'] }}" selected>{{ $estado['nombre'] }}</option>    
                        @else
                            <option value="{{ $estado['nombre'] }}">{{ $estado['nombre'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>   
        </div>                   
    </div>
</div>
<h4>Preguntas</h4>
<div class="card-body">
    <table class="table" id="encuesta-pregunta-table">
        <thead>
            <tr>
                <th style="width: 6%;">Pregunta</th>
                <th style="width: 45%;">Nombre</th>
                <th style="width: 15%;">Desde Puntaje</th>
                <th style="width: 15%;">Hasta Puntaje</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-encuesta-pregunta-table">
        @if ($data->encuesta_preguntas ?? '') 
            @foreach (old('encuesta_pregunta', $data->encuesta_preguntas->count() ? $data->encuesta_preguntas : ['']) as $encuesta_preguntas)
                <tr class="item-encuesta-pregunta">
                    <td>
                        <input type="hidden" class="id form-control" name="ids[]" value="{{$encuesta_preguntas->id ?? ''}}">
                        <input type="text" name="items[]" class="form-control iiencuesta_pregunta" readonly value="{{ $loop->index+1 }}" />
                    </td>
                    <td>
                        <input type="text" class="nombre form-control" name="nombres[]" value="{{$encuesta_preguntas->nombre ?? ''}}" required>
                    </td>
                    <td>
                        <input type="number" class="desdepuntaje form-control" name="desdepuntajes[]" min="1" max="100" value="{{$encuesta_preguntas->desdepuntaje ?? ''}}">
                    </td>
                    <td>
                        <input type="number" class="hastapuntaje form-control" name="hastapuntajes[]" min="1" max="100" value="{{$encuesta_preguntas->hastapuntaje ?? ''}}">
                    </td>                    
                    <td>
                        <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_encuesta_pregunta tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('compras.encuesta.template')
    <div class="row">
        <div class="col-md-12">
            <button id="agrega_renglon_encuesta_pregunta" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        </div>
    </div>
</div>

