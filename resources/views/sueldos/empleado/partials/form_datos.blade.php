@php
    $esEdicion = isset($data);
    $puedeEditar = $puedeEditar ?? ($esEdicion ? can('actualizar-empleado-sueldos', false) : can('crear-empleado-sueldos', false));
    $provinciaIdSel = old('provincia_id', $data->provincia_id ?? '');
    $localidadIdSel = old('localidad_id', $data->localidad_id ?? '');
    $descProvincia = old('desc_provincia', $data->provincia ?? '');
    $descLocalidad = old('desc_localidad', $data->localidad ?? '');
@endphp

<div class="row">
    {{-- ===================== Columna izquierda: identidad ===================== --}}
    <div class="col-lg-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
            'solo_lectura' => $esEdicion,
            'col_label' => 'col-lg-4 control-label text-right pr-2',
            'col_input' => 'col-lg-6',
        ])

        <div class="form-group row">
            <label for="legajo" class="col-lg-4 control-label text-right pr-2">Legajo</label>
            <div class="col-lg-4">
                <input type="number" name="legajo" id="legajo" class="form-control"
                       value="{{ old('legajo', $data->legajo ?? '') }}"
                       placeholder="Automático" {{ $esEdicion ? 'readonly' : '' }}>
            </div>
            @if ($esEdicion)
                <div class="col-lg-4 d-flex align-items-center">
                    <span class="badge badge-{{ ($data->estado ?? '') === 'A' ? 'success' : (($data->estado ?? '') === 'P' ? 'warning' : 'secondary') }} p-2">
                        {{ $estadosLabels[$data->estado] ?? $data->estado }}
                    </span>
                </div>
            @endif
        </div>

        <div class="form-group row">
            <label for="cuil" class="col-lg-4 control-label text-right pr-2">CUIL / CUIT</label>
            <div class="col-lg-8">
                <div class="input-group">
                    <input type="text" name="cuil" id="cuil" class="form-control" maxlength="13"
                           placeholder="XX-XXXXXXXX-X" oninput="formatarCUIT(this)"
                           value="{{ old('cuil', $data->cuil ?? '') }}" autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="btn-consulta-arca-padron-crear"
                                title="Ingresá el CUIT y consultá el padrón ARCA">
                            <i class="fa fa-search"></i> ARCA
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group row">
            <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required
                       value="{{ old('nombre', $data->nombre ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="documento" class="col-lg-4 control-label text-right pr-2">Documento</label>
            <div class="col-lg-5">
                <input type="text" name="documento" id="documento" class="form-control" maxlength="30"
                       value="{{ old('documento', $data->documento ?? '') }}">
            </div>
            <div class="col-lg-3 d-flex align-items-center pl-1">
                <div class="custom-control custom-checkbox mb-0">
                    <input type="checkbox" class="custom-control-input" name="confidencial" id="confidencial" value="1"
                           {{ old('confidencial', $data->confidencial ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label small text-muted" for="confidencial" title="Legajo confidencial">Confidencial</label>
                </div>
            </div>
        </div>

        <div class="form-group row">
            <label for="fecha_nacimiento" class="col-lg-4 control-label text-right pr-2">F. nacimiento</label>
            <div class="col-lg-5">
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control"
                       value="{{ old('fecha_nacimiento', optional($data->fecha_nacimiento ?? null)->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="sexo" class="col-lg-4 control-label text-right pr-2">Sexo</label>
            <div class="col-lg-5">
                <select name="sexo" id="sexo" class="form-control">
                    <option value="">—</option>
                    <option value="1" {{ old('sexo', $data->sexo ?? '') == '1' ? 'selected' : '' }}>Masculino</option>
                    <option value="2" {{ old('sexo', $data->sexo ?? '') == '2' ? 'selected' : '' }}>Femenino</option>
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="estado_civil" class="col-lg-4 control-label text-right pr-2">Estado civil</label>
            <div class="col-lg-5">
                <select name="estado_civil" id="estado_civil" class="form-control">
                    <option value="">—</option>
                    @foreach ([1=>'Soltero',2=>'Casado',3=>'Divorciado',4=>'Viudo',5=>'Separado'] as $k=>$v)
                        <option value="{{ $k }}" {{ (int) old('estado_civil', $data->estado_civil ?? 0) === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ===================== Columna derecha: contacto / obra social ===================== --}}
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="nacionalidad" class="col-lg-4 control-label text-right pr-2">Nacionalidad</label>
            <div class="col-lg-6">
                <input type="text" name="nacionalidad" id="nacionalidad" class="form-control" maxlength="40"
                       value="{{ old('nacionalidad', $data->nacionalidad ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="telefono" class="col-lg-4 control-label text-right pr-2">Teléfono</label>
            <div class="col-lg-6">
                <input type="text" name="telefono" id="telefono" class="form-control" maxlength="40"
                       value="{{ old('telefono', $data->telefono ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="telefono_emergencia" class="col-lg-4 control-label text-right pr-2">Tel. emerg.</label>
            <div class="col-lg-6">
                <input type="text" name="telefono_emergencia" id="telefono_emergencia" class="form-control" maxlength="40"
                       value="{{ old('telefono_emergencia', $data->telefono_emergencia ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="email" class="col-lg-4 control-label text-right pr-2">E-mail</label>
            <div class="col-lg-8">
                <input type="email" name="email" id="email" class="form-control" maxlength="120"
                       value="{{ old('email', $data->email ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="obrasocial_id" class="col-lg-4 control-label text-right pr-2">Obra social</label>
            <div class="col-lg-8">
                <select name="obrasocial_id" id="obrasocial_id" class="form-control">
                    <option value="">—</option>
                    @foreach ($obrasociales ?? [] as $os)
                        <option value="{{ $os->id }}" {{ (int) old('obrasocial_id', $data->obrasocial_id ?? 0) === (int) $os->id ? 'selected' : '' }}>
                            {{ $os->codigo }} — {{ $os->descripcion ?? $os->nombre ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="afiliacion_os" class="col-lg-4 control-label text-right pr-2">Afiliación O.S.</label>
            <div class="col-lg-6">
                <input type="text" name="afiliacion_os" id="afiliacion_os" class="form-control" maxlength="30"
                       value="{{ old('afiliacion_os', $data->afiliacion_os ?? '') }}">
            </div>
        </div>

        <div class="form-group row">
            <label for="sindicato_id" class="col-lg-4 control-label text-right pr-2">Sindicato</label>
            <div class="col-lg-8">
                <select name="sindicato_id" id="sindicato_id" class="form-control">
                    <option value="">—</option>
                    @foreach ($sindicatos ?? [] as $sin)
                        <option value="{{ $sin->id }}" {{ (int) old('sindicato_id', $data->sindicato_id ?? 0) === (int) $sin->id ? 'selected' : '' }}>
                            {{ $sin->codigo }} — {{ $sin->descripcion ?? $sin->nombre ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Domicilio (vinculado a maestros, como proveedores) ===================== --}}
<hr class="mt-2 mb-3">
<h5 class="mb-3"><i class="fa fa-map-marker-alt text-muted"></i> Domicilio</h5>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="pais_id">País</label>
            <select name="pais_id" id="pais_id" class="form-control">
                <option value="">-- Seleccionar --</option>
                @foreach ($pais_query ?? [] as $pais)
                    <option value="{{ $pais->id }}" {{ (int) old('pais_id', $data->pais_id ?? 0) === (int) $pais->id ? 'selected' : '' }}>{{ $pais->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="provincia_id">Provincia</label>
            <select name="provincia_id" id="provincia_id" class="form-control">
                <option value="">-- Seleccionar --</option>
                @foreach ($provincia_query ?? [] as $prov)
                    <option value="{{ $prov->id }}" {{ (int) $provinciaIdSel === (int) $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                @endforeach
            </select>
            <input type="hidden" id="desc_provincia" name="desc_provincia" value="{{ $descProvincia }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="localidad_id">Localidad</label>
            <select name="localidad_id" id="localidad_id" class="form-control">
                @if ($localidadIdSel)
                    <option value="{{ $localidadIdSel }}" selected>{{ $descLocalidad ?: $localidadIdSel }}</option>
                @else
                    <option value=""></option>
                @endif
            </select>
            <input type="hidden" id="localidad_id_previa" name="localidad_id_previa" value="{{ $localidadIdSel }}">
            <input type="hidden" id="desc_localidad" name="desc_localidad" value="{{ $descLocalidad }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="codigo_postal">Código postal</label>
            <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" maxlength="12"
                   value="{{ old('codigo_postal', $data->codigo_postal ?? '') }}" placeholder="Código Postal">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="domicilio">Dirección</label>
            <input type="text" name="domicilio" id="domicilio" class="form-control" maxlength="80"
                   value="{{ old('domicilio', $data->domicilio ?? '') }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="entre_calles">Entre calles</label>
            <input type="text" name="entre_calles" id="entre_calles" class="form-control" maxlength="80"
                   value="{{ old('entre_calles', $data->entre_calles ?? '') }}">
        </div>
    </div>
</div>
