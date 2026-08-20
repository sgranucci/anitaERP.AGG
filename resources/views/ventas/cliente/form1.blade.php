<div id="tab-datos-principales" class="tab-pane fade show active form1" role="tabpanel">
	<div class="row">
		<div class="col-lg-6">
			<div class="form-group row">
				<label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
				<div class="col-lg-8">
					<input type="text" name="nombre" id="nombre" class="form-control" pattern="[A-Za-z0-9]{1,255}" value="{{old('nombre', $data->nombre ?? '')}}" required/>
				</div>
			</div>
			<div class="form-group row">
				<label for="fantasia" class="col-lg-4 control-label text-right pr-2">Fantas&iacute;a</label>
				<div class="col-lg-8">
					<input type="text" name="fantasia" id="fantasia" class="form-control" value="{{old('fantasia', $data->fantasia ?? '')}}">
				</div>
			</div>
			<div class="form-group row">
				<label for="contacto" class="col-lg-4 control-label text-right pr-2">Contacto</label>
				<div class="col-lg-8">
					<input type="text" name="contacto" id="contacto" class="form-control" value="{{old('contacto', $data->contacto ?? '')}}">
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="form-group row">
				@if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI')
					<label for="telefono" class="col-lg-4 control-label text-right pr-2 requerido">Tel&eacute;fono</label>
				@else
					<label for="telefono" class="col-lg-4 control-label text-right pr-2">Tel&eacute;fono</label>
				@endif
				<div class="col-lg-8">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-phone"></i></span>
						</div>
						@if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI')
							<input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $data->telefono ?? '')}}" required/>
						@else
							<input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $data->telefono ?? '')}}"/>
						@endif
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label for="email" class="col-lg-4 control-label text-right pr-2">Email</label>
				<div class="col-lg-8">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-envelope"></i></span>
						</div>
						<input type="email" name="email" id="email" class="form-control" value="{{old('email', $data->email ?? '')}}" placeholder="Ingrese email">
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label for="urlweb" class="col-lg-4 control-label text-right pr-2">URL Web</label>
				<div class="col-lg-8">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input type="text" name="urlweb" id="urlweb" class="form-control" value="{{old('urlweb', $data->urlweb ?? '')}}">
					</div>
				</div>
			</div>
			@if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI')
				<div class="form-group row">
					<label for="vaweb" class="col-lg-4 control-label text-right pr-2 requerido">Va a web</label>
					<div class="col-lg-8">
						<select name="vaweb" class="form-control">
							<option value="">-- Elija si va a web --</option>
							@foreach($vaweb_enum as $value => $vaweb)
								<option value="{{ $value }}" {{ (string) old('vaweb', $data->vaweb ?? '') === (string) $value ? 'selected' : '' }}>{{ $vaweb }}</option>
							@endforeach
						</select>
					</div>
				</div>
			@endif
		</div>
	</div>
	<h5 class="mt-2 mb-3">Domicilio</h5>
	<div class="row">
		<div class="col-lg-6">
			<div class="form-group row">
				<label for="pais_id" class="col-lg-4 control-label text-right pr-2 requerido">Pa&iacute;s</label>
				<div class="col-lg-8">
					<select name="pais_id" id="pais_id" data-placeholder="País" class="form-control" required data-fouc>
						<option value="">-- Seleccionar --</option>
						@foreach($pais_query as $key => $value)
							<option value="{{ $value->id }}" {{ (int) $value->id === (int) old('pais_id', $data->pais_id ?? '') ? 'selected' : '' }}>{{ $value->nombre }}</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="form-group row" id="prov">
				<label for="provincia_id" class="col-lg-4 control-label text-right pr-2 requerido">Provincia</label>
				<div class="col-lg-8">
					<select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="form-control" required data-fouc>
						<option value="">-- Seleccionar --</option>
						@foreach($provincia_query as $key => $value)
							<option value="{{ $value->id }}" {{ (int) $value->id === (int) old('provincia_id', $data->provincia_id ?? '') ? 'selected' : '' }}>{{ $value->nombre }}</option>
						@endforeach
					</select>
					<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $data->desc_provincia ?? '')}}" >
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="form-group row" id="loc">
				<label for="codigolocalidad" class="col-lg-4 control-label text-right pr-2">Localidad</label>
				<div class="col-lg-8">
					<input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{old('localidad_id', $data->localidad_id ?? '')}}" >
					<input type="hidden" id="desc_localidad" name="desc_localidad" value="{{old('desc_localidad', $data->desc_localidad ?? '')}}" >
					<input type="hidden" class="localidad_id" id="localidad_id" name="localidad_id" value="{{ old('localidad_id', $data->localidad_id ?? '') }}" >
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<button type="button" title="Consulta localidades (F1)" class="btn-accion-tabla consultalocalidad tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" class="form-control codigolocalidad flex-shrink-0" id="codigolocalidad" name="codigolocalidad"
							value="{{ old('codigolocalidad', $data->localidades->codigo ?? '') }}"
							placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
						<input type="text" class="form-control nombrelocalidad" id="nombrelocalidad" name="nombrelocalidad"
							value="{{ old('nombrelocalidad', $data->localidades->nombre ?? '') }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label for="codigopostal" class="col-lg-4 control-label text-right pr-2">C&oacute;digo postal</label>
				<div class="col-lg-8">
					<input type="text" name="codigopostal" id="codigopostal" value="{{old('codigopostal', $data['codigopostal'] ?? '')}}" class="form-control" placeholder="C&oacute;digo postal">
				</div>
			</div>
		</div>
	</div>
	<div class="form-group row">
		<label for="domicilio" class="col-lg-2 control-label text-right pr-2 requerido">Direcci&oacute;n</label>
		<div class="col-lg-4">
			<input type="text" name="domicilio" id="domicilio" class="form-control" maxlength="255" value="{{old('domicilio', $data->domicilio ?? '')}}" required/>
		</div>
		<div class="col-lg-4">
			@if ($tasaarba != '')
				<label for="Tasaarba" class="col-form-label mb-0">Tasa ARBA: {{$tasaarba}} %</label>
			@endif
			@if ($tasacaba != '')
				<label for="Tasacaba" class="col-form-label mb-0">Tasa CABA: {{$tasacaba}} %</label>
			@endif
		</div>
		<div class="col-lg-2">
			<label for="Tiposuspension" id="nombretiposuspension" class="col-form-label text-danger mb-0"></label>
		</div>
	</div>
	<input type="hidden" id="estado" name="estado" value="{{old('estado', $data->estado ?? '')}}" >
	<input type="hidden" id="tipoalta" name="tipoalta" value="{{$tipoalta ?? ''}}" >
	<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$data->tiposuspension_id ?? ''}}" >
	<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >
	@if (!empty($urlOrigen))
		<input type="hidden" name="urlOrigen" value="{{ $urlOrigen }}">
	@endif
	<input type="hidden" name="idRemoto" value="{{$idRemoto ?? ''}}" >
	<input type="hidden" id="cliente_id" value="{{$data->id ?? ''}}" >
</div>
@include('includes.configuracion.modalconsultalocalidad')
