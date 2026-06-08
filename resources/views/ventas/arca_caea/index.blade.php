@extends("theme.$theme.layout")
@section('titulo')
    CAEA — ARCA / WSFE
@endsection

@section('scripts')
@include('ventas.arca_caea.partials.detalle_script')
@endsection

@section('contenido')
@include('ventas.arca_caea.partials.detalle_modal')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Códigos CAEA (quincenales)</h3>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('arca_caea') }}" class="d-flex flex-wrap align-items-end mb-3">
                    @include('includes.listado.filtro_empresa_asignada_campo', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresaId,
                        'permite_todas' => true,
                    ])
                    <div class="form-group mr-2 mb-2">
                        <label for="periodo">Periodo (AAAAMM)</label>
                        <input type="number" name="periodo" id="periodo" class="form-control" min="200001" max="299912"
                            value="{{ $periodo > 0 ? $periodo : '' }}" placeholder="ej. 202605">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-control">
                            <option value="">Todos</option>
                            @foreach (['ok' => 'OK', 'observacion' => 'Con observaciones', 'error' => 'Error', 'pendiente' => 'Pendiente'] as $k => $lbl)
                                <option value="{{ $k }}" @selected($estado === $k)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2 mr-2">Filtrar</button>
                </form>

                @if ($puedeSolicitar)
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2">
                            <strong>Solicitud manual</strong>
                        </div>
                        <div class="card-body py-2">
                            <form method="post" action="{{ route('arca_caea_solicitar') }}" class="form-inline flex-wrap align-items-end">
                                @csrf
                                <div class="form-group mr-2 mb-2">
                                    <label for="sol_empresa_id" class="mr-1">Empresa</label>
                                    @include('includes.form-empresa-asignada-control', [
                                        'empresa_query' => $empresas,
                                        'empresa_id' => $empresaId,
                                        'id' => 'sol_empresa_id',
                                        'name' => 'empresa_id',
                                        'required' => true,
                                        'mostrar_opcion_vacia' => false,
                                    ])
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label for="sol_periodo" class="mr-1">Periodo</label>
                                    <input type="number" name="periodo" id="sol_periodo" class="form-control" required
                                        min="200001" max="299912"
                                        value="{{ $quincenasVentana[0]['periodo'] ?? '' }}">
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label for="sol_orden" class="mr-1">Quincena</label>
                                    <select name="orden" id="sol_orden" class="form-control" required>
                                        <option value="1" @selected(($quincenasVentana[0]['orden'] ?? 0) === 1)>1 (día 1 al 15)</option>
                                        <option value="2" @selected(($quincenasVentana[0]['orden'] ?? 0) === 2)>2 (día 16 al fin de mes)</option>
                                    </select>
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-1">
                                        <input type="checkbox" name="solo_consultar" value="1">
                                        Solo consultar en ARCA
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-success mb-2">Pedir / consultar CAEA</button>
                            </form>
                            @if (count($quincenasVentana) > 0)
                                <p class="text-muted small mb-0 mt-2">
                                    Ventana AFIP hoy:
                                    @foreach ($quincenasVentana as $q)
                                        {{ $q['periodo'] }}/Q{{ $q['orden'] }}@if (! $loop->last), @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Periodo / Q</th>
                                <th>CAEA</th>
                                <th>Vigencia</th>
                                <th>Tope informe</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Actualizado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $r)
                                <tr>
                                    <td>{{ $r->empresa->nombre ?? '—' }}</td>
                                    <td>{{ $r->periodo }} / {{ $r->orden }}</td>
                                    <td><code>{{ $r->nro_caea ?? '—' }}</code></td>
                                    <td>
                                        @if ($r->fecha_vigencia_desde && $r->fecha_vigencia_hasta)
                                            {{ $r->fecha_vigencia_desde->format('d/m/Y') }}
                                            —
                                            {{ $r->fecha_vigencia_hasta->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $r->fecha_tope_informe?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        @if ($r->estado === 'ok')
                                            <span class="badge badge-success">OK</span>
                                        @elseif ($r->estado === 'observacion')
                                            <span class="badge badge-warning">Obs.</span>
                                        @elseif ($r->estado === 'error')
                                            <span class="badge badge-danger">Error</span>
                                        @else
                                            <span class="badge badge-secondary">{{ $r->estado }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($r->origen === 'import_anita')
                                            Anita (hist.)
                                        @elseif ($r->origen === 'automatico')
                                            Automático
                                        @elseif ($r->origen === 'manual')
                                            Manual
                                        @else
                                            {{ $r->origen }}
                                        @endif
                                    </td>
                                    <td>{{ $r->updated_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-nowrap">
                                        @if (can('ver-arca-caea', false))
                                            <button type="button"
                                                class="btn-accion-tabla tooltipsC js-arca-caea-ver"
                                                title="Ver detalle"
                                                data-id="{{ $r->id }}"
                                                data-quincena="{{ $r->periodo }}/Q{{ $r->orden }}"
                                                data-empresa="{{ $r->empresa->nombre ?? '' }}">
                                                <i class="fa fa-eye text-info"></i>
                                            </button>
                                        @endif
                                        @if (($puedeGrabarAnita ?? false) && $r->estaAutorizado())
                                            <form method="post"
                                                action="{{ route('arca_caea_grabar_anita', $r->id) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Grabar este CAEA en Anita (Informix)?');">
                                                @csrf
                                                <button type="submit"
                                                    class="btn-accion-tabla tooltipsC"
                                                    title="Grabar en Anita">
                                                    <i class="fa fa-database text-success"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Sin registros CAEA.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $registros->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
