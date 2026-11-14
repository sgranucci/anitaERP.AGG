{{--
    Sala del usuario (Crown). Misma UX que empresa asignada:
    1 sala → hidden + readonly; N salas → select.
    Variables: $sala_query, $sala_id, $solo_lectura (opcional).
    Opcionales: $label, $col_label, $col_input, $required, $id, $name, $empresa_input_id.
--}}
@php
    $labelText = $label ?? 'Sala';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-5';
    $esRequerido = $required ?? true;
@endphp

<div class="form-group row">
    <label for="{{ $id ?? 'sala_id' }}" class="{{ $colLabel }} control-label {{ $esRequerido ? 'requerido' : '' }}">{{ $labelText }}</label>
    <div class="{{ $colInput }}">
        @include('includes.form-sala-asignada-control')
    </div>
</div>
