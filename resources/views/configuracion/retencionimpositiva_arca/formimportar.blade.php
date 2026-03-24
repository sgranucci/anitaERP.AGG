<div class="form-group row">
	<label for="empresa" class="col-lg-3 col-form-label">Empresa</label>
	<select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-4 form-control required" data-fouc required>
		@foreach($empresa_query as $key => $value)
			<option value="{{ $value->id }}">{{ $value->id }} {{ $value->nombre }}</option>    
		@endforeach
	</select>
</div>    
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


