<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="tipoarticulo_id" class="col-lg-3 col-form-label requerido">Tipo de art&iacute;culo</label>
	<select name="tipoarticulo_id" class="col-lg-3 form-control">
		<option value="">-- Elija tipo de articulo --</option>
		@foreach ($tipoarticulos as $tipoarticulo)
			<option value="{{ $tipoarticulo->id }}"
				@if (old('tipoarticulo', $data->tipoarticulo->id ?? '') == $tipoarticulo->id) selected @endif
				>{{ $tipoarticulo->nombre }}
			</option>
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="copiaot" class="col-lg-3 col-form-label">C&oacute;digo de copia de OT</label>
    <div class="col-lg-8">
    <input type="number" name="copiaot" id="copiaot" class="form-control" value="{{old('copiaot', $data->copiaot ?? '')}}" >
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-4">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
