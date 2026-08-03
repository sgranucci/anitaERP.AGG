@extends("theme.$theme.layout")
@section('titulo')
    Configuraci&oacute;n remesas
@endsection

@section('contenido')
@php
    $filtrosQuery = $filtrosQuery ?? [];
    $puedeEditarCuenta = can('editar-cuentas-de-caja', false) || can('listar-cuentas-de-caja', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuentas configuradas para remesas</h3>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Preview de las cuentas vinculadas a cada <strong>uso de cuenta de caja</strong> de remesa.
                    Desde aqu&iacute; solo se toca el v&iacute;nculo (pivot): agregar o desafectar no modifica el maestro de la cuenta.
                </p>
                <p class="text-muted small mb-3">
                    La columna <em>Cuenta contable</em> es la que usar&aacute; el asiento REM en remesa externa
                    (Debe destino / Haber origen efectivo). La remesa interna (TES) no genera asiento.
                    ABM completo del uso:
                    <a href="{{ route('consultar_usocuentacaja') }}" class="text-primary" target="_blank" rel="noopener">Uso cuenta de caja</a>.
                </p>

                <div class="form-group row mb-4">
                    <label for="filtro_empresa_preview" class="col-lg-2 col-form-label">Filtrar preview por empresa</label>
                    <div class="col-lg-4">
                        <select id="filtro_empresa_preview" class="form-control form-control-sm">
                            <option value="">Todas</option>
                            @foreach ($empresa_query as $empresa)
                                <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6 col-form-label text-muted small">
                        Incluye siempre las cuentas compartidas (<code>empresa_id</code> vac&iacute;o).
                    </div>
                </div>

                @forelse ($grupos as $grupo)
                    <div class="card card-outline card-secondary mb-4 remesa-config-grupo"
                         data-clave="{{ $grupo['clave'] }}">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <h4 class="card-title mb-0">{{ $grupo['titulo'] }}</h4>
                                <small class="text-muted">Uso: {{ $grupo['uso_nombre'] }}
                                    @if ($grupo['uso_id'] <= 0)
                                        <span class="badge badge-danger">uso inexistente</span>
                                    @endif
                                </small>
                            </div>
                            <span class="badge badge-{{ $grupo['genera_asiento'] ? 'info' : 'secondary' }}">
                                {{ $grupo['genera_asiento'] ? 'Interviene en asiento REM' : 'Sin asiento (solo caja)' }}
                            </span>
                        </div>
                        <div class="card-body pt-2">
                            <p class="small mb-3">{{ $grupo['descripcion'] }}</p>

                            @if ($grupo['uso_id'] > 0)
                                <form class="form-inline mb-3 remesa-config-agregar"
                                      method="post"
                                      action="{{ route('configurar_remesa_agregar') }}">
                                    @csrf
                                    <input type="hidden" name="clave" value="{{ $grupo['clave'] }}">
                                    @foreach ($filtrosQuery as $qk => $qv)
                                        @if (! is_array($qv))
                                            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                                        @endif
                                    @endforeach
                                    <label class="mr-2 mb-0">Agregar por c&oacute;digo</label>
                                    <input type="text"
                                           name="codigo"
                                           class="form-control form-control-sm mr-2 remesa-config-codigo"
                                           placeholder="Ej. 120 o TES (F1)"
                                           title="Ingrese c&oacute;digo y Agregar, o F1 / Buscar cuenta para consultar"
                                           style="width: 8rem;"
                                           required>
                                    <select name="empresa_id"
                                            class="form-control form-control-sm mr-2 remesa-config-empresa-add"
                                            style="min-width: 10rem;">
                                        <option value="">Empresa (opc.)</option>
                                        @foreach ($empresa_query as $empresa)
                                            <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success mr-2">
                                        <i class="fas fa-plus"></i> Agregar
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary remesa-config-abrir-modal"
                                            data-clave="{{ $grupo['clave'] }}">
                                        <i class="fas fa-search"></i> Buscar cuenta
                                    </button>
                                </form>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover mb-0 remesa-config-tabla">
                                    <thead style="background-color: #85C1E9; color: #17202A;">
                                        <tr>
                                            <th>ID</th>
                                            <th>C&oacute;digo</th>
                                            <th>Cuenta de caja</th>
                                            <th>Empresa</th>
                                            <th>Mon.</th>
                                            <th>Cta. contable</th>
                                            <th>Nombre cta. contable</th>
                                            <th style="width: 9rem;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($grupo['cuentas'] as $fila)
                                            <tr class="remesa-config-fila"
                                                data-empresa-id="{{ $fila['empresa_id'] ?? '' }}">
                                                <td>{{ $fila['cuentacaja_id'] }}</td>
                                                <td>{{ $fila['codigo'] }}</td>
                                                <td>{{ $fila['nombre'] }}</td>
                                                <td>{{ $fila['empresa_nombre'] }}</td>
                                                <td>{{ $fila['moneda_abrev'] }}</td>
                                                <td>
                                                    @if ($fila['tiene_cuentacontable'])
                                                        {{ $fila['cuentacontable_codigo'] }}
                                                    @elseif ($grupo['genera_asiento'])
                                                        <span class="text-danger" title="Sin cuenta contable no se puede armar el asiento">Sin CC</span>
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($fila['tiene_cuentacontable'])
                                                        {{ $fila['cuentacontable_nombre'] }}
                                                    @elseif ($grupo['genera_asiento'])
                                                        <span class="text-danger">Falta en maestro</span>
                                                    @else
                                                        <span class="text-muted">N/A (sin asiento)</span>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    @if ($puedeEditarCuenta)
                                                        <a href="{{ route('editar_cuentacaja', $fila['cuentacaja_id']) }}?origen=modal_consulta&vista=consulta"
                                                           class="btn btn-xs btn-outline-info"
                                                           target="_blank"
                                                           rel="noopener"
                                                           title="Consultar / editar cuenta">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    @endif
                                                    @if ($grupo['uso_id'] > 0)
                                                        <form method="post"
                                                              action="{{ route('configurar_remesa_quitar') }}"
                                                              class="d-inline remesa-config-quitar"
                                                              onsubmit="return confirm('¿Desafectar esta cuenta del uso {{ $grupo['uso_nombre'] }}? Solo se quita el vínculo.');">
                                                            @csrf
                                                            <input type="hidden" name="clave" value="{{ $grupo['clave'] }}">
                                                            <input type="hidden" name="cuentacaja_id" value="{{ $fila['cuentacaja_id'] }}">
                                                            @foreach ($filtrosQuery as $qk => $qv)
                                                                @if (! is_array($qv))
                                                                    <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                                                                @endif
                                                            @endforeach
                                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Desafectar del uso">
                                                                <i class="fas fa-unlink"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="remesa-config-vacio">
                                                <td colspan="8" class="text-muted">
                                                    No hay cuentas con este uso.
                                                    @if ($grupo['uso_id'] <= 0)
                                                        Primero cree el uso «{{ $grupo['uso_nombre'] }}» en Uso cuenta de caja.
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">No hay grupos de configuraci&oacute;n definidos.</p>
                @endforelse
            </div>
            <div class="card-footer">
                <a href="{{ route('remesa', $filtrosQuery) }}" class="btn btn-outline-info btn-sm">
                    <i class="fa fa-reply-all"></i> Volver al listado
                </a>
            </div>
        </div>
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')
@endsection

@section('scripts')
<script>
window.REMESA_CONFIG = {
    urlAgregar: @json(route('configurar_remesa_agregar')),
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/remesa/configurar.js') }}" type="text/javascript"></script>
@endsection
