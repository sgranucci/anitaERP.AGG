<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $retencioniva->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="formacalculo" class="col-lg-3 col-form-label requerido">Forma de cálculo</label>
	<select id="formacalculo" name="formacalculo" class="col-lg-4 form-control" required>
    	<option value="">-- Elija forma de cálculo --</option>
       	@foreach($formacalculo_enum as $formacalculo)
			@if ($formacalculo['valor'] == old('formacalculo',$retencioniva->formacalculo??''))
       			<option value="{{ $formacalculo['valor'] }}" selected>{{ $formacalculo['nombre'] }}</option>    
			@else
			    <option value="{{ $formacalculo['valor'] }}">{{ $formacalculo['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="porcentajeretencion" class="col-lg-3 col-form-label requerido">Porcentaje retencion</label>
    <div class="col-lg-3">
    <input type="number" name="porcentajeretencion" id="porcentajeretencion" class="form-control" value="{{old('porcentajeretencion', $retencioniva->porcentajeretencion ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="minimoimponible" class="col-lg-3 col-form-label requerido">Mínimo imponible</label>
    <div class="col-lg-3">
    <input type="number" name="minimoimponible" id="minimoimponible" class="form-control" value="{{old('minimoimponible', $retencioniva->minimoimponible ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="regimen" class="col-lg-3 col-form-label requerido">Código de régimen</label>
    <div class="col-lg-3">
    <input type="number" name="regimen" id="regimen" class="form-control" value="{{old('regimen', $retencioniva->regimen ?? '')}}">
    </div>
</div>
<div class="form-group row" id="divbaseimponible">
    <label for="baseimponible" class="col-lg-3 col-form-label requerido">Base imponible</label>
    <div class="col-lg-4">
    <input type="number" name="baseimponible" id="baseimponible" class="form-control" value="{{old('baseimponible', $retencioniva->baseimponible ?? '0')}}">
    </div>
</div>
<div class="form-group row" id="divcantidadperiodoacumula">
    <label for="cantidadperiodoacumula" class="col-lg-3 col-form-label requerido">Cantidad de períodos que acumula</label>
    <div class="col-lg-2">
    <input type="number" name="cantidadperiodoacumula" id="cantidadperiodoacumula" class="form-control" value="{{old('cantidadperiodoacumula', $retencioniva->cantidadperiodoacumula ?? '0')}}">
    </div>
</div>
<div class="form-group row" id="divvalorunitario">
    <label for="valorunitario" class="col-lg-3 col-form-label requerido">Valor unitario</label>
    <div class="col-lg-2">
    <input type="number" name="valorunitario" id="valorunitario" class="form-control" value="{{old('valorunitario', $retencioniva->valorunitario ?? '0')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-1">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $retencioniva->codigo ?? '')}}" readonly>
    </div>
</div>
