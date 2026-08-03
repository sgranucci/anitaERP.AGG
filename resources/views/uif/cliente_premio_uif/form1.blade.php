<div class="form1">
	@php
		$premioData = $data ?? null;
		$premioOldId = static function ($campo, $default = '') use ($premioData) {
			return old($campo, $premioData !== null ? ($premioData->{$campo} ?? $default) : $default);
		};
	@endphp
	<div class="row">
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
				<div class="col-lg-4">
					<input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}">
				</div>
			</div>
			@php
				$clientePremioId = (int) ($cliente_uif_id ?? ($data->cliente_uif_id ?? 0));
				$origenClientePremio = \App\Support\Uif\ClienteUifOrigenPcSupport::origenDeClienteId($clientePremioId);
				$salaClienteId = $origenClientePremio
					? \App\Support\Uif\ClienteUifArchivoStorage::salaId($origenClientePremio)
					: (int) (\App\Support\Uif\ClienteUifOrigenPcSupport::intentarResolver()['sala_id'] ?? $premioOldId('sala_id'));
			@endphp
			<div class="form-group row">
				<label for="sala_id" class="col-lg-3 col-form-label requerido">Sala</label>
				<div class="col-lg-9">
					<input type="hidden" name="sala_id" id="sala_id" value="{{ $salaClienteId }}">
					<select class="form-control" disabled>
						@foreach($sala_query as $key => $value)
							<option value="{{ $value->id }}" @selected((int) $value->id === (int) $salaClienteId)>{{ $value->nombre }}</option>
						@endforeach
					</select>
					@if ($origenClientePremio)
						<small class="form-text text-muted">Sala del origen del cliente: {{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen($origenClientePremio) }}</small>
					@endif
				</div>
			</div>
			<div class="form-group row">
				<label for="monto" class="col-lg-3 col-form-label requerido">Monto</label>
				<div class="col-lg-9">
					<div class="input-group">
						<select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="form-control required" data-fouc required>
							@foreach($moneda_query as $key => $value)
								@if ((int) $value->id == (int) $premioOldId('moneda_id'))
									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
								@else
									<option value="{{ $value->id }}">{{ $value->nombre }}</option>
								@endif
							@endforeach
						</select>
						<span class="input-group-text">#</span>
						<input type="number" name="monto" id="monto" class="form-control" placeholder="Monto sin iva" aria-label="Monto sin iva" value="{{ $premioOldId('monto') }}" required step="0.01" min="0.01">
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label for="juego_uif_id" class="col-lg-3 col-form-label requerido">Juego</label>
				<div class="col-lg-9">
					<select name="juego_uif_id" id="juego_uif_id" data-placeholder="Juego" class="form-control required" data-fouc required>
						@foreach($juego_uif_query as $key => $value)
							@if ((int) $value->id == (int) $premioOldId('juego_uif_id'))
								<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
							@else
								<option value="{{ $value->id }}">{{ $value->nombre }}</option>
							@endif
						@endforeach
					</select>
				</div>
			</div>
			<div class="form-group row">
				<label for="formapago_id" class="col-lg-3 col-form-label requerido">Forma de pago</label>
				<div class="col-lg-9">
					<select name="formapago_id" id="formapago_id" data-placeholder="Forma de Pago" class="form-control required" data-fouc>
						@foreach($formapago_query as $key => $value)
							@if ((int) $value->id == (int) $premioOldId('formapago_id'))
								<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
							@else
								<option value="{{ $value->id }}">{{ $value->nombre }}</option>
							@endif
						@endforeach
					</select>
				</div>
			</div>
			<div class="form-group row">
				<label for="foto" class="col-lg-3 col-form-label">Foto</label>
				<div class="col-lg-9">
					@if (! empty($premioData->foto ?? null) && ! empty($premioData->id ?? null))
						<div class="premio-foto-actual mb-2">
							<a href="{{ route('muestra_foto_cliente_premio_uif', ['id' => $premioData->id]) }}" target="_blank" rel="noopener" class="premio-foto-preview-link tooltipsC" title="Ver en tamaño completo">
								<img src="{{ \App\Support\Uif\ClienteUifArchivoStorage::urlFotoPremio($premioData->foto) }}" alt="Foto actual del jugador" class="premio-foto-preview-img">
							</a>
							<div>
								<small class="text-muted d-block">Foto actual del jugador.</small>
								<small class="text-muted d-block">Seleccione un archivo abajo para reemplazarla.</small>
							</div>
						</div>
					@endif
					@php
						$tieneFotoActual = ! empty($premioData->foto ?? null) && ! empty($premioData->id ?? null);
						$fotoPreviewFileinput = $tieneFotoActual
							? ''
							: (! empty($premioData->foto ?? null)
								? (\App\Support\Uif\ClienteUifArchivoStorage::urlFotoPremio($premioData->foto) ?? asset('assets/'.$theme.'/dist/img/user2-160x160.jpg'))
								: asset('assets/'.$theme.'/dist/img/user2-160x160.jpg'));
					@endphp
					<input type="file" name="foto_up" id="foto" data-initial-preview="{{ $fotoPreviewFileinput }}" accept="image/*"/>
					<small class="form-text text-muted">JPG o PNG, máximo 2 MB.</small>
				</div>
			</div>
		</div>
		<div class="col-sm-6">
			@php
				$fmtDatetimeLocal = static function ($valor) {
					if ($valor === null || $valor === '') {
						return '';
					}
					try {
						return \Carbon\Carbon::parse(str_replace('T', ' ', (string) $valor))->format('Y-m-d\TH:i');
					} catch (\Throwable $e) {
						return '';
					}
				};
				$fechaTitoForm = old('fechatito');
				if ($fechaTitoForm === null) {
					$fechaTitoForm = $fmtDatetimeLocal($premioData?->fechatito);
				} else {
					$fechaTitoForm = $fmtDatetimeLocal($fechaTitoForm);
				}
				$fechaEntregaForm = old('fechaentrega');
				if ($fechaEntregaForm === null) {
					$fechaEntregaRaw = $premioData?->fechaentrega;
					$fechaEntregaForm = $fechaEntregaRaw
						? $fmtDatetimeLocal($fechaEntregaRaw)
						: now()->format('Y-m-d\TH:i');
				} else {
					$fechaEntregaForm = $fmtDatetimeLocal($fechaEntregaForm);
				}
			@endphp
			<div class="form-group row">
				<label for="fechatito" class="col-lg-3 col-form-label">Fecha de TITO</label>
				<div class="col-lg-5">
					<input type="datetime-local" name="fechatito" id="fechatito" class="form-control" value="{{ $fechaTitoForm }}">
				</div>
			</div>
			<div class="form-group row">
				<label for="fechaentrega" class="col-lg-3 col-form-label requerido">Fecha de entrega</label>
				<div class="col-lg-5">
					<input type="datetime-local" name="fechaentrega" id="fechaentrega" class="form-control" value="{{ $fechaEntregaForm }}" required>
				</div>
			</div>
			<div class="form-group row">
				<label for="numerotito" class="col-lg-3 col-form-label">Número de TITO</label>
				<div class="col-lg-4">
					<input type="text" name="numerotito" id="numerotito" class="form-control" value="{{ $premioOldId('numerotito') }}">
				</div>
			</div>
			<div class="form-group row">
				<label for="posicion" class="col-lg-3 col-form-label">Número de Posición</label>
				<div class="col-lg-4">
					<input type="text" name="posicion" id="posicion" class="form-control" value="{{ $premioOldId('posicion') }}">
				</div>
			</div>
			<div class="form-group row">
				<label for="piderecibopago" class="col-lg-3 col-form-label">Pide recibo de pago</label>
				<div class="col-lg-5">
					<select name="piderecibopago" id="piderecibopago" data-placeholder="piderecibopago" class="form-control required" data-fouc required>
						@foreach($piderecibopago_enum as $value)
							@if ($value['nombre'] == $premioOldId('piderecibopago'))
								<option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>
							@else
								<option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>
							@endif
						@endforeach
					</select>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" id="estado" name="estado" value="{{ $premioOldId('estado') }}">
	<input type="hidden" id="cliente_uif_id" name="cliente_uif_id" value="{{ old('cliente_uif_id', $premioData?->cliente_uif_id ?? ($cliente_uif_id ?? '')) }}">
	<input type="hidden" id="essupervisor" name="essupervisor" value="{{ old('essupervisor', $essupervisor ?? '') }}">
	<input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $premioData?->creousuario_id ?? Auth::user()->id }}" />
	<input type="hidden" id="referer" name="referer" value="{{ $referer ?? '' }}" />
</div>
