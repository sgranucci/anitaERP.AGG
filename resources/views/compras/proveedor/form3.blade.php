@php
    $filasFormapago = collect();
    if (old('nombres') !== null) {
        foreach ((array) old('nombres', []) as $i => $nombre) {
            $filasFormapago->push((object) [
                'nombre' => $nombre,
                'formapago_id' => old('formapago_ids.'.$i),
                'cbu' => old('cbus.'.$i),
                'alias_cbu' => old('alias_cbus.'.$i),
                'tipocuentacaja_id' => old('tipocuentacaja_ids.'.$i),
                'moneda_id' => old('moneda_ids.'.$i),
                'numerocuenta' => old('numerocuentas.'.$i),
                'nroinscripcion' => old('nroinscripciones.'.$i),
                'banco_id' => old('banco_ids.'.$i),
                'mediopago_id' => old('mediopago_ids.'.$i),
                'email' => old('emails.'.$i),
            ]);
        }
    } elseif (isset($data) && $data->proveedor_formapagos && $data->proveedor_formapagos->count()) {
        $filasFormapago = $data->proveedor_formapagos;
    }
@endphp
@php
    $aceptaChequeFisico = (bool) old('acepta_cheque_fisico', isset($data) ? ($data->acepta_cheque_fisico ?? true) : true);
    $aceptaEcheq = (bool) old('acepta_echeq', isset($data) ? ($data->acepta_echeq ?? true) : true);
@endphp
<div id="tab3" class="card form3 tab-content" style="display: none">
    <div class="card-body">
		<h3>Formas de pago</h3>
		<p class="text-muted small mb-2">
			En cada rengl&oacute;n con datos son obligatorios: Nombre, Forma de pago y Moneda.
			TC, CBU, Alias CBU, C.U.I.T., N&uacute;mero de cuenta, Banco y Medio de pago son opcionales.
		</p>
		<div class="border rounded px-3 py-2 mb-3" style="background:#f8f9fa; max-width:28rem;">
			<div class="small text-muted mb-1">Cheques de terceros que acepta</div>
			<div class="custom-control custom-checkbox custom-control-inline">
				<input type="hidden" name="acepta_cheque_fisico" value="0">
				<input type="checkbox" class="custom-control-input" id="acepta_cheque_fisico" name="acepta_cheque_fisico" value="1"
					@checked($aceptaChequeFisico)>
				<label class="custom-control-label" for="acepta_cheque_fisico">F&iacute;sico</label>
			</div>
			<div class="custom-control custom-checkbox custom-control-inline">
				<input type="hidden" name="acepta_echeq" value="0">
				<input type="checkbox" class="custom-control-input" id="acepta_echeq" name="acepta_echeq" value="1"
					@checked($aceptaEcheq)>
				<label class="custom-control-label" for="acepta_echeq">e-cheq</label>
			</div>
			<div class="small text-muted mt-1 mb-0">El programa de pagos solo asigna CHT del tipo marcado.</div>
		</div>
    	<table class="table" id="formapago-table">
    		<thead>
    			<tr>
    				<th style="width: 5%;">Cod.</th>
    				<th style="width: 10%;">Nombre <span class="text-danger">*</span></th>
    				<th style="width: 10%;">Forma de pago <span class="text-danger">*</span></th>
    				<th style="width: 10%;">CBU</th>
    				<th style="width: 8%;">Alias CBU</th>
    				<th style="width: 5%;">TC</th>
    				<th>Moneda <span class="text-danger">*</span></th>
    				<th style="width: 10%;">N&uacute;mero de cuenta</th>
					<th style="width: 10%;">C.U.I.T.</th>
					<th style="width: 10%;">Banco</th>
					<th>Medio de pago</th>
					<th>Email confirmaci&oacute;n pago</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-formapago-table">
				@foreach ($filasFormapago as $formapago)
            			<tr class="item-formapago">
                			<td>
                				<input type="text" name="formapagos[]" class="form-control iiformapago" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
                				<input type="text" name="nombres[]" class="form-control fp-nombre fp-requerido"
                					value="{{ $formapago->nombre ?? '' }}"
                					data-fp-label="Nombre" />
                			</td>
                			<td>
								<select name="formapago_ids[]" data-placeholder="Forma de pago" class="form-control formapago fp-formapago fp-requerido" data-fouc data-fp-label="Forma de pago">
        							<option value=""></option>
        							@foreach($formapago_query as $key => $value)
        								<option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}" @if((int) $value->id === (int) ($formapago->formapago_id ?? 0)) selected @endif>{{ $value->nombre }}</option>
        							@endforeach
        						</select>
                			</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="cbus[]" value="{{ $formapago->cbu ?? '' }}" class="form-control cbus fp-cbu" placeholder="CBU">
        						</div>
                			</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="alias_cbus[]" value="{{ $formapago->alias_cbu ?? '' }}" class="form-control alias_cbus fp-alias-cbu" placeholder="Alias" maxlength="80">
        						</div>
                			</td>
							<td>
        						<select name="tipocuentacaja_ids[]" data-placeholder="Tipo de cuenta de caja" class="form-control tipocuentacaja fp-tipocuentacaja" data-fouc data-fp-label="TC (tipo de cuenta)">
        							<option value=""></option>
        							@foreach($tipocuentacaja_query as $key => $value)
        								<option value="{{ $value->id }}" @if((int) $value->id === (int) ($formapago->tipocuentacaja_id ?? 0)) selected @endif>{{ $value->abreviatura }}</option>
        							@endforeach
        						</select>
        					</td>
							<td>
								<select name="moneda_ids[]" data-placeholder="Moneda" class="form-control moneda fp-moneda fp-requerido" data-fouc data-fp-label="Moneda">
        							<option value=""></option>
        							@foreach($moneda_query as $key => $value)
        								<option value="{{ $value->id }}" @if((int) $value->id === (int) ($formapago->moneda_id ?? 0)) selected @endif>{{ $value->abreviatura }}</option>
        							@endforeach
        						</select>
							</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="numerocuentas[]" value="{{ $formapago->numerocuenta ?? '' }}" class="form-control numerocuentas fp-numerocuenta" placeholder="Nro.cuenta">
        						</div>
                			</td>
							<td>
        						<div class="form-group">
        							<input type="text" name="nroinscripciones[]" value="{{ $formapago->nroinscripcion ?? '' }}" class="form-control nroinscripciones fp-nroinscripcion" placeholder="XX-XXXXXXXX-X" maxlength="13" oninput="formatarCUIT(this)">
        						</div>
                			</td>
							<td>
								<select name="banco_ids[]" data-placeholder="Banco" class="form-control banco fp-banco" data-fouc>
        							<option value=""></option>
        							@foreach($banco_query as $key => $value)
        								<option value="{{ $value->id }}" @if((int) $value->id === (int) ($formapago->banco_id ?? 0)) selected @endif>{{ $value->nombre }}</option>
        							@endforeach
        						</select>
							</td>
							<td>
								<select name="mediopago_ids[]" data-placeholder="Medio de pago" class="form-control mediopago fp-mediopago" data-fouc>
        							<option value=""></option>
        							@foreach($mediopago_query as $key => $value)
        								<option value="{{ $value->id }}" @if((int) $value->id === (int) ($formapago->mediopago_id ?? 0)) selected @endif>{{ $value->nombre }}</option>
        							@endforeach
        						</select>
							</td>
							<td>
        						<div class="form-group">
        							<input type="text" name="emails[]" value="{{ $formapago->email ?? '' }}" class="form-control emails fp-email" placeholder="{{ \App\Support\Mail\EmailsMultiplesSupport::PLACEHOLDER }}" title="{{ \App\Support\Mail\EmailsMultiplesSupport::TITLE }}" autocomplete="off">
        						</div>
                			</td>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_formapago tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           		@endforeach
       		</tbody>
       	</table>
		@include('compras.proveedor.template3')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_formapago" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
