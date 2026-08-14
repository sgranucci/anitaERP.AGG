@php
    use App\Models\Caja\PerdidaPersonal;
    $esEdicion = isset($data->id) && $data->id;
    $empresaIdSel = old('empresa_id', $data->empresa_id ?? null);
    $empleadoIdSel = (int) old('empleado_sueldos_id', $data->empleado_sueldos_id ?? 0);
    $supervisorIdSel = (int) old('supervisor_empleado_sueldos_id', $data->supervisor_empleado_sueldos_id ?? 0);
    $conceptoIdSel = (int) old('concepto_perdida_id', $data->concepto_perdida_id ?? 0);
    $conceptoCodigoSel = 0;
    foreach ($conceptos ?? [] as $c) {
        if ((int) $c->id === $conceptoIdSel) {
            $conceptoCodigoSel = (int) $c->codigo;
            break;
        }
    }
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

<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 control-label text-right pr-2 requerido">Centro de costo</label>
    <div class="col-lg-6">
        <select name="centrocosto_id" id="centrocosto_id" class="form-control" required>
            <option value="">-- Elija centro de costo --</option>
            @foreach($centroscosto ?? [] as $cc)
                <option value="{{ $cc->id }}"
                    @selected((int) $cc->id === (int) old('centrocosto_id', $data->centrocosto_id ?? 0))>
                    {{ $cc->codigo }} — {{ $cc->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="imputacion_perdida_id" class="col-lg-3 control-label text-right pr-2 requerido">Imputaci&oacute;n</label>
    <div class="col-lg-6">
        <select name="imputacion_perdida_id" id="imputacion_perdida_id" class="form-control" required>
            <option value="">-- Elija imputaci&oacute;n --</option>
            @foreach($imputaciones ?? [] as $imp)
                <option value="{{ $imp->id }}"
                    @selected((int) $imp->id === (int) old('imputacion_perdida_id', $data->imputacion_perdida_id ?? 0))>
                    {{ $imp->codigo }} — {{ $imp->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="empleado_sueldos_id" class="col-lg-3 control-label text-right pr-2 requerido">Empleado</label>
    <div class="col-lg-6">
        <select name="empleado_sueldos_id" id="empleado_sueldos_id" class="form-control" required>
            <option value="">-- Elija empleado --</option>
            @foreach($empleados ?? [] as $emp)
                <option value="{{ $emp->id }}"
                    @selected((int) $emp->id === $empleadoIdSel)>
                    {{ $emp->legajo }} — {{ $emp->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="supervisor_empleado_sueldos_id" class="col-lg-3 control-label text-right pr-2 requerido">Supervisor</label>
    <div class="col-lg-6">
        <select name="supervisor_empleado_sueldos_id" id="supervisor_empleado_sueldos_id" class="form-control" required>
            <option value="">-- Elija supervisor --</option>
            @foreach($empleados ?? [] as $emp)
                <option value="{{ $emp->id }}"
                    @selected((int) $emp->id === $supervisorIdSel)>
                    {{ $emp->legajo }} — {{ $emp->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

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

<div class="form-group row">
    <label for="concepto_perdida_id" class="col-lg-3 control-label text-right pr-2 requerido">Concepto</label>
    <div class="col-lg-6">
        <select name="concepto_perdida_id" id="concepto_perdida_id" class="form-control" required>
            <option value="">-- Elija concepto --</option>
            @foreach($conceptos ?? [] as $con)
                <option value="{{ $con->id }}"
                        data-codigo="{{ $con->codigo }}"
                    @selected((int) $con->id === $conceptoIdSel)>
                    {{ $con->codigo }} — {{ $con->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

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
