@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'mostrar_id' => true,
    'col_input' => 'col-lg-4',
])
<div class="form-group row">
	<label for="agregapisa" class="col-lg-3 col-form-label requerido">Agrega o Pisa Datos</label>
	<select name="agregapisa" class="col-lg-3 form-control requerido" required>
		<option value="">-- Elija si agrega o pisa los datos --</option>
		@foreach($agregapisa_enum as $value => $agregapisa)
			<option value="{{ $value }}">{{ $agregapisa }}</option>    
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="file" class="col-lg-3 col-form-label requerido">Archivo</label>
	<div class="col-lg-8">
		<input type="file" name="file" class="form-control" value="" required/>
	</div>
</div>


