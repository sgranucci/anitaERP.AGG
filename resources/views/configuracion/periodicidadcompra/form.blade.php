<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="diasentrecompra" class="col-lg-3 col-form-label requerido">Dias entre compra</label>
    <div class="col-lg-2">
        <input type="number" name="diasentrecompra" id="diasentrecompra" class="form-control" value="{{old('diasentrecompra', $data->diasentrecompra ?? '')}}" required/>
    </div>
</div>