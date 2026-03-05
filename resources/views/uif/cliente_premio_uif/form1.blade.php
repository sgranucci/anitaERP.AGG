<div class="form1">
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group row">
					<label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
					<div class="col-lg-3">
						<input type="date" name="fecha" id="fecha" class="form-control" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
					</div>
            	</div>
				<div class="form-group row">
					<label for="sala" class="col-lg-3 col-form-label requerido">Sala</label>
                    <select name="sala_id" id="sala_id" data-placeholder="Sala" class="col-lg-2 form-control required" data-fouc>
                        @foreach($sala_query as $key => $value)
                            @if( (int) $value->id == (int) old('sala_id', $data->sala_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
				</div>				
				<div class="form-group row">
					<div class="input-group mb-3">
						<label for="moneda" class="col-lg-3 col-form-label requerido">Monto</label>
						<select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-2 form-control required" data-fouc>
							@foreach($moneda_query as $key => $value)
								@if( (int) $value->id == (int) old('moneda_id', $data->moneda_id ?? ''))
									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
								@else
									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
								@endif
							@endforeach
						</select>
						<span class="input-group-text">#</span>
						<input type="number" name="monto" id="monto" class="col-lg-3 form-control" placeholder="Monto sin iva" aria-label="Monto sin iva" value="{{$data->monto??''}}" required>
					</div>
				</div>
				<div class="form-group row">
					<label for="juego_uif" class="col-lg-3 col-form-label requerido">Juego</label>
                    <select name="juego_uif_id" id="juego_uif_id" data-placeholder="Juego" class="col-lg-2 form-control required" data-fouc>
                        @foreach($juego_uif_query as $key => $value)
                            @if( (int) $value->id == (int) old('juego_uif_id', $data->juego_uif_id ?? ''))
                                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                            @else
                                <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                            @endif
                        @endforeach
                    </select>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group row">
					<label for="fechatito" class="col-lg-3 col-form-label">Fecha de TITO</label>
					<div class="col-lg-5">
						<input type="datetime-local" name="fechatito" id="fechatito" class="form-control" value="{{old('fechatito', $data->fechatito ?? '')}}">
					</div>
            	</div>				
				<div class="form-group row">
					<label for="fechaentrega" class="col-lg-3 col-form-label">Fecha de entrega</label>
					<div class="col-lg-5">
						<input type="datetime-local" name="fechaentrega" id="fechaentrega" class="form-control" value="{{old('fechaentrega', $data->fechaentrega ?? '')}}">
					</div>
            	</div>
				<div class="form-group row">
					<label for="numerotito" class="col-lg-3 col-form-label">Número de TITO</label>
					<input type="text" name="numerotito" id="numerotito" class="col-lg-3 form-control" value="{{old('numerotito', $data->numerotito ?? '')}}">
				</div> 
				<div class="form-group row">
					<label for="posicion" class="col-lg-3 col-form-label">Número de Posición</label>
					<input type="text" name="posicion" id="posicion" class="col-lg-3 form-control" value="{{old('posicion', $data->posicion ?? '')}}">
				</div> 
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group row">
				<label for="formapago_id" class="col-lg-3 col-form-label requerido">Forma de pago</label>
				<select name="formapago_id" id="formapago_id" data-placeholder="Forma de Pago" class="col-lg-2 form-control required" data-fouc>
					@foreach($formapago_query as $key => $value)
						@if( (int) $value->id == (int) old('formapago_id', $data->formapago_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
                <label for="piderecibopago" class="col-lg-3 col-form-label">Pide recibo de pago</label>
                <select name="piderecibopago" id="piderecibopago" data-placeholder="piderecibopago" class="col-lg-3 form-control required" data-fouc required>
                    @foreach($piderecibopago_enum as $value)
                        @if( $value['nombre'] == old('piderecibopago', $data->piderecibopago ?? ''))
                            <option value="{{ $value['nombre'] }}" selected="select">{{ $value['nombre'] }}</option>    
                        @else
                            <option value="{{ $value['nombre'] }}">{{ $value['nombre'] }}</option>    
                        @endif
                    @endforeach
                </select>
            </div>    
			<div class="form-group row">
				<label for="foto" class="col-lg-3 col-form-label">Foto</label>
				<div class="col-lg-4">
					<input type="file" name="foto_up" id="foto" data-initial-preview="{{isset($data->foto) ? asset("storage/imagenes/fotos_uif/$data->foto") : asset("assets/$theme/dist/img/user2-160x160.jpg")}}" accept="image/*"/>
				</div>
			</div>
		</div>
        <input type="hidden" id="estado" name="estado" value="{{old('estado', $data->estado ?? '')}}" >
		<input type="hidden" id="cliente_uif_id" name="cliente_uif_id" value="{{old('cliente_uif_id', $data->cliente_uif_id ?? $cliente_uif_id)}}" >
		<input type="hidden" id="essupervisor" name="essupervisor" value="{{old('essupervisor', $essupervisor ?? '')}}" >
		<input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $data->creousuario_id ?? Auth::user()->id }}" />
		<input type="hidden" id="referer" name="referer" value="{{ $referer ?? '' }}" />
</div>



