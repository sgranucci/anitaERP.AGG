<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $guia->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="tipodocumento" class="col-lg-3 col-form-label requerido">Tipo de documento</label>
	<select id="tipodocumento" name="tipodocumento" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de documento --</option>
       	@foreach($tipodocumento_enum as $tipodocumento)
			@if ($tipodocumento['valor'] == old('tipodocumento',$guia->tipodocumento??''))
       			<option value="{{ $tipodocumento['valor'] }}" selected>{{ $tipodocumento['nombre'] }}</option>    
			@else
			    <option value="{{ $tipodocumento['valor'] }}">{{ $tipodocumento['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="numerodocumento" class="col-lg-3 col-form-label requerido">Número de documento</label>
    <div class="col-lg-3">
    <input type="number" name="numerodocumento" id="numerodocumento" class="form-control" value="{{old('numerodocumento', $guia->numerodocumento ?? '')}}">
    </div>
</div>
<div class="form-group row">
	<label for="tipoguia" class="col-lg-3 col-form-label requerido">Tipo de guía</label>
	<select id="tipoguia" name="tipoguia" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de guía --</option>
       	@foreach($tipoguia_enum as $tipoguia)
			@if ($tipoguia['valor'] == old('tipoguia',$guia->tipoguia??''))
       			<option value="{{ $tipoguia['valor'] }}" selected>{{ $tipoguia['nombre'] }}</option>    
			@else
			    <option value="{{ $tipoguia['valor'] }}">{{ $tipoguia['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="maneja" class="col-lg-3 col-form-label requerido">Maneja</label>
	<select id="maneja" name="maneja" class="col-lg-4 form-control" required>
    	<option value="">-- Elija si maneja o no --</option>
       	@foreach($maneja_enum as $maneja)
			@if ($maneja['valor'] == old('maneja',$guia->maneja??''))
       			<option value="{{ $maneja['valor'] }}" selected>{{ $maneja['nombre'] }}</option>    
			@else
			    <option value="{{ $maneja['valor'] }}">{{ $maneja['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row divcarnetguia">
    <label for="carnetguia" class="col-lg-3 col-form-label">Carnet de Guía</label>
    <div class="col-lg-4">
    <input type="text" name="carnetguia" id="carnetguia" class="form-control" value="{{old('carnetguia', $guia->carnetguia ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="carnetconducir" class="col-lg-3 col-form-label">Carnet de Conducir</label>
    <div class="col-lg-4">
    <input type="text" name="carnetconducir" id="carnetconducir" class="form-control" value="{{old('carnetconducir', $guia->carnetconducir ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="categoriacarnetconducir" class="col-lg-3 col-form-label">Categoría Carnet de Conducir</label>
    <div class="col-lg-4">
    <input type="text" name="categoriacarnetconducir" id="categoriacarnetconducir" class="form-control" value="{{old('categoriacarnetconducir', $guia->categoriacarnetconducir ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="carnetsanidad" class="col-lg-3 col-form-label">Carnet de Sanidad</label>
    <div class="col-lg-4">
    <input type="text" name="carnetsanidad" id="carnetsanidad" class="form-control" value="{{old('carnetsanidad', $guia->carnetsanidad ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
	<label for="telefono" class="col-lg-3 col-form-label requerido">Teléfono</label>
	<span class="input-group-text"><i class="fas fa-phone"></i></span>
	<div class="col-lg-8">
	<input type="text" name="telefono" id="telefono" class="form-control" value="{{old('telefono', $guia->telefono ?? '')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="email" class="col-lg-3 col-form-label">Email</label>
	<span class="input-group-text"><i class="fas fa-envelope"></i></span>
	<div class="col-lg-8">
		<input type="email" name="email" id="email" class="form-control" value="{{old('email', $guia->email ?? '')}}" placeholder="Ingrese email">
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
						@if( (int) $value->id == (int) old('pais_id', $guia->pais_id ?? ''))
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
						@if( (int) $value->id == (int) old('provincia_id', $guia->provincia_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
				<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia', $guia->desc_provincia ?? '')}}" >
			</div>
		</div>
		<div class="col-md-3" id='loc'>
			<div class="form-group">
				<label>Localidad</label>
				<select name="localidad_id" id='localidad_id' data-placeholder="Localidad" class="form-control" data-fouc>
					@if($guia->localidad_id ?? '')
						@if($guia->localidad_id == "")
							<option selected></option>
						@else
							<option value="{{old('localidad_id', $guia->localidad_id)}}" selected>{{$guia['desc_localidad']}}</option>
						@endif
					@endif
				</select>
				<input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{old('localidad_id', $guia->localidad_id ?? '')}}" >
				<input type="hidden" id="desc_localidad" name="desc_localidad" value="{{old('desc_localidad', $guia->desc_localidad ?? '')}}" >
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
			<label for="domicilio" class="col-lg-2 col-form-label">Direcci&oacuten</label>
			<div class="col-lg-4">
				<input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $guia->domicilio ?? '')}}"/>
			</div>
		</div>
	</div>
</div>
<div class="form-group row">
    <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
    <div class="col-lg-8">
    <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $guia->observacion ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-1">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $guia->codigo ?? '0')}}" readonly>
    </div>
</div>
<div class="card">
    <div class="card-header">
    	<h3>Idiomas</h3>
    </div>

    <div class="card-body">
    	<table class="table" id="idiomas-table">
    		<thead>
    			<tr>
					<th>Renglón</th>
    				<th>Idioma</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
			@if ($guia->guia_idiomas ?? '') 
				@foreach (old('idiomas', $guia->guia_idiomas->count() ? $guia->guia_idiomas : ['']) as $guia_idioma)
					<tr class="item-idioma">
						<td>
							<input type="number" name="items[]" class="form-control iiitem" readonly value="{{ $loop->index+1 }}" />
						</td>
						<td>
							<select name="idioma_ids[]" id="idioma_ids" data-placeholder="Idioma" class="form-control idioma" data-fouc>
								<option value=""></option>
								@foreach($idioma_query as $key => $value)
									@if( (int) $value->id == (int) old('idioma_ids', $guia_idioma->idioma_id ?? ''))
										<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
									@else
										<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
									@endif
								@endforeach
							</select>
						</td>
						<td>
							<button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar tooltipsC">
								<i class="fa fa-times-circle text-danger"></i>
							</button>
						</td>
					</tr>
				@endforeach
			@endif
       		</tbody>
       	</table>
		@include('receptivo.guia.template')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
