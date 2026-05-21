<input type="hidden" class="deposito_id" id="deposito_id"
    value="{{ $data->id ?? '' }}">

<div class="form-group row depmae-campo-consulta">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-8">
        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
            <button type="button" title="Consulta depósitos" class="btn-accion-tabla consultadeposito tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="codigo" id="codigo" class="form-control codigodeposito"
                value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="20" autocomplete="off"
                style="max-width: 8rem;"
                @if (!empty($data->id)) readonly @endif/>
            <input type="text" name="nombre" id="nombre" class="form-control descripciondeposito flex-grow-1"
                value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="50"
                placeholder="Descripción"/>
        </div>
        <small class="form-text text-muted">Código en Anita (depm_deposito). Descripción = depm_desc.</small>
    </div>
</div>
<div class="form-group row">
	<label for="tipodeposito" class="col-lg-3 col-form-label requerido">Tipo de depósito</label>
	<select id="tipodeposito" name="tipodeposito" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de depósito --</option>
       	@foreach($tipodeposito_enum as $tipodeposito)
			@if ($tipodeposito['nombre'] == old('tipodeposito', $data->tipodeposito ?? ''))
       			<option value="{{ $tipodeposito['nombre'] }}" selected>{{ $tipodeposito['nombre'] }}</option>
			@else
			    <option value="{{ $tipodeposito['nombre'] }}">{{ $tipodeposito['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
