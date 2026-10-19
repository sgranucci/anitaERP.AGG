@php
    $modoEtiqueta = old('modo', 'manual');
    $tipoSeleccionado = old('tipoetiqueta', 'CAJA FOTO');
@endphp
<div class="row">
	<div class="col-sm-12">
		<div class="form-group row">
			<label for="modo" class="col-lg-3 col-form-label text-right requerido">Cómo emitir</label>
			<div class="col-lg-4">
				<select name="modo" id="modo" class="form-control" required>
					<option value="manual" {{ $modoEtiqueta === 'manual' ? 'selected' : '' }}>Sin orden de trabajo</option>
					<option value="ot" {{ $modoEtiqueta === 'ot' ? 'selected' : '' }}>Desde una orden de trabajo</option>
				</select>
				<small class="form-text text-muted">Sin orden: se cargan artículo, combinación y cantidad. No hace falta crear la OT ni una tarea.</small>
			</div>
		</div>

		<div class="form-group row">
    		<label for="tipoetiqueta" class="col-lg-3 col-form-label text-right requerido">Tipo de etiqueta</label>
			<div class="col-lg-4">
				<select name="tipoetiqueta" id="tipoetiqueta" class="form-control" required>
					<option value="">-- Elija tipo de etiqueta --</option>
					@foreach($tipoetiqueta_enum as $value => $tipoetiqueta)
						<option value="{{ $value }}" {{ $value == $tipoSeleccionado ? 'selected' : '' }}>{{ $tipoetiqueta }}</option>
					@endforeach
				</select>
			</div>
		</div>

		<div id="bloque-etiqueta-ot" class="{{ $modoEtiqueta === 'ot' ? '' : 'd-none' }}">
			<div class="form-group row">
				<label for="ordenestrabajo" class="col-lg-3 col-form-label text-right">Órdenes de trabajo</label>
				<div class="col-lg-8">
					<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{ old('ordenestrabajo') }}" placeholder="Números separados por coma">
				</div>
			</div>
			<div class="form-group row">
				<label for="origen" class="col-lg-3 col-form-label text-right">Origen</label>
				<div class="col-lg-4">
					<select name="origen" id="origen" class="form-control">
						<option value="">-- Elija origen --</option>
						@foreach($origen_enum as $value => $origen)
							@php
								$origenSeleccionado = old('origen', 'ERP');
							@endphp
							<option value="{{ $value }}" {{ $value == $origenSeleccionado ? 'selected' : '' }}>{{ $origen }}</option>
						@endforeach
					</select>
				</div>
			</div>
		</div>

		<div id="bloque-etiqueta-manual" class="{{ $modoEtiqueta === 'manual' ? '' : 'd-none' }}">
			<div class="form-group row tm-articulo-campo" id="eti-articulo-campo">
				<label for="eti-codigoarticulo" class="col-lg-3 col-form-label text-right requerido">Artículo</label>
				<div class="col-lg-8">
					<input type="hidden" name="articulo_id" id="eti-articulo-id" class="articulo_id" value="{{ old('articulo_id') }}">
					<div class="d-flex align-items-center flex-nowrap">
						<button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0 mr-1">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" id="eti-codigoarticulo" class="codigoarticulo form-control flex-shrink-0 mr-1" style="width: 140px; max-width: 30%;" value="{{ old('codigoarticulo') }}" autocomplete="off" title="SKU. F1 abre el modal. Enter resuelve.">
						<input type="text" id="eti-descripcionarticulo" class="descripcionarticulo form-control" value="{{ old('descripcionarticulo') }}" readonly tabindex="-1" placeholder="Descripción">
					</div>
				</div>
			</div>

			<div class="form-group row tm-combinacion-campo" id="eti-combinacion-campo">
				<label for="eti-codigocombinacion" class="col-lg-3 col-form-label text-right requerido">Combinación</label>
				<div class="col-lg-8">
					<input type="hidden" name="combinacion_id" id="eti-combinacion-id" class="combinacion_id" value="{{ old('combinacion_id') }}">
					<div class="d-flex align-items-center flex-nowrap">
						<button type="button" title="Consulta combinaciones (F1)" class="btn-accion-tabla consultacombinacion tooltipsC flex-shrink-0 mr-1">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" id="eti-codigocombinacion" class="codigocombinacion form-control flex-shrink-0 mr-1" style="width: 140px; max-width: 30%;" value="{{ old('codigocombinacion') }}" autocomplete="off" title="Código de combinación. F1 abre el modal.">
						<input type="text" id="eti-descripcioncombinacion" class="descripcioncombinacion form-control" value="{{ old('descripcioncombinacion') }}" readonly tabindex="-1" placeholder="Descripción">
					</div>
				</div>
			</div>

			<div class="form-group row tm-talle-campo" id="eti-talle-campo">
				<label for="eti-codigotalle" class="col-lg-3 col-form-label text-right requerido">Talle</label>
				<div class="col-lg-8">
					<input type="hidden" name="talle_id" id="eti-talle-id" class="talle_id" value="{{ old('talle_id') }}">
					<div class="d-flex align-items-center flex-nowrap">
						<button type="button" title="Consulta talles (F1)" class="btn-accion-tabla consultatalle tooltipsC flex-shrink-0 mr-1">
							<i class="fa fa-search text-primary"></i>
						</button>
						<input type="text" id="eti-codigotalle" class="codigotalle form-control flex-shrink-0 mr-1" style="width: 140px; max-width: 30%;" value="{{ old('codigotalle') }}" autocomplete="off" title="Código de talle. F1 abre el modal. Enter resuelve.">
						<input type="text" id="eti-descripciontalle" class="descripciontalle form-control" value="{{ old('descripciontalle') }}" readonly tabindex="-1" placeholder="Descripción">
					</div>
					<small class="form-text text-muted">El talle va en la etiqueta de caja. En la de CUIT no se imprime.</small>
				</div>
			</div>

			<div class="form-group row">
				<label for="cantidad" class="col-lg-3 col-form-label text-right requerido">Cantidad</label>
				<div class="col-lg-2">
					<input type="number" name="cantidad" id="cantidad" class="form-control" min="1" max="500" value="{{ old('cantidad', 1) }}">
				</div>
			</div>

			<div class="form-group row" id="eti-nro-referencia">
				<label for="nro_referencia" class="col-lg-3 col-form-label text-right">Nro. de OT</label>
				<div class="col-lg-3">
					<input type="text" name="nro_referencia" id="nro_referencia" class="form-control" value="{{ old('nro_referencia') }}" maxlength="20" autocomplete="off">
					<small class="form-text text-muted">Opcional. Si se carga, sale en la etiqueta de cabecera del CUIT. No crea la orden.</small>
				</div>
			</div>
		</div>
	</div>
</div>
