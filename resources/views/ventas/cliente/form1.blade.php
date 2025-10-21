<div class="form1">
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group row">
    				<label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    				<div class="col-lg-8">
    					<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    				<div class="col-lg-2">
    					<input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly>
    				</div>
				</div>
				<div class="form-group row">
    				<label for="fantasia" class="col-lg-3 col-form-label">Fantas&iacute;a</label>
    				<div class="col-lg-8">
    					<input type="text" name="fantasia" id="fantasia" class="form-control" value="{{old('fantasia', $data->fantasia ?? '')}}">
    				</div>
				</div>
				<div class="form-group row">
    				<label for="contacto" class="col-lg-3 col-form-label">Contacto</label>
    				<div class="col-lg-8">
    					<input type="text" name="contacto" id="contacto" class="form-control" value="{{old('contacto', $data->contacto ?? '')}}">
    				</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group row">
    				<label for="telefono" class="col-lg-3 col-form-label requerido">Telefono</label>
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
    				<div class="col-lg-8">
    				<input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $data->telefono ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
   					<label for="email" class="col-lg-3 col-form-label">Email</label>
   					<span class="input-group-text"><i class="fas fa-envelope"></i></span>
   					<div class="col-lg-8">
   						<input type="email" name="email" id="email" class="form-control" value="{{old('email', $data->email ?? '')}}" placeholder="Ingrese email">
   					</div>
				</div>
				<div class="form-group row">
    				<label for="urlweb" class="col-lg-3 col-form-label">URL Web</label>
                    <span class="input-group-text"><i class="fas fa-laptop"></i></span>
    				<div class="col-lg-7">
    					<input type="text" name="urlweb" id="urlweb" class="form-control" value="{{old('urlweb', $data->urlweb ?? '')}}">
    				</div>
    			</div>
				<div class="form-group row">
    				<label for="vaweb" class="col-lg-4 col-form-label requerido">Va a web</label>
					<select name="vaweb" class="col-lg-3 form-control" required>
    					<option value="">-- Elija si va a web --</option>
        				@foreach($vaweb_enum as $value => $vaweb)
        					@if( (int) $value == old('vaweb', $data->vaweb ?? ''))
        						<option value="{{ $value }}" selected="select">{{ $vaweb }}</option>    
        					@else
        						<option value="{{ $value }}">{{ $vaweb }}</option>    
        					@endif
        				@endforeach
					</select>
				</div>
			</div>
		</div>
		<h3>Domicilio</h3>
        <div class='col-md-12'>
        	<div class="row mt-0">
        		<div class="col-md-3">
        			<div class="form-group">
        				<label class="requerido">País</label>
        				<select name="pais_id" id="pais_id" data-placeholder="País" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($pais_query as $key => $value)
        						@if( (int) $value->id == (int) old('pais_id', $data->pais_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        			</div>
        		</div>
        		<div class="col-md-3" id='prov'>
        			<div class="form-group">
        				<label class="requerido">Provincia</label>
        				<select name="provincia_id" id="provincia_id" data-placeholder="Provincia" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($provincia_query as $key => $value)
        						@if( (int) $value->id == (int) old('provincia_id', $data->provincia_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        				<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $data->desc_provincia ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3" id='loc'>
        			<div class="form-group">
        				<label>Localidad</label>
        				<select name="localidad_id" id='localidad_id' data-placeholder="Localidad" class="form-control" data-fouc>
        					@if($data->localidad_id ?? '')
								@if($data->localidad_id == "")
        							<option selected></option>
        						@else
        							<option value="{{old('localidad_id', $data['localidad_id'])}}" selected>{{$data['desc_localidad']}}</option>
								@endif
        					@endif
        				</select>
        				<input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{old('localidad_id', $data->localidad_id ?? '')}}" >
        				<input type="hidden" id="desc_localidad" name="desc_localidad" value="{{old('desc_localidad', $data->desc_localidad ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3">
        			<div class="form-group">
        				<label>Código Postal</label>
        				<input type="text" name="codigopostal" id="codigopostal" value="{{old('codigopostal', $data['codigopostal'] ?? '')}}" class="form-control" placeholder="Codigo Postal">
        			</div>
        		</div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-12">
				<div class="form-group row">
    				<label for="domicilio" class="col-lg-2 col-form-label requerido">Direcci&oacuten</label>
    				<div class="col-lg-4">
    					<input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $data->domicilio ?? '')}}" required/>
    				</div>
    				<div class="col-lg-4">
					@if ($tasaarba != '')
    					<label for="Tasaarba" style="padding: 0px;" class="col-form-label">Tasa ARBA: {{$tasaarba}} %</label>
					@endif
					@if ($tasacaba != '')
    					<label for="Tasacaba" style="padding: 0px;" class="col-form-label">Tasa CABA: {{$tasacaba}} %</label>
					@endif
    				</div>
					<div class="col-lg-2">
						<label for="Tiposuspension" id="nombretiposuspension" style="padding: 0px;" class="col-form-label text-danger"></label>
					</div>
				</div>
			</div>
		</div>
        <input type="hidden" id="estado" name="estado" value="{{old('estado', $data->estado ?? '')}}" >
		<input type="hidden" id="tipoalta" name="tipoalta" value="{{$tipoalta ?? ''}}" >
		<input type="hidden" id="tiposuspension_id" name="tiposuspension_id" value="{{$data->tiposuspension_id ?? ''}}" >
		<input type="hidden" id="tiposuspensioncliente_query" value="{{$tiposuspensioncliente_query ?? ''}}" >
		<input type="hidden" name="urlOrigen" value="{{$urlOrigen ?? ''}}" >
		<input type="hidden" name="idRemoto" value="{{$idRemoto ?? ''}}" >
</div>



