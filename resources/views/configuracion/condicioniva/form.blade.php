<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="letra" class="col-lg-3 col-form-label requerido">Letra</label>
	<select name="letra" class="col-lg-3 form-control">
    	<option value="">-- Elija letra --</option>
        @foreach ($letras as $value => $letra)
        	<option value="{{ $value }}"
        		@if (old('letra', $data->letra ?? '') == $value) selected @endif
        	>{{ $letra }}</option>
        @endforeach
	</select>
</div>
<div class="form-group row">
    <label for="coniva" class="col-lg-3 col-form-label requerido">Iva</label>
	<select name="coniva" class="col-lg-3 form-control">
    	<option value="">-- Elija iva --</option>
        @foreach ($conivas as $value => $condiva)
        	<option value="{{ $value }}"
        		@if (old('coniva', $data->coniva ?? '') == $value) selected @endif
        	>{{ $condiva }}</option>
        @endforeach
	</select>
</div>
<div class="form-group row">
    <label for="coniibb" class="col-lg-3 col-form-label requerido">IIBB</label>
	<select name="coniibb" class="col-lg-3 form-control">
    	<option value="">-- Elija iibb --</option>
        @foreach ($coniibbs as $value => $condiibb)
        	<option value="{{ $value }}"
        		@if (old('coniibb', $data->coniibb ?? '') == $value) selected @endif
        	>{{ $condiibb }}</option>
        @endforeach
	</select>
</div>
<div class="form-group row">
    <label for="codigoexterno" class="col-lg-3 col-form-label requerido">Código ARCA</label>
    <div class="col-lg-3">
    <input type="text" name="codigoexterno" id="codigoexterno" class="form-control" value="{{old('codigoexterno', $data->codigoexterno ?? '')}}" required/>
    </div>
</div>
