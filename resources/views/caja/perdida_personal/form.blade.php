@php
    use App\Models\Caja\PerdidaPersonal;
    $esEdicion = isset($data->id) && $data->id;
    $empresaIdSel = old('empresa_id', $data->empresa_id ?? null);
    $conceptoCodigoSel = (int) ($conceptoSeleccionado->codigo ?? 0);
    $maquinaHabilitada = in_array($conceptoCodigoSel, $conceptos_con_maquina ?? PerdidaPersonal::CONCEPTOS_CON_MAQUINA, true);
@endphp

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $empresaIdSel,
    'solo_lectura' => $esEdicion,
    'col_label' => 'col-lg-3 text-right pr-2',
    'col_input' => 'col-lg-5',
])

<div class="form-group row">
    <label for="numero" class="col-lg-3 control-label text-right pr-2">N&uacute;mero</label>
    <div class="col-lg-3">
        <input type="number" name="numero" id="numero" class="form-control text-right" min="1" step="1"
               value="{{ old('numero', $data->numero ?? '') }}"
               @if($esEdicion) readonly @endif
               placeholder="{{ $esEdicion ? '' : 'Vacío = automático Anita' }}"/>
        @if(! $esEdicion)
            <small class="form-text text-muted">Vac&iacute;o o 0: reserva el siguiente n&uacute;mero en Anita (numabm a-perdmae.c).</small>
        @endif
    </div>
</div>

<div class="form-group row">
    <label for="fecha" class="col-lg-3 control-label text-right pr-2 requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" required
               value="{{ old('fecha', optional($data->fecha)->format('Y-m-d') ?? date('Y-m-d')) }}"/>
    </div>
</div>

@include('contable.partials.campo_consulta_centrocosto', [
    'prefix' => 'perdida_personal',
    'layout' => 'form_row',
    'inputName' => 'centrocosto_id',
    'inputId' => 'centrocosto_id',
    'centrocostoId' => $centrocostoSeleccionado->id ?? '',
    'codigo' => $centrocostoSeleccionado->codigo ?? '',
    'descripcion' => $centrocostoSeleccionado->nombre ?? '',
    'col_label' => 'col-lg-3',
    'col_input' => 'col-lg-6',
    'required' => true,
])

@include('caja.perdida_personal.partials.campo_consulta_catalogo', [
    'tipo' => 'imputacion',
    'label' => 'Imputaci&oacute;n',
    'inputName' => 'imputacion_perdida_id',
    'inputId' => 'imputacion_perdida_id',
    'registroId' => $imputacionSeleccionada->id ?? 0,
    'codigo' => $imputacionSeleccionada->codigo ?? '',
    'descripcion' => $imputacionSeleccionada->nombre ?? '',
])

@include('caja.perdida_personal.partials.campo_consulta_catalogo', [
    'tipo' => 'empleado',
    'label' => 'Empleado',
    'inputName' => 'empleado_sueldos_id',
    'inputId' => 'empleado_sueldos_id',
    'registroId' => $empleadoSeleccionado->id ?? 0,
    'codigo' => $empleadoSeleccionado->legajo ?? '',
    'descripcion' => $empleadoSeleccionado->nombre ?? '',
])

@include('caja.perdida_personal.partials.campo_consulta_catalogo', [
    'tipo' => 'empleado',
    'label' => 'Supervisor',
    'inputName' => 'supervisor_empleado_sueldos_id',
    'inputId' => 'supervisor_empleado_sueldos_id',
    'registroId' => $supervisorSeleccionado->id ?? 0,
    'codigo' => $supervisorSeleccionado->legajo ?? '',
    'descripcion' => $supervisorSeleccionado->nombre ?? '',
])

<div class="form-group row">
    <label for="turno" class="col-lg-3 control-label text-right pr-2 requerido">Turno</label>
    <div class="col-lg-4">
        <select name="turno" id="turno" class="form-control" required>
            <option value="">-- Elija turno --</option>
            @foreach($turno_enum ?? PerdidaPersonal::$enumTurno as $turno)
                <option value="{{ $turno['valor'] }}"
                    @selected($turno['valor'] == old('turno', $data->turno ?? PerdidaPersonal::TURNO_MANIANA))>
                    {{ $turno['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@include('caja.perdida_personal.partials.campo_consulta_catalogo', [
    'tipo' => 'concepto',
    'label' => 'Concepto',
    'inputName' => 'concepto_perdida_id',
    'inputId' => 'concepto_perdida_id',
    'registroId' => $conceptoSeleccionado->id ?? 0,
    'codigo' => $conceptoSeleccionado->codigo ?? '',
    'descripcion' => $conceptoSeleccionado->nombre ?? '',
])

<div class="form-group row">
    <label for="maquina" class="col-lg-3 control-label text-right pr-2">M&aacute;quina</label>
    <div class="col-lg-3">
        <input type="text" name="maquina" id="maquina" class="form-control" maxlength="10"
               value="{{ old('maquina', $data->maquina ?? '') }}"
               @if(! $maquinaHabilitada) disabled @endif/>
        <small class="form-text text-muted">Obligatoria para conceptos 6 y 8.</small>
    </div>
</div>

<div class="form-group row">
    <label for="importe" class="col-lg-3 control-label text-right pr-2 requerido">Importe</label>
    <div class="col-lg-3">
        <input type="number" name="importe" id="importe" class="form-control text-right" min="0.01" step="0.01" required
               value="{{ old('importe', $data->importe ?? '') }}"/>
    </div>
</div>

<div class="form-group row">
    <label for="leyenda" class="col-lg-3 control-label text-right pr-2">Leyenda</label>
    <div class="col-lg-8">
        <input type="text" name="leyenda" id="leyenda" class="form-control" maxlength="80"
               value="{{ old('leyenda', $data->leyenda ?? '') }}"/>
        <small class="form-text text-muted">M&aacute;ximo 80 caracteres (perdm_leyenda).</small>
    </div>
</div>

@if($esEdicion)
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Usuario</label>
    <div class="col-lg-5">
        <input type="text" class="form-control" readonly
               value="{{ $data->usuario->nombre ?? ($data->usuario_id ? '#'.$data->usuario_id : '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Fecha ingreso</label>
    <div class="col-lg-3">
        <input type="text" class="form-control" readonly
               value="{{ optional($data->fecha_ingreso)->format('d/m/Y') }}"/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Hora ingreso</label>
    <div class="col-lg-3">
        <input type="text" class="form-control" readonly value="{{ $data->hora_ingreso ?? '' }}"/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Estado</label>
    <div class="col-lg-3">
        <input type="text" class="form-control" readonly value="{{ $data->estado_label }}"/>
    </div>
</div>
@endif
