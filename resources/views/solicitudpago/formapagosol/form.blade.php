@if (isset($data))
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    <div class="col-lg-3">
        <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
    </div>
</div>
@endif
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="40" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
    </div>
</div>
