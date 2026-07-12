<div id="tab-datos-facturacion" class="tab-pane fade form2" role="tabpanel">
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
				<input type="text" name="numerodocumento" id="numerodocumento" class="col-lg-3 form-control" value="{{ old('numerodocumento', $data->numerodocumento ?? '') }}">
				@if (!empty($data->facturas_apocrifas))
					<span id="cliente-apoc-estado-badge" class="badge badge-danger ml-1 align-self-center">Facturas ap&oacute;crifas (ARCA)</span>
				@elseif (!empty($data->facturas_apocrifas_consulta_at))
					<span id="cliente-apoc-estado-badge" class="badge badge-success ml-1 align-self-center">Sin registro APOC ({{ $data->facturas_apocrifas_consulta_at->format('d/m/Y H:i') }})</span>
				@else
					<span id="cliente-apoc-estado-badge" class="badge badge-secondary ml-1 align-self-center" style="display: none;"></span>
				@endif
				<span class="d-inline-block position-relative pl-1 align-middle">
					<button type="button" id="btn-consulta-arca-cliente" title="Consultar padrón ARCA" class="btn-accion-tabla tooltipsC" style="padding:1;" onclick="return window.consultaArcaCliente?.(event)">
						<i class="fa fa-search text-primary"></i>
					</button>
					<span id="arca-loading-cliente" style="display:none; position:absolute; left:100%; top:50%; transform:translateY(-50%); margin-left:8px; white-space:nowrap; color:#6c757d; font-size:0.95em; z-index:5;">
						<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Consultando a ARCA...
					</span>
				</span>
			</div>			
			<div class="form-group row">
				@if ($tipoalta != 'P')
					<label for="retieneiva" class="col-lg-4 col-form-label requerido">Percepción iva</label>
				@else
					<label for="retieneiva" class="col-lg-4 col-form-label">Percepción iva</label>
				@endif
				<select name="retieneiva" class="col-lg-3 form-control" @if ($tipoalta != 'P') required @endif>
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
					@foreach ($modofacturacion_enum as $value => $modofacturacion)
						<option value="{{ $value }}"
							@if (old('modofacturacion', $data->modofacturacion ?? '') == $value) selected @endif
							>{{ $modofacturacion }}</option>
					@endforeach
				</select>
				@if (config('app.empresa') == 'EL BIERZO')
					<label for="logistica" class="col-lg-2 col-form-label">Logística</label>
					<input type="number" min="0" max="100" name="porcentajelogistica" id="porcentajelogistica" class="col-lg-2 form-control" value="{{old('porcentajelogistica', $data->porcentajelogistica ?? '0')}}"/>
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
				<div class="col-lg-8 tm-zonavta-campo">
					<input type="hidden" id="zonavta_id_previa" name="zonavta_id_previa" value="{{old('zonavta_id', $data->zonavta_id ?? '')}}" >
					<input type="hidden" id="desc_zonavta" name="desc_zonavta" value="{{old('desc_zonavta', $data->desc_zonavta ?? '')}}" >
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<input type="hidden" class="zonavta_id" id="zonavta_id" name="zonavta_id" value="{{ old('zonavta_id', $data->zonavta_id ?? '') }}">
						<button type="button" title="Consulta zonas de venta (F1)" class="btn-accion-tabla consultazonavta tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" class="form-control codigozonavta flex-shrink-0" id="codigozonavta" name="codigozonavta"
							value="{{ old('codigozonavta', $data->zonavtas->codigo ?? '') }}"
							placeholder="C&oacute;d." autocomplete="off" style="width: 4rem;">
						<input type="text" class="form-control nombrezonavta" id="nombrezonavta" name="nombrezonavta"
							value="{{ old('nombrezonavta', $data->zonavtas->nombre ?? '') }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
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
			<div class="form-group row tm-listaprecio-campo">
				<label for="listaprecio_id" class="col-lg-4 col-form-label">Lista de precio</label>
				<div class="col-lg-8">
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<input type="hidden" class="listaprecio_id" name="listaprecio_id" id="listaprecio_id"
							value="{{ old('listaprecio_id', $data->listaprecio_id ?? '') }}">
						<button type="button" title="Consulta listas de precios (F1)" class="btn-accion-tabla consultalistaprecio tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						@if (can('editar-listaprecio', false) || can('listar-listaprecio', false))
							<a href="{{ ((int) ($data->listaprecio_id ?? 0) > 0) ? route('editar_listaprecio', ['id' => (int) $data->listaprecio_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
								target="_blank" rel="noopener"
								class="btn-accion-tabla btn-link-editar-listaprecio tooltipsC flex-shrink-0 {{ ((int) ($data->listaprecio_id ?? 0) > 0) ? '' : 'd-none' }}"
								title="Consultar lista de precios en ABM">
								<i class="fa fa-edit"></i>
							</a>
						@endif
						<input type="text" class="form-control codigolistaprecio flex-shrink-0" id="codigolistaprecio"
							value="{{ old('codigolistaprecio', optional($data->listaprecios ?? null)->codigo ?? '') }}"
							placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
						<input type="text" class="form-control nombrelistaprecio" id="nombrelistaprecio"
							value="{{ old('nombrelistaprecio', optional($data->listaprecios ?? null)->nombre ?? '') }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
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
				<div class="form-group row tm-distribuidor-campo">
					<label for="distribuidor_id" class="col-lg-4 col-form-label">Distribuidor</label>
					<div class="col-lg-8">
						<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
							<input type="hidden" class="distribuidor_id" name="distribuidor_id" id="distribuidor_id"
								value="{{ old('distribuidor_id', $data->distribuidor_id ?? '') }}">
							<button type="button" title="Consulta distribuidores (F1)" class="btn-accion-tabla consultadistribuidor tooltipsC flex-shrink-0">
								<i class="fa fa-search text-primary"></i>
							</button>
							@if (can('editar-distribuidor', false) || can('listar-distribuidor', false))
								<a href="{{ ((int) ($data->distribuidor_id ?? 0) > 0) ? route('editar_distribuidor', ['id' => (int) $data->distribuidor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
									target="_blank" rel="noopener"
									class="btn-accion-tabla btn-link-editar-distribuidor tooltipsC flex-shrink-0 {{ ((int) ($data->distribuidor_id ?? 0) > 0) ? '' : 'd-none' }}"
									title="Consultar distribuidor en ABM">
									<i class="fa fa-edit"></i>
								</a>
							@endif
							<input type="text" class="form-control codigodistribuidor flex-shrink-0" id="codigodistribuidor"
								value="{{ old('codigodistribuidor', $data->distribuidores->codigo ?? '') }}"
								placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
							<input type="text" class="form-control nombredistribuidor" id="nombredistribuidor"
								value="{{ old('nombredistribuidor', $data->distribuidores->nombre ?? '') }}"
								placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
						</div>
					</div>
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
					<label for="condicioniibb_id" class="col-lg-4 col-form-label requerido">Condici&oacute;n IIBB</label>
				@else
					<label for="condicioniibb_id" class="col-lg-4 col-form-label">Condici&oacute;n IIBB</label>
				@endif
				<select name="condicioniibb_id" class="col-lg-5 form-control" @if ($tipoalta != 'P') required @endif>
					<option value="">-- Elija condici&oacute;n IIBB --</option>
					@foreach($condicioniibb_query as $key => $value)
						@if( (int) $value->id == (int) old('condicioniibb_id', $data->condicioniibb_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="tipoempresa_cliente_id" class="col-lg-4 col-form-label">Tipo de empresa</label>
				<select name="tipoempresa_cliente_id" id="tipoempresa_cliente_id" data-placeholder="Tipo de empresa" class="col-lg-5 form-control" data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($tipoempresa_cliente_query as $key => $value)
						@if( (int) $value->id == (int) old('tipoempresa_cliente_id', $data->tipoempresa_cliente_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row tm-vendedor-campo">
				<label for="vendedor_id" class="col-lg-4 col-form-label">Vendedor</label>
				<div class="col-lg-8">
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id"
							value="{{ old('vendedor_id', $data->vendedor_id ?? '') }}">
						<button type="button" title="Consulta vendedores (F1)" class="btn-accion-tabla consultavendedor tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						@if (can('editar-vendedores', false) || can('listar-vendedores', false))
							<a href="{{ ((int) ($data->vendedor_id ?? 0) > 0) ? route('editar_vendedor', ['id' => (int) $data->vendedor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
								target="_blank" rel="noopener"
								class="btn-accion-tabla btn-link-editar-vendedor tooltipsC flex-shrink-0 {{ ((int) ($data->vendedor_id ?? 0) > 0) ? '' : 'd-none' }}"
								title="Consultar vendedor en ABM">
								<i class="fa fa-edit"></i>
							</a>
						@endif
						<input type="text" class="form-control codigovendedor flex-shrink-0" id="codigovendedor"
							value="{{ old('codigovendedor', $data->vendedores->codigo ?? '') }}"
							placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
						<input type="text" class="form-control nombrevendedor" id="nombrevendedor"
							value="{{ old('nombrevendedor', $data->vendedores->nombre ?? '') }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
			</div>
			<div class="form-group row tm-transporte-campo">
				<label for="transporte" class="col-lg-4 col-form-label">Reparto</label>
				<div class="col-lg-8">
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<input type="hidden" class="transporte_id" name="transporte_id" id="transporte_id"
							value="{{ old('transporte_id', $data->transporte_id ?? '') }}">
						<button type="button" title="Consulta repartos (F1)" class="btn-accion-tabla consultatransporte tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" class="form-control codigotransporte flex-shrink-0" id="codigotransporte"
							value="{{ old('codigotransporte', optional($data->transportes ?? null)->codigo ?? '') }}"
							placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
						<input type="text" class="form-control nombretransporte" id="nombretransporte"
							value="{{ old('nombretransporte', optional($data->transportes ?? null)->nombre ?? '') }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
			</div>
			<div class="form-group row tm-cuentacontable-campo">
				@if ($tipoalta != 'P')
					<label for="cuentacontable_id" class="col-lg-4 col-form-label requerido">Cuenta contable</label>
				@else
					<label for="cuentacontable_id" class="col-lg-4 col-form-label">Cuenta contable</label>
				@endif
				@php
					$cuentaContableId = old('cuentacontable_id', $data->cuentacontable_id ?? '');
					$cuentaContableCodigo = old('codigocuentacontable', optional($data->cuentascontables ?? null)->codigo ?? '');
					$cuentaContableNombre = old('nombrecuentacontable', optional($data->cuentascontables ?? null)->nombre ?? '');
					if ($cuentaContableId === '' && $cuentaContableCodigo === '') {
						$cuentaDefault = \App\Models\Contable\Cuentacontable::where('empresa_id', config('cliente.EMPRESA_DEFAULT_ID'))
							->where('codigo', (string) config('cliente.DEUDORES_POR_VENTAS'))
							->first();
						if ($cuentaDefault) {
							$cuentaContableId = $cuentaDefault->id;
							$cuentaContableCodigo = $cuentaDefault->codigo;
							$cuentaContableNombre = $cuentaDefault->nombre;
						}
					}
				@endphp
				<div class="col-lg-8">
					<input type="hidden" id="empresa_id" value="{{ config('cliente.EMPRESA_DEFAULT_ID') }}">
					<div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
						<input type="hidden" class="cuentacontable_id" name="cuentacontable_id" id="cuentacontable_id"
							value="{{ $cuentaContableId }}" @if ($tipoalta != 'P') required @endif>
						<button type="button" title="Consulta cuentas contables (F1)" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
							<i class="fa fa-search text-primary"></i>
						</button>
						@if (can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false))
							<a href="{{ ((int) $cuentaContableId > 0) ? route('editar_cuentacontable', ['id' => (int) $cuentaContableId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
								target="_blank" rel="noopener"
								class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ ((int) $cuentaContableId > 0) ? '' : 'd-none' }}"
								title="Consultar cuenta contable en ABM">
								<i class="fa fa-edit"></i>
							</a>
						@endif
						<input type="text" class="form-control codigocuentacontable flex-shrink-0" id="codigocuentacontable"
							value="{{ $cuentaContableCodigo }}"
							placeholder="C&oacute;d." autocomplete="off"
							style="flex: 0 0 6.85rem; width: 6.85rem; min-width: 6.85rem; max-width: 6.85rem;">
						<input type="text" class="form-control nombrecuentacontable" id="nombrecuentacontable"
							value="{{ $cuentaContableNombre }}"
							placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
					</div>
				</div>
			</div>
			@if (can('modificar-descuento-cliente', false))
				<div class="form-group row">
					@if (config('app.empresa') == 'EL BIERZO')
						<label for="agregabonificacion" class="col-lg-3 col-form-label">Agrega Bonif.</label>
						<select name="agregabonificacion" class="col-lg-4 form-control">
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
				@if (config('app.empresa') == 'EL BIERZO')
					<input type="hidden" name="agregabonificacion" value="{{ old('agregabonificacion', $data->agregabonificacion ?? 'No Agrega Bonificacion') }}">
				@endif
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
				@else
					<input type="hidden" name="coeficiente_id" value="{{ old('coeficiente_id', $data->coeficiente_id ?? '') }}">
					<input type="hidden" name="coeficienteextra" value="{{ old('coeficienteextra', $data->coeficienteextra ?? '0') }}">
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
			<div class="form-group row">
				<label for="horarioatencion" class="col-lg-4 col-form-label">Horario de Atención</label>
				<div class="col-lg-8">
					<input type="text" name="horarioatencion" id="horarioatencion" class="form-control" value="{{old('horarioatencion', $data->horarioatencion ?? '')}}"/>
				</div>
			</div>
		</div>
	</div>
</div>
@include('includes.ventas.modalconsultatransporte')
@include('includes.ventas.modalconsultazonavta')
@include('includes.ventas.modalconsultavendedor')
@include('includes.ventas.modalconsultadistribuidor')
@include('includes.stock.modalconsultalistaprecio')
@include('includes.contable.modalconsultacuentacontable')
