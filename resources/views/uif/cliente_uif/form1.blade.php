<div class="form1">
	@php
		$uifCtxForm = $uifContexto ?? \App\Support\Uif\ClienteUifOrigenPcSupport::contexto();
		$empresasUifForm = $uifCtxForm['empresas_uif'] ?? collect();
		$origenClienteForm = \App\Support\Uif\ClienteUifOrigenPcSupport::origenDeCliente($data ?? null);
		$empresaDesdeCliente = $origenClienteForm
			? \App\Support\Uif\ClienteUifOrigenPcSupport::empresaIdDesdeOrigen($origenClienteForm)
			: null;
		$empresaFormId = old('empresa_id', $empresaDesdeCliente ?? $uifCtxForm['empresa_id'] ?? '');
		$empresaSoloLectura = !empty($uifCtxForm['origen_fijo']) || !empty($origenClienteForm);
	@endphp
		<div class="row">
			<div class="col-sm-6">
				@if ($empresaSoloLectura)
					@php
						$empIdLock = (int) ($empresaDesdeCliente ?? $uifCtxForm['empresa_id'] ?? 0);
						$empLabelLock = $origenClienteForm
							? \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen($origenClienteForm)
							: ($uifCtxForm['label'] ?? '');
						$empNombreLock = $empresasUifForm->firstWhere('id', $empIdLock)->nombre
							?? ($uifCtxForm['empresa_nombre'] ?? '');
					@endphp
					<input type="hidden" name="empresa_id" value="{{ $empIdLock }}">
					<div class="form-group row">
						<label class="col-lg-3 col-form-label">Empresa / origen</label>
						<div class="col-lg-6">
							<input type="text" class="form-control" value="{{ $empLabelLock }} — {{ $empNombreLock }}" readonly>
						</div>
					</div>
				@else
					@include('includes.form-empresa-asignada', [
						'empresa_query' => $empresasUifForm,
						'empresa_id' => $empresaFormId,
						'label' => 'Empresa / sala',
						'col_label' => 'col-lg-3',
						'col_input' => 'col-lg-6',
						'required' => true,
						'permite_vacio' => false,
					])
				@endif
				<div class="form-group row">
    				<label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    				<div class="col-lg-6">
    					<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    				</div>
				</div>
				<div class="form-group row">
					<div class="input-group mb-3">
						<label for="documento" class="col-lg-3 col-form-label requerido">Documento</label>
						<select name="tipodocumento_id" id="tipodocumento_id" data-placeholder="Tipo de documento" class="col-lg-1 form-control required" data-fouc>
        					<option value="">---</option>
        					@foreach($tipodocumento_query as $key => $value)
        						@if( (int) $value->id == (int) old('tipodocumento_id', $data->tipodocumento_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
        						@endif
        					@endforeach
        				</select>
						<span class="input-group-text">#</span>
						<input type="text" name="numerodocumento" id="numerodocumento" class="col-lg-3 form-control" placeholder="Nro. de documento" aria-label="Número" maxlength="13" value="{{ old('numerodocumento', $data->numerodocumento ?? '') }}" required>
						<span class="input-group-text">CUIT</span>
						<input type="text" name="cuit" id="cuit" class="col-lg-3 form-control" placeholder="XX-XXXXXXXX-X" aria-label="CUIT" maxlength="13" value="{{ old('cuit', $data->cuit ?? '') }}" oninput="typeof formatarCUIT === 'function' && formatarCUIT(this)">
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group row">
    				<label for="telefono" class="col-lg-3 col-form-label requerido">Teléfono</label>
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
			</div>
		</div>
		<div class='col-md-12'>
			<div class="row mt-0">
				<div class="col-md-3">
					<div class="form-group row">
						<label for="sexo" class="col-lg-3 col-form-label requerido">Sexo</label>
						<select id="sexo" name="sexo" class="col-lg-4 form-control" required>
							<option value="">-- Elija sexo --</option>
							@foreach($sexo_enum as $sexo)
								@if ($sexo['nombre'] == old('sexo',$data->sexo??''))
									<option value="{{ $sexo['nombre'] }}" selected>{{ $sexo['nombre'] }}</option>    
								@else
									<option value="{{ $sexo['nombre'] }}">{{ $sexo['nombre'] }}</option>
								@endif
							@endforeach
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group row">
						<label for="estadocivil_uif" class="col-lg-4 col-form-label requerido">Estado Civil</label>
						<select name="estadocivil_uif_id" id="estadocivil_uif_id" data-placeholder="Estado Civil" class="form-control col-lg-5 required" data-fouc>
							<option value="">-- Seleccionar --</option>
							@foreach($estadocivil_uif_query as $key => $value)
								@if( (int) $value->id == (int) old('estadocivil_uif_id', $data->estadocivil_uif_id ?? ''))
									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
								@endif
							@endforeach
						</select>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group row">
						<label for="actividad_uif" class="col-lg-3 col-form-label requerido">Actividad</label>
						<input type="text" class="col-lg-1" id="actividad_uif_id" name="actividad_uif_id" value="{{$data->actividad_uif_id??''}}" required>
						<button type="button" title="Consulta actividades" style="padding:1;" class="btn-accion-tabla consultaactividad_uif tooltipsC">
								<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" class="col-lg-7 nombreactividad_uif form-control" id="nombreactividad_uif" name="nombreactividad_uif" value="{{$data->actividades_uif->nombre??''}}" >
					</div>					
				</div>
			</div>
		</div>
        <div class='col-md-12'>
        	<div class="row mt-0">
        		<div class="col-md-3">
        			<div class="form-group">
        				<label class="requerido">País Nacimiento</label>
        				<select name="paisnacimiento_id" id="paisnacimiento_id" data-placeholder="País" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($pais_uif_query as $key => $value)
        						@if( (int) $value->id == (int) old('paisnacimiento_id', $data->paisnacimiento_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        			</div>
        		</div>
        		<div class="col-md-3" id='provnac'>
        			<div class="form-group">
        				<label class="requerido">Provincia Nacimiento</label>
        				<select name="provincianacimiento_id" id="provincianacimiento_id" data-placeholder="Provincia Nacimiento" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($provincia_uif_query as $key => $value)
        						@if( (int) $value->id == (int) old('provincianacimiento_id', $data->provincianacimiento_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        				<input type="hidden" id="desc_provincianacimiento" name="desc_provincianacimiento" value="{{old('desc_provincianacimiento', $data->provincia_nacimientos->nombre ?? '')}}" >
        			</div>
        		</div>				
        		<div class="col-md-3" id='locnac'>
        			<div class="form-group">
        				<label>Localidad Nacimiento</label>
        				<select name="localidadnacimiento_id" id='localidadnacimiento_id' data-placeholder="Localidad Nacimiento" class="form-control" data-fouc>
        					@if($data->localidadnacimiento_id ?? '')
								@if($data->localidadnacimiento_id == "")
        							<option selected></option>
        						@else
        							<option value="{{old('localidadnacimiento_id', $data->localidadnacimiento_id)}}" selected>{{$data->localidad_nacimientos->nombre}}</option>
								@endif
        					@endif
        				</select>
        				<input type="hidden" id="localidadnacimiento_id_previa" name="localidadnacimiento_id_previa" value="{{old('localidadnacimiento_id', $data->localidadnacimiento_id ?? '')}}" >
        				<input type="hidden" id="desc_localidadnacimiento" name="desc_localidadnacimiento" value="{{old('desc_localidadnacimiento', $data->localidad_nacimientos->nombre ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3">
        			<div class="form-group">
        				<label>Fecha de Nacimiento</label>
        				<input type="date" name="fechanacimiento" id="fechanacimiento" value="{{old('fechanacimiento', $data['fechanacimiento'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha de nacimiento">
        			</div>
        		</div>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group row">
					<div class="input-group mb-3">
    					<label for="domicilio" class="col-lg-2 col-form-label requerido">Domicilio</label>
    					<input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $data->domicilio ?? '')}}" required/>
						<span class="input-group-text">Piso</span>
						<input type="text" name="piso" id="piso" class="col-lg-2 form-control" placeholder="Piso" aria-label="Piso" value="{{$data->piso??''}}">
						<span class="input-group-text">Depto</span>
						<input type="text" name="departamento" id="departamento" class="col-lg-2 form-control" placeholder="Departamento" aria-label="Depto" value="{{$data->departamento??''}}">
    				</div>
				</div>
			</div>
		</div>
        <div class='col-md-12'>
        	<div class="row mt-0">
        		<div class="col-md-3">
        			<div class="form-group">
        				<label class="requerido">País de residencia</label>
        				<select name="pais_uif_id" id="pais_uif_id" data-placeholder="País de residencia" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($pais_uif_query as $key => $value)
        						@if( (int) $value->id == (int) old('pais_uif_id', $data->pais_uif_id ?? ''))
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
        				<label class="requerido">Provincia de residencia</label>
        				<select name="provincia_uif_id" id="provincia_uif_id" data-placeholder="Provincia de residencia" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@foreach($provincia_uif_query as $key => $value)
        						@if( (int) $value->id == (int) old('provincia_uif_id', $data->provincia_uif_id ?? ''))
        							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        						@else
        							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        						@endif
        					@endforeach
        				</select>
        				<input type="hidden" id="desc_provincia_uif" name="desc_provincia_uif" value="{{old('desc_provincia_uif', $data->desc_provincia_uif ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3" id='loc'>
        			<div class="form-group">
        				<label class="requerido">Localidad de residencia</label>
        				<select name="localidad_uif_id" id='localidad_uif_id' data-placeholder="Localidad de residencia" class="form-control required" data-fouc>
        					<option value="">-- Seleccionar --</option>
        					@if($data->localidad_uif_id ?? '')
								@if($data->localidad_uif_id == "")
        							<option selected></option>
        						@else
        							<option value="{{old('localidad_uif_id', $data['localidad_uif_id'])}}" selected>{{$data->localidades_uif->nombre}}</option>
								@endif
        					@endif
        				</select>
        				<input type="hidden" id="localidad_uif_id_previa" name="localidad_uif_id_previa" value="{{old('localidad_uif_id', $data->localidad_uif_id ?? '')}}" >
        				<input type="hidden" id="desc_localidad_uif" name="desc_localidad_uif" value="{{old('desc_localidad_uif', $data->localidades_uif->nombre ?? '')}}" >
        			</div>
        		</div>
        		<div class="col-md-3">
        			<div class="form-group">
        				<label>Código Postal</label>
        				<input type="text" name="codigopostal" id="codigopostal" value="{{old('codigopostal', $data['codigopostal'] ?? '')}}" class="col-lg-5 form-control" placeholder="Codigo Postal">
        			</div>
				</div>
			</div>
		</div>
        <div class='col-md-12'>
			<div class="form-group row">
				<label for="fotodocumento">Foto DNI:</label>
				<input type="file" id="fotodocumento" name="fotodocumento" accept="image/jpeg,image/png,image/gif,image/webp" style="color: transparent" value="{{$data->fotodocumento ?? ''}}">
				<div id="archivoseleccionado" style="align: left;"></div>
				<div id="fotodocumento-preview-nuevo" class="mt-2" style="display: none;">
					<small class="text-muted d-block mb-1">Vista previa (archivo seleccionado):</small>
					<div class="text-center bg-white border rounded p-2">
						<img id="fotodocumento-preview-img" src="" alt="Vista previa" class="img-fluid rounded" style="max-height: min(18vh, 160px); max-width: 100%; object-fit: contain;">
					</div>
				</div>
				@if (!empty($data['fotodocumento']))
					@php
						$fotoNombre = $data['fotodocumento'];
						$fotoBasename = basename($fotoNombre);
						$fid = $data->id ?? null;
						$rutaVerDocumento = $fid ? route('cliente_uif_fotodocumento', ['id' => $fid]) : '#';
						$esImagen = preg_match('/\.(jpe?g|png|gif|webp)$/i', $fotoNombre);
						$esPdf = preg_match('/\.pdf$/i', $fotoNombre);
					@endphp
					@if ($fid)
					<div class="mt-3 cliente-uif-fotodocumento-vista border rounded bg-light p-2">
						@if ($esPdf)
							<div class="rounded overflow-hidden bg-white border shadow-sm">
								<iframe src="{{ $rutaVerDocumento }}" class="w-100 border-0 d-block bg-secondary" style="height: min(24vh, 260px); min-height: 140px;" title="Vista previa PDF del documento"></iframe>
							</div>
						@elseif ($esImagen)
							<div class="text-center p-2 bg-white rounded border shadow-sm">
								<a href="{{ $rutaVerDocumento }}" target="_blank" rel="noopener noreferrer" title="Abrir imagen en tamaño completo">
									<img src="{{ $rutaVerDocumento }}" alt="Foto documento" class="img-fluid rounded" style="max-height: min(20vh, 180px); max-width: 100%; width: auto; object-fit: contain;">
								</a>
							</div>
						@else
							<div class="text-center py-3">
								<a href="{{ $rutaVerDocumento }}" target="_blank" rel="noopener noreferrer" class="text-secondary" title="Abrir archivo">
									<i class="fa fa-file-o fa-3x d-block mb-2"></i>
									<span class="small text-break d-inline-block" style="max-width: 100%;">{{ $fotoBasename }}</span>
								</a>
							</div>
						@endif
						<div class="d-flex flex-wrap align-items-center mt-3 pt-2 border-top" style="gap: 8px;">
							<a class="btn btn-sm btn-outline-primary" href="{{ route('cliente_uif_fotodocumento', ['id' => $fid]) }}?disposition=attachment">
								<i class="fa fa-download"></i> Descargar
							</a>
							<a class="btn btn-sm btn-outline-secondary" href="{{ $rutaVerDocumento }}" target="_blank" rel="noopener noreferrer" title="Abrir en nueva pestaña">
								<i class="fa fa-external-link-alt"></i> Abrir en pestaña nueva
							</a>
							@if (can('actualizar-cliente-uif', false))
							<button type="button" class="btn btn-sm btn-outline-danger" data-url="{{ route('elimina_fotodocumento_cliente_uif', ['id' => $fid]) }}" onclick="eliminarFotoDocumentoClienteUif(this.dataset.url)">
								<i class="fa fa-trash"></i> Borrar foto
							</button>
							@endif
						</div>
					</div>
					@else
						<p class="small text-muted mt-2 mb-0">Guarde el cliente para gestionar la foto del documento.</p>
					@endif
				@endif
			</div>
		</div>
        <input type="hidden" id="estado" name="estado" value="{{old('estado', $data->estado ?? '')}}" >
		<input type="hidden" id="cliente_uif_id" name="cliente_uif_id" value="{{old('cliente_uif_id', $data->id ?? '0')}}" >
		<input type="hidden" id="essupervisor" name="essupervisor" value="{{old('essupervisor', $essupervisor ?? '')}}" >
		<input type="hidden" id="uif_perfil_cliente" value="{{ $uifPerfil ?? 'operador' }}" >
</div>
@include('includes.uif.modalconsultaactividad_uif')



