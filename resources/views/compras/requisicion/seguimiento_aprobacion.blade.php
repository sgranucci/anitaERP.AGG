@extends("theme.$theme.layout")
@section('titulo')
Seguimiento aprobación de requisiciones
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/compras/requisicion-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/requisicion-ui.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/requisicion/seguimiento-arbol-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/requisicion/seguimiento-arbol-modal.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $umbralHoras = (int) ($umbral_horas ?? 48);
@endphp
@include('compras.requisicion.partials.modal_arbol_seguimiento')
<div class="row oc-ui rq-ui rq-seguimiento">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="oc-header">
            <h1><i class="fas fa-tasks"></i> Seguimiento de aprobación</h1>
            <div class="oc-header-acciones">
                <a href="{{ route('consultar_requisicion') }}" class="btn btn-outline-info btn-sm" title="Volver al listado de requisiciones">
                    <i class="fa fa-reply-all"></i> Volver al listado
                </a>
            </div>
        </div>

        <div class="oc-panel">
            <p class="oc-intro">
                Requisiciones en circuito de aprobación (en árbol o en compras pendientes de retome).
                Muestra el responsable actual, los días desde la creación y alerta cuando el nivel
                actual supera {{ $umbralHoras }} horas.
                @if (can('usuario-requisicion-resto', false) && ! can('listar-todas-requisicion', false) && ! can('usuario-requisicion-compras', false))
                    Solo las de tu centro de costo (origen o destino del árbol).
                @endif
            </p>

            <div class="oc-resumen">
                <div class="oc-kpi is-pendiente">
                    <div class="oc-kpi-label">Pendientes en circuito</div>
                    <div class="oc-kpi-valor">{{ number_format((int) ($total ?? 0), 0, ',', '.') }}</div>
                </div>
                <div class="oc-kpi {{ ((int) ($con_alerta ?? 0) > 0) ? 'is-alerta' : 'is-mute' }}">
                    <div class="oc-kpi-label">Con alerta (≥ {{ $umbralHoras }} hs)</div>
                    <div class="oc-kpi-valor">{{ number_format((int) ($con_alerta ?? 0), 0, ',', '.') }}</div>
                </div>
                <div class="oc-kpi is-mute">
                    <div class="oc-kpi-label">Umbral de demora</div>
                    <div class="oc-kpi-valor">{{ $umbralHoras }} hs</div>
                </div>
            </div>

            @include('compras.requisicion.partials.filtros_externos', [
                'rutaIndex' => 'seguimiento_aprobacion_requisicion',
            ])

            <div class="table-responsive p-0">
                <table class="table table-hover oc-grilla mb-0" id="tabla-paginada">
                    <thead>
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
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $data)
                            <tr @if (!empty($data->alerta_demora)) class="rq-fila-alerta" @endif>
                                <td>
                                    <span class="oc-numero">{{ $data->numerorequisicion }}</span>
                                </td>
                                <td class="text-nowrap">
                                    @if (!empty($data->fecha))
                                        {{ date('d/m/Y', strtotime($data->fecha)) }}
                                    @endif
                                    @if (!empty($data->fecha_creacion))
                                        <span class="oc-meta">{{ $data->fecha_creacion->format('d/m/Y H:i') }}</span>
                                    @endif
                                </td>
                                <td>{{ $data->nombreempresa }}</td>
                                <td>{{ $data->nombrecentrocosto }}</td>
                                <td>{{ $data->nombresolicitante }}</td>
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
                                <td>{{ $data->responsable_etiqueta }}</td>
                                <td class="oc-num">{{ (int) ($data->dias_desde_creacion ?? 0) }}</td>
                                <td class="oc-num">{{ (int) ($data->horas_en_nivel ?? 0) }}</td>
                                <td class="text-center">
                                    @if (!empty($data->alerta_demora))
                                        <span class="oc-pill oc-pill-suspendida" title="Supera {{ $umbralHoras }} horas en el nivel actual">
                                            Demora
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="oc-acciones">
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
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="oc-vacio">
                                    <i class="fa fa-inbox"></i>
                                    <div class="oc-vacio-titulo">No hay requisiciones pendientes de aprobación</div>
                                    Ajuste la empresa o el umbral de seguimiento.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($filas, 'links'))
            <div class="oc-footer">
                @if (method_exists($filas, 'firstItem') && $filas->firstItem())
                    <small class="text-muted mr-2">Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</small>
                @endif
                {{ $filas->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
