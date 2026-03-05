<div class="form-group row">
    <label for="salida_id" class="col-lg-3 col-form-label requerido">Salida</label>
	<select id="salida_id" name="salida_id" class="col-lg-4 form-control" required>
		<option value="">-- Elija salidas --</option>
		@foreach ($salidas_query as $salida)
			<option value="{{ $salida->id }}"
				@if (old('salida_id', $datas['salida_id'] ?? '') == $salida->id) selected @endif
				>{{ $salida->nombre }}
			</option>
		@endforeach
	</select>
</div>
<input type="hidden" name="programa" id="programa" class="form-control" value="{{$programa}}"/>

