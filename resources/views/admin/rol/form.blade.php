<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 col-form-label requerido">Centro Costo</label>
    <div class="col-lg-8">
        <select class="form-control" id="centrocosto_id" name="centrocosto_id">
            <option value="">Seleccione el centro de costo</option>
            @foreach($centrocosto_query as $id => $nombre)
                <option value="{{$id}}" {{is_array(old('centrocosto_id')) ? (in_array($id, old('centrocosto_id')) ? 'selected' : '')  : (isset($data) ? ($data->centrocosto_id == $id ? 'selected' : '') : '')}}>{{$nombre}} ({{$id}})</option>            
            @endforeach
        </select>  
    </div>
</div> 
