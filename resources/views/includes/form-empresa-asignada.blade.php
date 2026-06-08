{{--
    Empresa del usuario (EmpresaRepository::allFiltrado()).
    Variables: $empresa_query (collection), $empresa_id (valor seleccionado), $solo_lectura (bool opcional, ej. edición).
    Opcionales: $label, $col_label, $col_input, $required, $id, $name, $permite_vacio, $opcion_vacia, $mostrar_id, $select_class.
--}}
@php
    $labelText = $label ?? 'Empresa';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-5';
    $esRequerido = $required ?? true;
@endphp

<div class="form-group row">
    <label for="{{ $id ?? 'empresa_id' }}" class="{{ $colLabel }} control-label {{ $esRequerido ? 'requerido' : '' }}">{{ $labelText }}</label>
    <div class="{{ $colInput }}">
        @include('includes.form-empresa-asignada-control')
    </div>
</div>
