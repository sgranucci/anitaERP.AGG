@php
    $empresasExcluidas = collect($empresas_sin_pv ?? []);
@endphp

<div class="alert alert-info py-2 small mb-3">
    <strong><i class="fa fa-info-circle"></i> Empresa operativa.</strong>
    Solo puede habilitar o cerrar turnos en empresas que tengan
    <strong>configuración de punto de venta gastronomía</strong> para la terminal
    <code>{{ $identificador_pc }}</code>.
    @if (collect($empresa_query ?? [])->count() === 1)
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

@if (collect($empresa_query ?? [])->isEmpty())
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
        @include('includes.listado.filtro_empresa_asignada_inline', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresa_id,
            'required' => true,
            'permite_todas' => false,
            'select_class' => 'js-auto-consultar-empresa',
        ])
    </form>
@endif
