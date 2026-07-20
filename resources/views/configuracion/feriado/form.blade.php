@php
    $tiposFeriado = ['inamovible', 'trasladable', 'puente', 'no laborable', 'religioso'];
    $tipoActual = old('tipo', $data->tipo ?? '');
@endphp
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', isset($data->fecha) && $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('Y-m-d') : '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="tipo" class="col-lg-3 col-form-label">Tipo</label>
    <div class="col-lg-3">
        <input type="text" name="tipo" id="tipo" class="form-control" list="lista-tipos-feriado" value="{{ $tipoActual }}" maxlength="50" placeholder="Opcional"/>
        <datalist id="lista-tipos-feriado">
            @foreach ($tiposFeriado as $t)
                <option value="{{ $t }}"></option>
            @endforeach
        </datalist>
    </div>
</div>
