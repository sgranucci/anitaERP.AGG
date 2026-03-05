<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $linea->nombre ?? '')}}" required/>
    </div>
</div>
@if (config('app.empresa') == 'Calzados Ferli')
	<div class="form-group row">
		<label for="nombre" class="col-lg-3 col-form-label requerido">C&oacute;digo ANITA</label>
		<div class="col-lg-3">
		<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $linea->codigo ?? '')}}" required/>
		</div>
	</div>
	<div class="form-group row">
		<label for="tiponumeracion_id" class="col-lg-3 col-form-label requerido">Tipo de numeraci&oacute;n</label>
		<select name="tiponumeracion_id" class="col-lg-3 form-control">
			<option value="">-- Elija tipo de numeraci&oacute;n --</option>
			@foreach ($tiponumeracion_query as $tiponumeracion)
				<option value="{{ $tiponumeracion->id }}"
					@if (old('tiponumeracion', $linea->tiponumeracion_id ?? '') == $tiponumeracion->id) selected @endif
					>{{ $tiponumeracion->nombre }}
				</option>
			@endforeach
		</select>
	</div>
	<div class="form-group row">
		<label for="maxhorma" class="col-lg-3 col-form-label">M&aacute;xima cantidad de hormas</label>
		<div class="col-lg-8">
		<input type="number" name="maxhorma" id="maxhorma" class="form-control" value="{{old('maxhorma', $linea->maxhorma ?? '15')}}" >
		</div>
	</div>
	<div class="form-group row">
		<label for="numeracion_id" class="col-lg-3 col-form-label requerido">Numeraci&oacute;n</label>
		<select name="numeracion_id" class="col-lg-3 form-control">
			<option value="">-- Elija numeraci&oacute;n --</option>
			@foreach ($numeracion_query as $numeracion)
				<option value="{{ $numeracion->id }}"
					@if (old('numeracion', $linea->numeraciones->id ?? '') == $numeracion->id) selected @endif
					>{{ $numeracion->nombre }}
				</option>
			@endforeach
		</select>
	</div>
	<div class="form-group row">
		<label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios WEB</label>
		<select name="listaprecio_id" class="col-lg-3 form-control">
			<option value="">-- Elija lista de precios --</option>
			@foreach ($listaprecio_query as $listaprecio)
				<option value="{{ $listaprecio->id }}"
					@if (old('listaprecio', $linea->listaprecios->id ?? '') == $listaprecio->id) selected @endif
					>{{ $listaprecio->nombre }}
				</option>
			@endforeach
		</select>
	</div>

	<div class="card">
		<div class="card-header">
			M&oacute;dulos
		</div>

		<div class="card-body">
		<table class="table" id="modulos_table">
			<thead>
				<tr>
					<th>M&oacute;dulo</th>
				</tr>
			</thead>
			<tbody>
				@if (!empty($linea))
					@foreach (old('modulos', $linea->modulos->count() ? $linea->modulos : ['']) as $linea_modulo)
						<tr id="modulo{{ $loop->index }}">
							<td>
								<select name="modulos[]" class="form-control">
									<option value="">-- Elija m&oacute;dulo --</option>
									@foreach ($modulo_query as $modulo)
										<option value="{{ $modulo->id }}"
										@if (old('modulos.' . $loop->parent->index, optional($linea_modulo)->id) == $modulo->id) selected @endif
									>{{ $modulo->nombre }}</option>
									@endforeach
								</select>
							</td>
						</tr>
					@endforeach
					<tr id="modulo{{ count(old('modulos', $linea->modulos->count() ? $linea->modulos : [''])) }}"></tr>
				@else
					<tr id="modulo0">
						<td>
							<select name="modulos[]" class="form-control">
								<option value="">-- Elija m&oacute;dulo --</option>
								@foreach ($modulo_query as $modulo)
									<option value="{{ $modulo->id }}">
										{{ $modulo->nombre }}
									</option>
								@endforeach
							</select>
						</td>
					</tr>
					<tr id="modulo1"></tr>
				@endif
				</tbody>
				</table>
				<div class="row">
					<div class="col-md-12">
						<button id="add_row" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
						<button id="delete_row" class="pull-right btn btn-danger">- Borra rengl&oacute;n</button>
					</div>
				</div>
			</div>
		</div>
	</div>
@endif
