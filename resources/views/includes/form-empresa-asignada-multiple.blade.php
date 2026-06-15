{{--
    Multiselect de empresas (ABM usuario admin). Fuente: EmpresaRepository::all().
    Variables: $empresa_query (collection o array id=>nombre), $empresa_ids seleccionados vía old/data.
    Opcionales: $col_label, $col_input, $id, $name, $label.
--}}
@php
    $empresasRaw = $empresa_query ?? [];
    $empresasDisponibles = collect($empresasRaw);
    if ($empresasDisponibles->isNotEmpty() && ! is_object($empresasDisponibles->first())) {
        $empresasDisponibles = collect($empresasRaw)->map(fn ($nombre, $id) => (object) [
            'id' => $id,
            'nombre' => $nombre,
        ])->values();
    }
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaUnicaRegistro = $empresaUnica ? $empresasDisponibles->first() : null;
    $inputId = $id ?? 'empresa_id';
    $inputName = $name ?? 'empresa_ids[]';
    $labelText = $label ?? 'Empresas';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-8';
    $seleccionados = collect(old('empresa_ids', $empresa_ids_seleccionados ?? (isset($data) ? $data->usuario_empresas->pluck('id')->all() : [])))
        ->map(fn ($id) => (int) $id)
        ->all();
    if ($empresaUnica && empty($seleccionados) && $empresaUnicaRegistro) {
        $seleccionados = [(int) $empresaUnicaRegistro->id];
    }
@endphp
<div class="form-group row">
    <label for="{{ $inputId }}" class="{{ $colLabel }} col-form-label requerido">{{ $labelText }}</label>
    <div class="{{ $colInput }}">
        @if ($empresaUnica && $empresaUnicaRegistro)
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $empresaUnicaRegistro->id }}"/>
            <input type="text" class="form-control" readonly value="{{ $empresaUnicaRegistro->nombre }}"/>
        @else
            <select class="form-control select2" id="{{ $inputId }}" name="{{ $inputName }}" multiple="multiple" required data-placeholder="Seleccione una o más empresas">
                @foreach ($empresasDisponibles as $emp)
                    <option value="{{ $emp->id }}" @selected(in_array((int) $emp->id, $seleccionados, true))>{{ $emp->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>
