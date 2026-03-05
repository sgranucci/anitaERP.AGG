<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="nombrecolumna" class="col-lg-3 col-form-label requerido">Nombre de colúmna</label>
    <div class="col-lg-8">
    <input type="text" name="nombrecolumna" id="nombrecolumna" class="form-control" value="{{old('nombrecolumna', $data->nombrecolumna ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="numerocolumna" class="col-lg-3 col-form-label requerido">Número de colúmna</label>
    <div class="col-lg-4">
        <input type="text" name="numerocolumna" id="numerocolumna" class="form-control" value="{{old('numerocolumna', $data->numerocolumna ?? '')}}" required>
    </div>
</div>

