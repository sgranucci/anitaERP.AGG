<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $retencionsuss->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="formacalculo" class="col-lg-3 col-form-label requerido">Forma de cálculo</label>
	<select id="formacalculo" name="formacalculo" class="col-lg-4 form-control" required>
    	<option value="">-- Elija forma de cálculo --</option>
       	@foreach($formacalculo_enum as $formacalculo)
			@if ($formacalculo['valor'] == old('formacalculo',$retencionsuss->formacalculo??''))
       			<option value="{{ $formacalculo['valor'] }}" selected>{{ $formacalculo['nombre'] }}</option>    
			@else
			    <option value="{{ $formacalculo['valor'] }}">{{ $formacalculo['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="valorretencion" class="col-lg-3 col-form-label requerido">Valor retención</label>
    <div class="col-lg-3">
    <input type="number" name="valorretencion" id="valorretencion" class="form-control" value="{{old('valorretencion', $retencionsuss->valorretencion ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="minimoimponible" class="col-lg-3 col-form-label requerido">Mínimo imponible</label>
    <div class="col-lg-3">
    <input type="number" name="minimoimponible" id="minimoimponible" class="form-control" value="{{old('minimoimponible', $retencionsuss->minimoimponible ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="regimen" class="col-lg-3 col-form-label requerido">Código de régimen</label>
    <div class="col-lg-3">
    <input type="number" name="regimen" id="regimen" class="form-control" value="{{old('regimen', $retencionsuss->regimen ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-1">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $retencionsuss->codigo ?? '')}}" readonly>
    </div>
</div>
