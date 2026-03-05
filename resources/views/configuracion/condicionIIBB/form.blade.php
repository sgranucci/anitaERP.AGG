<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="formacalculo" class="col-lg-3 col-form-label requerido">Forma de cálculo</label>
	<select name="formacalculo" class="col-lg-3 form-control">
    	<option value="">-- Elija forma de cálculo --</option>
        @foreach ($formaCalculo_enum as $value => $formacalculo)
        	<option value="{{ $value }}"
        		@if (old('formacalculo', $data->formacalculo ?? '') == $value) selected @endif
        	>{{ $formacalculo }}</option>
        @endforeach
	</select>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
	<select name="estado" class="col-lg-3 form-control">
    	<option value="">-- Elija estado --</option>
        @foreach ($estado_enum as $value => $estado)
        	<option value="{{ $value }}"
        		@if (old('estado', $data->estado ?? '') == $value) selected @endif
        	>{{ $estado }}</option>
        @endforeach
	</select>
</div>
