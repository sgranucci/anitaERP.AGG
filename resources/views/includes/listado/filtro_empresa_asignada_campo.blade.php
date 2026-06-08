{{--
    Filtro empresa en form-group (toolbar de índices con más filtros).
    Variables: $empresa_query o $empresas, $empresa_id, $f (opcional).
    Opcionales: $id, $name, $label, $wrapper_class, $label_class, $required, $permite_todas, $opcion_todas, $select_class, $mostrar_opcion_vacia.
--}}
@php
    $empresasDisponibles = collect($empresa_query ?? $empresas ?? []);
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaUnicaRegistro = $empresaUnica ? $empresasDisponibles->first() : null;
    $inputId = $id ?? 'empresa_id';
    $inputName = $name ?? 'empresa_id';
    $labelText = $label ?? 'Empresa';
    $wrapperClass = $wrapper_class ?? 'mr-2 mb-2';
    $labelClass = $label_class ?? 'mr-1';
    $empresaIdSeleccion = (int) ($empresa_id ?? ($f['empresa_id'] ?? ($empresaUnicaRegistro?->id ?? 0)));
    $esRequerido = $required ?? false;
    $permiteTodas = $permite_todas ?? true;
    $opcionTodas = $opcion_todas ?? 'Todas';
    $selectClass = trim($select_class ?? '');
    $mostrarOpcionVacia = $mostrar_opcion_vacia ?? $permiteTodas;
@endphp
<div class="form-group {{ $wrapperClass }}">
    <label for="{{ $inputId }}" class="{{ $labelClass }}">{{ $labelText }}</label>
    @if ($empresaUnica && $empresaUnicaRegistro && ! $permiteTodas)
        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $empresaUnicaRegistro->id }}"/>
        <input type="text" class="form-control {{ $selectClass }}" readonly value="{{ $empresaUnicaRegistro->nombre }}"/>
    @else
        <select name="{{ $inputName }}" id="{{ $inputId }}" class="form-control {{ $selectClass }}" @if($esRequerido) required @endif>
            @if ($mostrarOpcionVacia)
                <option value="">{{ $opcionTodas }}</option>
            @endif
            @foreach ($empresasDisponibles as $emp)
                <option value="{{ $emp->id }}" @selected($empresaIdSeleccion === (int) $emp->id)>{{ $emp->nombre }}</option>
            @endforeach
        </select>
    @endif
</div>
