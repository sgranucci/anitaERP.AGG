@extends("theme.$theme.layout")
@section('titulo')
Seguimiento aprobación de requisiciones
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/requisicion/seguimiento-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/seguimiento-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $umbralHoras = (int) ($umbral_horas ?? 48);
@endphp
@include('compras.requisicion.partials.modal_arbol_seguimiento')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-tasks"></i> Seguimiento de aprobación
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm" title="Volver al listado de requisiciones">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            @include('compras.requisicion.partials.filtros_externos', [
                'rutaIndex' => 'seguimiento_aprobacion_requisicion',
            ])
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Requisiciones en circuito de aprobación (en árbol o en compras pendientes de retome).
                    Muestra el responsable actual, los días desde la creación y alerta cuando el nivel
                    actual supera {{ $umbralHoras }} horas.
                </p>

                <div class="row mb-3">
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Pendientes en circuito</div>
                            <div class="h4 mb-0">{{ number_format((int) ($total ?? 0), 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 {{ ((int) ($con_alerta ?? 0) > 0) ? 'border-danger' : 'bg-light' }}"
                             @if ((int) ($con_alerta ?? 0) > 0) style="background:#fdecea;" @endif>
                            <div class="text-muted small">Con alerta (&ge; {{ $umbralHoras }} hs)</div>
                            <div class="h4 mb-0 {{ ((int) ($con_alerta ?? 0) > 0) ? 'text-danger' : '' }}">
                                {{ number_format((int) ($con_alerta ?? 0), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">Umbral de demora</div>
                            <div class="h4 mb-0">{{ $umbralHoras }} hs</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Número</th>
                                <th>Fecha / creación</th>
                                <th>Empresa</th>
                                <th>Centro costo</th>
                                <th>Solicitante</th>
                                <th>Estado</th>
                                <th>Nivel</th>
                                <th>Responsable actual</th>
                                <th class="text-right">Días</th>
                                <th class="text-right">Hs en nivel</th>
                                <th>Alerta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $data)
                                <tr @if (!empty($data->alerta_demora)) class="table-danger" @endif>
                                    <td>{{ $data->numerorequisicion }}</td>
                                    <td>
                                        @if (!empty($data->fecha))
                                            {{ date('d/m/Y', strtotime($data->fecha)) }}
                                        @endif
                                        @if (!empty($data->fecha_creacion))
                                            <br><small class="text-muted">{{ $data->fecha_creacion->format('d/m/Y H:i') }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $data->nombreempresa }}</td>
                                    <td><small>{{ $data->nombrecentrocosto }}</small></td>
                                    <td><small>{{ $data->nombresolicitante }}</small></td>
                                    <td>
                                        @include('compras.requisicion.partials.estado_badge', ['estado' => $data->estado ?? ''])
                                    </td>
                                    <td class="text-center">
                                        @if ($data->nivel_actual !== null)
                                            {{ $data->nivel_actual }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $data->responsable_etiqueta }}</small>
                                    </td>
                                    <td class="text-right">{{ (int) ($data->dias_desde_creacion ?? 0) }}</td>
                                    <td class="text-right">{{ (int) ($data->horas_en_nivel ?? 0) }}</td>
                                    <td class="text-center">
                                        @if (!empty($data->alerta_demora))
                                            <span class="badge badge-danger" title="Supera {{ $umbralHoras }} horas en el nivel actual">
                                                <i class="fa fa-exclamation-triangle"></i> Demora
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="#"
                                           class="btn-accion-tabla tooltipsC text-info js-requisicion-ver-arbol"
                                           title="Ver árbol de aprobación"
                                           data-id="{{ $data->id }}"
                                           data-numero="{{ $data->numerorequisicion }}">
                                            <i class="fa fa-sitemap"></i>
                                        </a>
                                        @if (can('editar-requisicion', false))
                                            <a href="{{ route('editar_requisicion', $data->id) }}"
                                               class="btn-accion-tabla tooltipsC text-primary"
                                               target="_blank" rel="noopener"
                                               title="Abrir requisición">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @elseif (can('listar-requisicion', false))
                                            <a href="{{ route('editar_requisicion', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               class="btn-accion-tabla tooltipsC text-primary"
                                               target="_blank" rel="noopener"
                                               title="Consultar requisición">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        No hay requisiciones pendientes de aprobación con los filtros actuales.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (method_exists($filas, 'links'))
                    <div class="d-flex justify-content-between align-items-center flex-wrap mt-2 px-1">
                        <small class="text-muted">
                            @if (method_exists($filas, 'firstItem') && $filas->firstItem())
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            @endif
                        </small>
                        <div>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
