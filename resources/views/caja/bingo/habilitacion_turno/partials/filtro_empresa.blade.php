@php
    $empresasExcluidas = collect($empresas_sin_pv ?? []);
@endphp

<div class="alert alert-info py-2 small mb-3">
    <strong><i class="fa fa-info-circle"></i> Empresa operativa.</strong>
    Solo puede habilitar o cerrar turnos en empresas que tengan
    <strong>configuración de terminal bingo</strong> para la terminal
    <code>{{ $identificador_pc }}</code>.
    @if (collect($empresa_query ?? [])->count() === 1)
        Si su usuario tiene una sola empresa operativa en esta PC, queda preseleccionada automáticamente.
    @else
        Al cambiar la empresa en el listado se recarga el panel automáticamente.
    @endif
    @if ($empresasExcluidas->isNotEmpty())
        <br>
        Las siguientes empresas asignadas a su usuario
        <strong>no tienen terminal configurada</strong> en esta PC y no pueden elegirse:
        {{ $empresasExcluidas->pluck('nombre')->join(', ') }}.
    @endif
</div>

@if (collect($empresa_query ?? [])->isEmpty())
    <div class="alert alert-warning mb-3">
        Ninguna de las empresas asignadas a su usuario tiene terminal bingo configurada
        para <code>{{ $identificador_pc !== '' ? $identificador_pc : '(vacío)' }}</code>.
        @php
            $configsPv = collect($configs_pv_asignadas ?? []);
        @endphp
        @if ($configsPv->isNotEmpty())
            <br><br>
            <strong>Configuraciones existentes para sus empresas:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($configsPv as $cfgPv)
                    <li>
                        {{ $cfgPv->empresa->nombre ?? 'Empresa #'.$cfgPv->empresa_id }}
                        → identificador <code>{{ $cfgPv->identificador_pc }}</code>
                        @if ($cfgPv->descripcion)
                            <span class="text-muted">({{ $cfgPv->descripcion }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <small class="d-block mt-2 text-muted">
                El identificador de esta sesión debe coincidir con <code>identificador_pc</code> de la fila.
                Use la IP de la caja en el ABM y <code>BINGO_IDENTIFICADOR_USAR_IP_CLIENTE=true</code>,
                o fije <code>BINGO_IDENTIFICADOR_PC</code> en <code>.env</code> con el mismo valor.
            </small>
        @else
            Revise la configuración en
            <strong>Caja → Bingo → Config. terminal</strong>.
        @endif
    </div>
@else
    <form method="get"
          action="{{ route('bingo_habilitacion_turno') }}"
          id="form-filtro-empresa-habilitacion-turno"
          class="form-inline mb-3">
        @include('includes.listado.filtro_empresa_asignada_inline', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresa_id,
            'required' => true,
            'permite_todas' => false,
            'select_class' => 'js-auto-consultar-empresa',
        ])
    </form>
@endif
