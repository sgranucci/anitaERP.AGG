@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaNombreSeleccionada = $empresasDisponibles->firstWhere('id', (int) ($empresa_id ?? 0))?->nombre;
    $empresasExcluidas = collect($empresas_sin_pv ?? []);
@endphp

<div class="alert alert-info py-2 small mb-3">
    <strong><i class="fa fa-info-circle"></i> Empresa operativa.</strong>
    Solo puede habilitar o cerrar turnos en empresas que tengan
    <strong>configuración de punto de venta gastronomía</strong> para la terminal
    <code>{{ $identificador_pc }}</code>.
    @if ($empresaUnica)
        Si su usuario tiene una sola empresa operativa en esta PC, queda preseleccionada automáticamente.
    @else
        Al cambiar la empresa en el listado se recarga el panel automáticamente.
    @endif
    @if ($empresasExcluidas->isNotEmpty())
        <br>
        Las siguientes empresas asignadas a su usuario
        <strong>no tienen PV configurado</strong> en esta terminal y no pueden elegirse:
        {{ $empresasExcluidas->pluck('nombre')->join(', ') }}.
    @endif
</div>

@if ($empresasDisponibles->isEmpty())
    <div class="alert alert-warning mb-3">
        Ninguna de las empresas asignadas a su usuario tiene punto de venta gastronomía configurado
        para la terminal <code>{{ $identificador_pc }}</code>.
        @if ($empresasExcluidas->isNotEmpty())
            Revise la configuración en
            <strong>Ventas → Gastronomía → Configuración punto de venta</strong>.
        @endif
    </div>
@else
    <form method="get"
          action="{{ route('gastronomia_habilitacion_turno') }}"
          id="form-filtro-empresa-habilitacion-turno"
          class="form-inline mb-3">
        @if (! empty($accion))
            <input type="hidden" name="accion" value="{{ $accion }}"/>
        @endif
        <label class="mr-2" for="empresa_id">Empresa</label>
        @if ($empresaUnica)
            <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa_id }}"/>
            <input type="text" class="form-control mr-2" readonly value="{{ $empresaNombreSeleccionada }}"/>
        @else
            <select name="empresa_id" id="empresa_id" class="form-control mr-2 js-auto-consultar-empresa" required>
                @foreach ($empresasDisponibles as $emp)
                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                        {{ $emp->nombre }}
                    </option>
                @endforeach
            </select>
        @endif
    </form>
@endif
