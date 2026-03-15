<div class="form-group row">
	<label for="modeloetiqueta_id" class="col-lg-3 col-form-label requerido">Modelo</label>
	<select id="modeloetiqueta_id" name="modeloetiqueta_id" class="col-lg-3 form-control" required>
		<option value="">-- Elija modelo de etiqueta --</option>
		@foreach ($modeloetiquetas_query as $modeloetiqueta)
			<option value="{{ $modeloetiqueta->id }}"
				@if (old('modeloetiqueta_id', $datas['modeloetiqueta_id'] ?? '') == $modeloetiqueta->id) selected @endif
				>{{ $modeloetiqueta->nombre }}
			</option>
		@endforeach
	</select>
</div>
<input type="hidden" name="programa" id="programa" class="form-control" value="{{$programa}}"/>
<input type="hidden" name="urlretorno" id="urlretorno" class="form-control" value="{{$urlRetorno}}"/>

