<div class="form2" style="display: none">
	<div class="row">
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="tipodocumento_id" class="col-lg-4 col-form-label requerido">Documento</label>
				<select name="tipodocumento_id" id="tipodocumento_id" data-placeholder="Tipo de documento" class="col-lg-2 form-control" required data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($tipodocumento_query as $key => $value)
						@if( (int) $value->id == (int) old('tipodocumento_id', $data->tipodocumento_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
						@endif
					@endforeach
				</select>
				<input type="hidden" id="condicioniva_query" value="{{$condicioniva_query}}">
				<span class="input-group-text">#</span>
				<input type="text" name="numerodocumento" id="numerodocumento" class="col-lg-3 form-control" value="{{$data->numerodocumento??''}}">
			</div>			
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="retieneiva" class="col-lg-4 col-form-label requerido">Percepción iva</label>
				@else
					<label for="retieneiva" class="col-lg-4 col-form-label">Percepción iva</label>
				@endif
				<select name="retieneiva" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija percepción iva --</option>
					@foreach ($retieneiva_enum as $value => $retieneiva)
						<option value="{{ $value }}"
							@if (old('retieneiva', $data->retieneiva ?? '') == $value) selected @endif
							>{{ $retieneiva }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="modofacturacion" class="col-lg-4 col-form-label requerido">Modo facturaci&oacute;n</label>
				@else
					<label for="modofacturacion" class="col-lg-4 col-form-label">Modo facturaci&oacute;n</label>
				@endif
				<select name="modofacturacion" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija modo de facturac&oacute;n --</option>
					@foreach ($modofacturacion_enum as $value => $modofacturacion)
						<option value="{{ $value }}"
							@if (old('modofacturacion', $data->modofacturacion ?? '') == $value) selected @endif
							>{{ $modofacturacion }}</option>
					@endforeach
				</select>
				@if (config('app.empresa') == 'EL BIERZO')
					<label for="logistica" class="col-lg-2 col-form-label">Logística</label>
					<input type="number" name="porcentajelogistica" id="porcentajelogistica" class="col-lg-2 form-control" value="{{old('porcentajelogistica', $data->porcentajelogistica ?? '0')}}"/>
				@endif
			</div>
			<div class="form-group row">
				<label for="nroiibb" class="col-lg-4 col-form-label">Nro.IIBB</label>
				<div class="col-lg-3">
					<input type="text" name="nroiibb" id="nroiibb" class="form-control" value="{{old('nroiibb', $data->nroiibb ?? '')}}"/>
				</div>
				@if (config('app.empresa') == 'EL BIERZO')
					<label for="emitecertificado" class="col-lg-2 col-form-label">Emite Cert.</label>
					<select name="emitecertificado" class="col-lg-3 form-control">
						<option value="">-- Elija bonificación --</option>
						@foreach ($emitecertificado_enum as $value => $emitecertificado)
							<option value="{{ $emitecertificado }}"
								@if (old('emitecertificado', $data->emitecertificado ?? '') == $emitecertificado) selected @endif
								>{{ $emitecertificado }}</option>
						@endforeach
					</select>		
				@endif
			</div>
			<div class="form-group row">
				<label for="zonavta" class="col-lg-4 col-form-label">Zona de venta</label>
				<select name="zonavta_id" id="zonavta_id" data-placeholder="Zona de venta" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Zona de Venta --</option>
					@foreach($zonavta_query as $key => $value)
						@if( (int) $value->id == (int) old('zonavta_id', $data->zonavta_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			@if (config('app.empresa') == 'EL BIERZO')
				<input type="hidden" name="subzonavta_id" id="subzonavta_id" class="form-control" value="{{old('subzonavta_id', $data->subzonavta_id ?? '')}}">
			@else
				<div class="form-group row">
					<label for="subzonavta" class="col-lg-4 col-form-label">Subzona de venta</label>
					<select name="subzonavta_id" id="subzonavta_id" data-placeholder="Subzona de venta" class="col-lg-5 form-control" data-fouc>
						<option value="">-- Seleccionar Subzona --</option>
						@foreach($subzonavta_query as $key => $value)
							@if( (int) $value->id == (int) old('subzonavta_id', $data->subzonavta_id ?? ''))
								<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
							@else
								<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
							@endif
						@endforeach
					</select>
				</div>
			@endif
			<div class="form-group row">
				<label for="condicionventa" class="col-lg-4 col-form-label">Condici&oacute;n de venta</label>
				<select name="condicionventa_id" id="condicionventa_id" data-placeholder="Vendedor" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar Cond. Venta --</option>
					@foreach($condicionventa_query as $key => $value)
						@if( (int) $value->id == (int) old('condicionventa_id', $data->condicionventa_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="listaprecio" class="col-lg-4 col-form-label">Lista de precio</label>
				<select name="listaprecio_id" id="listaprecio_id" data-placeholder="Lista de precio" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar lista de precio --</option>
					@foreach($listaprecio_query as $key => $value)
						@if( (int) $value->id == (int) old('listaprecio_id', $data->listaprecio_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			@if (config('app.empresa') == 'EL BIERZO')
				<div class="form-group row">
					<label for="abasto" class="col-lg-4 col-form-label">Abasto</label>
					<select name="abasto_id" id="abasto_id" data-placeholder="Abasto" class="col-lg-5 form-control" data-fouc>
						<option value="">-- Seleccionar abasto --</option>
						@foreach($abasto_query as $key => $value)
							@if( (int) $value->id == (int) old('abasto_id', $data->abasto_id ?? ''))
								<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
							@else
								<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
							@endif
						@endforeach
					</select>
				</div>
				<div class="form-group row">
					<label for="desdefecha_exclusionpercepcioniva" class="col-lg-4 col-form-label">Desde Fecha Excl. Perc. Iva</label>
					<div class="col-lg-3">
						<input type="date" name="desdefecha_exclusionpercepcioniva" id="desdefecha_exclusionpercepcioniva" class="form-control" value="{{substr(old('desdefecha_exclusionpercepcioniva', $data->desdefecha_exclusionpercepcioniva ?? ''),0,10)}}">
					</div>
				</div>
			@endif
		</div>
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="condicioniva_id" class="col-lg-4 col-form-label requerido">Condicion de iva.</label>
				<select name="condicioniva_id" id="condicioniva_id" data-placeholder="Condicion de iva" class="col-lg-5 form-control" required data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($condicioniva_query as $key => $value)
						@if( (int) $value->id == (int) old('condicioniva_id', $data->condicioniva_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
				<input type="hidden" id="condicioniva_query" value="{{$condicioniva_query}}">
				<span class="input-group-text">#</span>
				<input type="text" name="letra" id="letra" class="col-lg-1 form-control" value="" readonly>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P') 
					<label for="condicioniibb" class="col-lg-4 col-form-label requerido">Condici&oacute;n IIBB</label>
				@else
					<label for="condicioniibb" class="col-lg-4 col-form-label">Condici&oacute;n IIBB</label>
				@endif
				<select name="condicioniibb" class="col-lg-5 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija condici&oacute;n IIBB --</option>
					@foreach ($condicioniibb_enum as $value => $condicioniibb)
						<option value="{{ $value }}"
							@if (old('condicioniibb', $data->condicioniibb ?? '') == $value) selected @endif
							>{{ $condicioniibb }}</option>
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="vendedor" class="col-lg-4 col-form-label">Vendedor</label>
				<select name="vendedor_id" id="vendedor_id" data-placeholder="Vendedor" class="col-lg-3 form-control" data-fouc>
					<option value="">-- Seleccionar Vendedor --</option>
					@foreach($vendedor_query as $key => $value)
						@if( (int) $value->id == (int) old('vendedor_id', $data->vendedor_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
				<label for="distribuidor" class="col-lg-2 col-form-label">Distribuidor</label>
				<select name="distribuidor_id" id="distribuidor_id" data-placeholder="Distribuidor" class="col-lg-3 form-control" data-fouc>
					<option value="">-- Seleccionar Distribuidor --</option>
					@foreach($distribuidor_query as $key => $value)
						@if( (int) $value->id == (int) old('distribuidor_id', $data->distribuidor_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="transporte" class="col-lg-4 col-form-label">Reparto</label>
				<select name="transporte_id" id="transporte_id" data-placeholder="Reparto" class="col-lg-8 form-control" data-fouc>
					<option value="">-- Seleccionar Reparto --</option>
					@foreach($transporte_query as $key => $value)
						@if( (int) $value->id == (int) old('transporte_id', $data->transporte_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				@if ($tipoalta != 'P') 
					<label for="cuentacontable" class="col-lg-4 col-form-label requerido">Cuenta contable</label>
				@else
					<label for="cuentacontable" class="col-lg-4 col-form-label">Cuenta contable</label>
				@endif
				<select name="cuentacontable_id" id="cuentacontable_id" data-placeholder="Cuenta contable para imputaciones" class="col-lg-8 form-control" data-fouc @if ($tipoalta != 'P') required @endif>
					<option value="">-- Seleccionar Cta. Contable --</option>
					@foreach($cuentacontable_query as $key => $value)
						@if (isset($data->cuentacontable_id) ? 
							(int) $value->id == (int) old('cuentacontable_id', $data->cuentacontable_id ?? '') : 
							config('cliente.DEUDORES_POR_VENTAS') == $value->codigo)
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			@if (can('modificar-descuento-cliente', false))
				<div class="form-group row">
					@if (config('app.empresa') == 'EL BIERZO')
						<label for="agregabonificacion" class="col-lg-3 col-form-label">Agrega Bonif.</label>
						<select name="agregabonificacion" class="col-lg-4 form-control">
							<option value="">-- Elija bonificación --</option>
							@foreach ($agregabonificacion_enum as $value => $agregabonificacion)
								<option value="{{ $agregabonificacion }}"
									@if (old('agregabonificacion', $data->agregabonificacion ?? '') == $agregabonificacion) selected @endif
									>{{ $agregabonificacion }}</option>
							@endforeach
						</select>			
					@endif
					<label for="descuento" class="col-lg-2 col-form-label">Descuento</label>
					<span class="input-group-text"><i class="fas fa-percent"></i></span>
					<div class="col-lg-2">
						<input type="text" name="descuento" id="descuento" class="form-control" value="{{old('descuento', $data->descuento ?? '0')}}">
					</div>								
				</div>
			@else
				<input type="hidden" name="descuento" id="descuento" class="form-control" value="{{old('descuento', $data->descuento ?? '0')}}">
			@endif
			@if (config('app.empresa') == 'EL BIERZO')
				<input type="hidden" name="descuentoventa_id" id="descuentoventa_id" class="form-control" value="{{old('descuentoventa_id', $data->descuentoventa_id ?? '')}}">
				@if (can('cargar-coeficiente-cliente', false))
					<div class="form-group row">
						<label for="coeficiente" class="col-lg-4 col-form-label">Coeficiente</label>
						<select name="coeficiente_id" id="coeficiente_id" data-placeholder="Coeficiente" class="col-lg-5 form-control" data-fouc>
							<option value="">-- Seleccionar Coeficiente --</option>
							@foreach($coeficiente_query as $key => $value)
								@if( (int) $value->id == (int) old('coeficiente_id', $data->coeficiente_id ?? ''))
									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
								@endif
							@endforeach
						</select>
					</div>
					<div class="form-group row">
						<label for="coeficienteextra" class="col-lg-3 col-form-label">Coeficiente Extra</label>
						<span class="input-group-text"><i class="fas fa-percent"></i></span>
						<div class="col-lg-3">
							<input type="number" min="0" max="100" name="coeficienteextra" id="coeficienteextra" class="form-control" value="{{old('coeficienteextra', $data->coeficienteextra ?? '0')}}">
						</div>
					</div>
				@endif
				<div class="form-group row">
					<label for="hastafecha_exclusionpercepcioniva" class="col-lg-4 col-form-label">Hasta Fecha Excl. Perc. Iva</label>
					<div class="col-lg-3">
						<input type="date" name="hastafecha_exclusionpercepcioniva" id="hastafecha_exclusionpercepcioniva" class="form-control" value="{{substr(old('hastafecha_exclusionpercepcioniva', $data->hastafecha_exclusionpercepcioniva ?? ''),0,10)}}">
					</div>
				</div>				
			@endif
			@if (strtoupper(config('app.empresa')) == 'CALZADOS FERLI')
				<div class="form-group row">
					@if ($tipoalta != 'P')
						<label for="cajaespecial" class="col-lg-4 col-form-label requerido">Caja especial</label>
					@else
						<label for="cajaespecial" class="col-lg-4 col-form-label">Caja especial</label>
					@endif
					<select name="cajaespecial" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
						<option value="">-- Elija si lleva caja especial --</option>
						@foreach ($cajaespecial_enum as $value => $cajaespecial)
							<option value="{{ $value }}"
								@if (old('cajaespecial', $data->cajaespecial ?? '') == $value) selected @endif
								>{{ $cajaespecial }}</option>
						@endforeach
					</select>
				</div>
			@endif
		</div>
	</div>
</div>
