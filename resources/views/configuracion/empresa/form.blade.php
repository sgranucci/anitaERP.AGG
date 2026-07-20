<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>

<div class="form-group row">
    <label for="domicilio" class="col-lg-3 col-form-label">Direcci&oacute;n</label>
    <div class="col-lg-5">
        <input type="text" name="domicilio" id="domicilio" class="form-control" value="{{ old('domicilio', $data->domicilio ?? '') }}" maxlength="100" placeholder="Calle y n&uacute;mero"/>
    </div>
</div>

<div class="form-group row">
    <label for="pais_id" class="col-lg-3 col-form-label">Pa&iacute;s</label>
    <div class="col-lg-3">
        <select name="pais_id" id="pais_id" class="form-control">
            <option value="">-- Seleccionar --</option>
            @foreach ($pais_query ?? [] as $pais)
                <option value="{{ $pais->id }}" {{ (int) old('pais_id', $data->pais_id ?? 0) === (int) $pais->id ? 'selected' : '' }}>
                    {{ $pais->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="provincia_id" class="col-lg-3 col-form-label">Provincia</label>
    <div class="col-lg-3">
        <select name="provincia_id" id="provincia_id" class="form-control">
            <option value="">-- Seleccionar --</option>
            @foreach ($provincia_query ?? [] as $prov)
                <option value="{{ $prov->id }}" {{ (int) old('provincia_id', $data->provincia_id ?? 0) === (int) $prov->id ? 'selected' : '' }}>
                    {{ $prov->nombre }}
                </option>
            @endforeach
        </select>
        <input type="hidden" id="desc_provincia" name="desc_provincia" value="{{ old('desc_provincia', optional($data->provincia ?? null)->nombre ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="localidad_id" class="col-lg-3 col-form-label">Localidad</label>
    <div class="col-lg-3">
        <select name="localidad_id" id="localidad_id" class="form-control">
            @if (old('localidad_id', $data->localidad_id ?? ''))
                <option value="{{ old('localidad_id', $data->localidad_id) }}" selected>
                    {{ old('desc_localidad', optional($data->localidad ?? null)->nombre ?? '') }}
                </option>
            @else
                <option value=""></option>
            @endif
        </select>
        <input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{ old('localidad_id', $data->localidad_id ?? '') }}">
        <input type="hidden" id="desc_localidad" name="desc_localidad" value="{{ old('desc_localidad', optional($data->localidad ?? null)->nombre ?? '') }}">
    </div>
    <label for="codigopostal" class="col-lg-1 col-form-label text-right">CP</label>
    <div class="col-lg-2">
        <input type="text" name="codigopostal" id="codigopostal" class="form-control" value="{{ old('codigopostal', $data->codigopostal ?? '') }}" maxlength="50"/>
    </div>
</div>

<div class="form-group row">
    <label for="nroinscripcion" class="col-lg-3 col-form-label requerido">Nro. Inscripci&oacute;n (CUIT)</label>
    <div class="col-lg-2">
        <input type="text" name="nroinscripcion" id="nroinscripcion" class="form-control" value="{{ old('nroinscripcion', $data->nroinscripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="numeroiibb" class="col-lg-3 col-form-label requerido">Nro. IIBB</label>
    <div class="col-lg-3">
        <input type="text" name="numeroiibb" id="numeroiibb" class="form-control" value="{{ old('numeroiibb', $data->numeroiibb ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="fechainicioactividad" class="col-lg-3 col-form-label requerido">Fecha Inicio Actividades</label>
    <div class="col-lg-3">
        <input type="date" name="fechainicioactividad" id="fechainicioactividad" class="form-control requerido" value="{{ old('fechainicioactividad', $data->fechainicioactividad ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-2">
        <input type="number" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}"/>
    </div>
</div>
