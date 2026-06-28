{{--
    Filtro de empresa en listados (EmpresaRepository::allFiltrado()).
    Variables: $empresa_query (collection), $empresa_id o $f['empresa_id'] (valor seleccionado).
    Opcionales: $col_class (default col-md-2 col-sm-6 mb-2), $opcion_todas (default "Todas (asignadas)").
--}}
@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaIdSeleccion = (int) ($empresa_id ?? ($f['empresa_id'] ?? 0));
    $colClass = $col_class ?? 'col-md-2 col-sm-6 mb-2';
    $opcionTodas = $opcion_todas ?? 'Todas (asignadas)';
    $requiereSeleccion = (bool) ($requiere_seleccion ?? false);
@endphp
@if ($empresasDisponibles->count() > 1)
    <div class="form-group {{ $colClass }}">
        <label class="small mb-1" for="empresa_id">
            Empresa
            @if ($requiereSeleccion)
                <span class="text-danger">*</span>
            @endif
        </label>
        <select name="empresa_id" id="empresa_id" class="form-control form-control-sm"@if ($requiereSeleccion) required @endif>
            @if ($requiereSeleccion)
                <option value="" disabled @selected($empresaIdSeleccion <= 0)>Seleccione empresa…</option>
            @else
                <option value="">{{ $opcionTodas }}</option>
            @endif
            @foreach ($empresasDisponibles as $emp)
                <option value="{{ $emp->id }}" @selected($empresaIdSeleccion === (int) $emp->id)>{{ $emp->nombre }}</option>
            @endforeach
        </select>
    </div>
@elseif ($empresasDisponibles->count() === 1)
    <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}"/>
@endif
