@php
    $tieneClienteForm4 = isset($data) && ($data->id ?? null);
    $riesgoClienteUifRestringido = esSoloVisualizacionClienteUif() || ! esSupervisorUif();
    $mesesPeriodoUif = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];
    $anioMinRiesgoUif = 2010;
    $anioMaxRiesgoUif = (int) date('Y') + 5;
@endphp

<div class="card form4" style="display: none">
    <div class="card-body">
    	<table class="table" id="riesgo-table">
    		<thead>
    			<tr>
					<th style="width: 25%;">Período</th>
    				<th style="width: 25%;">Inusualidad</th>
    				<th style="width: 25%;">Riesgo</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-riesgo">
		 		@if ($data->cliente_riesgos_uif ?? '') 
					@foreach (old('riesgo', $data->cliente_riesgos_uif->count() ? $data->cliente_riesgos_uif : []) as $riesgo)
            			<tr class="item-riesgo">
                			<td>
                				<input type="hidden" name="iiriesgos[]" class="form-control iiriesgo" readonly value="{{ $loop->index+1 }}" />
								<input type="hidden" name="riesgo_ids[]" class="form-control riesgo_id" readonly value="{{ $riesgo->id ?? ''}}" />
								<input type="hidden" name="creousuario_riesgo_ids[]" class="form-control creousuario_riesgo_id" value="{{ $riesgo->creousuario_id ?? ''}}" />
								<input type="hidden" name="periodos[]" class="periodo" value="{{ old('periodos.' . $loop->index, $riesgo->periodo ?? '') }}">
								<div class="form-row align-items-center mx-0 capex-periodo-picker">
									<div class="col-6 col-md-5 pr-md-1 px-0 mb-1 mb-md-0">
										<label class="d-md-none small text-muted mb-0">Año</label>
										<select class="form-control periodo-anio" title="Año del período" aria-label="Año del período">
											<option value="">Año</option>
											@for ($y = $anioMinRiesgoUif; $y <= $anioMaxRiesgoUif; $y++)
												<option value="{{ $y }}">{{ $y }}</option>
											@endfor
										</select>
									</div>
									<div class="col-6 col-md-7 pl-md-1 px-0">
										<label class="d-md-none small text-muted mb-0">Mes</label>
										<select class="form-control periodo-mes" title="Mes del período" aria-label="Mes del período">
											<option value="">Mes</option>
											@foreach ($mesesPeriodoUif as $num => $nombre)
												<option value="{{ $num }}">{{ $nombre }}</option>
											@endforeach
										</select>
									</div>
								</div>
                			</td>
							<td>
        						<select name="inusualidad_uif_ids[]" data-placeholder="Inusualidad" class="form-control inusualidad_uif" data-fouc>
        							<option value="">-- Seleccionar --</option>
        							@foreach($inusualidad_uif_query as $key => $value)
        								@if( (int) $value->id == (int) old('inusualidad_uif_ids', $riesgo->inusualidad_uif_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
        					</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="riesgos[]" value="{{old('riesgos.' . $loop->index, $riesgo->riesgo ?? '')}}" class="form-control riesgo" placeholder="Riesgo asociado">
        						</div>
                			</td>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_riesgo tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('uif.cliente_uif.template4')
        @unless($riesgoClienteUifRestringido)
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_riesgo" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
        @endunless
    </div>
</div>
