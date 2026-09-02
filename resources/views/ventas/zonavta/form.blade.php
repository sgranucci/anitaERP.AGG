<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">C&oacute;digo Anita</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" readonly/>
    </div>
</div>
<input type="hidden" id="referer" name="referer" value="{{ $referer ?? '' }}" />
