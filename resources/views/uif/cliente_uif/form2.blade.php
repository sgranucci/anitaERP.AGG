<div class="form2" style="display: none">
	<div class="row">
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="nivelsocioeconomico_uif" class="col-lg-4 col-form-label requerido">Nivel Socioeconómico</label>
				<select name="nivelsocioeconomico_uif_id" id="nivelsocioeconomico_uif_id" data-placeholder="Nivel Socioeconómico" class="form-control col-lg-5 required" data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($nivelsocioeconomico_uif_query as $key => $value)
						@if( (int) $value->id == (int) old('nivelsocioeconomico_uif_id', $data->nivelsocioeconomico_uif_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row" id="div-riesgopep">
				<label for="riesgopep" class="col-lg-4 col-form-label requerido">Riesgo PEP</label>
				<select id="riesgopep" name="riesgopep" class="col-lg-4 form-control" required>
					<option value="">-- Elija Riego --</option>
					@foreach($riesgopep_enum as $riesgopep)
						@if ($riesgopep['nombre'] == old('riesgopep',$data->riesgopep??''))
							<option value="{{ $riesgopep['nombre'] }}" selected>{{ $riesgopep['nombre'] }}</option>    
						@else
							<option value="{{ $riesgopep['nombre'] }}">{{ $riesgopep['nombre'] }}</option>
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="so_uif_id" class="col-lg-4 col-form-label requerido">Sujeto Obligado</label>
				<select name="so_uif_id" id="so_uif_id" data-placeholder="Sujeto Obligado" class="form-control col-lg-5 required" data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($so_uif_query as $key => $value)
						@if( (int) $value->id == (int) old('so_uif_id', $data->so_uif_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>		
			<div class="form-group row" id="div-actividadso">
				<label for="actividadso" class="col-lg-4 col-form-label requerido">Actividad Sujeto Obligado</label>
				<input type="text" class="col-lg-7 actividadso form-control" id="actividadso" name="actividadso" value="{{$data->actividadso??''}}" >
				</select>
			</div>						
		</div>
		<div class="col-sm-6">
			<div class="form-group row">
				<label for="pep_uif_id" class="col-lg-4 col-form-label requerido">Expuesto Políticamente PEP</label>
				<select name="pep_uif_id" id="pep_uif_id" data-placeholder="Expuesto Políticamente" class="form-control col-lg-5 required" data-fouc>
					<option value="">-- Seleccionar --</option>
					@foreach($pep_uif_query as $key => $value)
						@if( (int) $value->id == (int) old('pep_uif_id', $data->pep_uif_id ?? ''))
							<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
						@else
							<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="resideparaisofiscal" class="col-lg-4 col-form-label requerido">Reside Paraíso Fiscal</label>
				<select id="resideparaisofiscal" name="resideparaisofiscal" class="col-lg-4 form-control" required>
					<option value="">-- Elija --</option>
					@foreach($resideparaisofiscal_enum as $resideparaisofiscal)
						@if ($resideparaisofiscal['valor'] == old('resideparaisofiscal',$data->resideparaisofiscal??''))
							<option value="{{ $resideparaisofiscal['valor'] }}" selected>{{ $resideparaisofiscal['nombre'] }}</option>    
						@else
							<option value="{{ $resideparaisofiscal['valor'] }}">{{ $resideparaisofiscal['nombre'] }}</option>
						@endif
					@endforeach
				</select>
			</div>
			<div class="form-group row">
				<label for="resideexterior" class="col-lg-4 col-form-label requerido">Reside en el Exterior</label>
				<select id="resideexterior" name="resideexterior" class="col-lg-4 form-control" required>
					<option value="">-- Elija --</option>
					@foreach($resideexterior_enum as $resideexterior)
						@if ($resideexterior['valor'] == old('resideexterior',$data->resideexterior??''))
							<option value="{{ $resideexterior['valor'] }}" selected>{{ $resideexterior['nombre'] }}</option>    
						@else
							<option value="{{ $resideexterior['valor'] }}">{{ $resideexterior['nombre'] }}</option>
						@endif
					@endforeach
				</select>
			</div>		
			<div class="form-group row" id="div-cumplenormativaso">
				<label for="cumplenormativaso" class="col-lg-4 col-form-label requerido">Cumple Normativa SO</label>
				<select id="cumplenormativaso" name="cumplenormativaso" class="col-lg-4 form-control" required>
					<option value="">-- Elija Si Cumple Normativa SO --</option>
					@foreach($cumplenormativaso_enum as $cumplenormativaso)
						@if ($cumplenormativaso['nombre'] == old('cumplenormativaso',$data->cumplenormativaso??''))
							<option value="{{ $cumplenormativaso['nombre'] }}" selected>{{ $cumplenormativaso['nombre'] }}</option>    
						@else
							<option value="{{ $cumplenormativaso['nombre'] }}">{{ $cumplenormativaso['nombre'] }}</option>
						@endif
					@endforeach
				</select>
			</div>				
		</div>
	</div>

	<div class="row">
		<div class="col-sm-6">
			<div class="form-group row" id="div-fechainformepep">
				<label class="col-lg-4 col-form-label requerido">Fecha Informe PEP</label>
				<input type="date" name="fechainformepep" id="fechainformepep" class="col-lg-3 form-control" value="{{old('fechainformepep', $data['fechainformepep'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Ultima Firma PEP">
			</div>
			<div class="form-group row" id='div-fechafirmapep'>
				<label class="col-lg-4 col-form-label requerido">Fecha Ultima Firma PEP</label>
				<input type="date" name="fechafirmapep" id="fechafirmapep" class="col-lg-3 form-control" value="{{old('fechafirmapep', $data['fechafirmapep'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Ultima Firma PEP">
			</div>
			<div class="form-group row" id='div-fechaconfirmapep'>
				<label class="col-lg-4 col-form-label requerido">Fecha Validación Ultima Firma PEP</label>
				<input type="date" name="fechaconfirmapep" id="fechaconfirmapep" class="col-lg-3 form-control" value="{{old('fechaconfirmapep', $data['fechaconfirmapep'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Confirmación Ultima Firma PEP">
			</div>
		</div>
		<div class="col-sm-6">
			<div class="form-group row" id="div-fechainformenosis">
				<label class="col-lg-4 col-form-label requerido">Fecha Informe NOSIS</label>
				<input type="date" name="fechainformenosis" id="fechainformenosis" class="col-lg-3 form-control" value="{{old('fechainformenosis', $data['fechainformenosis'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Confirmación Ultima Firma PEP">
			</div>
			<div class="form-group row"  id='div-fechavencimientodni'>
				<label class="col-lg-4 col-form-label requerido">Fecha Vto. DNI</label>
				<input type="date" name="fechavencimientodni" id="fechavencimientodni" class="col-lg-3 form-control" value="{{old('fechavencimientodni', $data['fechavencimientodni'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Confirmación Ultima Firma PEP">
			</div>
			<div class="form-group row" id='div-fechavencimientoactividad'>
				<label class="col-lg-4 col-form-label requerido">Fecha Vto. Actividad Econ.</label>
				<input type="date" name="fechavencimientoactividad" id="fechavencimientoactividad" class="col-lg-3 form-control" value="{{old('fechavencimientoactividad', $data['fechavencimientoactividad'] ?? '')}}" class="col-lg-6 form-control" placeholder="Fecha Confirmación Ultima Firma PEP">
			</div>		
			<div class="form-group row" id="div-firmodeclaracionjurada">
				<label for="firmodeclaracionjurada" class="col-lg-4 col-form-label requerido">Firmo Decl.Jur. Actividad Econ.</label>
				<select id="firmodeclaracionjurada" name="firmodeclaracionjurada" class="col-lg-4 form-control" required>
					<option value="">-- Elija Si Firmó Decl.Jur.Act. --</option>
					@foreach($firmodeclaracionjurada_enum as $firmodeclaracionjurada)
						@if ($firmodeclaracionjurada['valor'] == old('firmodeclaracionjurada',$data->firmodeclaracionjurada??''))
							<option value="{{ $firmodeclaracionjurada['valor'] }}" selected>{{ $firmodeclaracionjurada['nombre'] }}</option>    
						@else
							<option value="{{ $firmodeclaracionjurada['valor'] }}">{{ $firmodeclaracionjurada['nombre'] }}</option>
						@endif
					@endforeach
				</select>
			</div>							
		</div>
	</div>
</div>



