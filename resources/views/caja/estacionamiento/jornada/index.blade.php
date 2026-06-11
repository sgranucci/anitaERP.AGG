@extends("theme.$theme.layout")
@section('titulo')
    Jornada estacionamiento
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/jornada.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/estacionamiento/jornada.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="jornada-estacionamiento-app"
     data-api-estado="{{ url('caja/estacionamiento/jornada/api/estado') }}"
     data-api-abrir="{{ url('caja/estacionamiento/jornada/api/abrir') }}"
     data-api-cerrar="{{ url('caja/estacionamiento/jornada/api/cerrar') }}"
     data-api-eliminar="{{ url('caja/estacionamiento/jornada/api/eliminar') }}"
     data-api-anular-cierre="{{ route('estacionamiento_jornada_api_anular_cierre') }}"
     data-csrf="{{ csrf_token() }}"
     data-puede-abrir="{{ $puede_abrir ? '1' : '0' }}"
     data-puede-cerrar="{{ $puede_cerrar ? '1' : '0' }}"
     data-puede-eliminar="{{ ($puede_eliminar ?? false) ? '1' : '0' }}"
     data-puede-anular-cierre="{{ ($puede_anular_cierre ?? false) ? '1' : '0' }}"
     data-cierre-anulable='@json($cierre_anulable ?? null)'
     data-jornada-abierta="{{ ! empty($estado['jornada_abierta']) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Apertura y cierre de jornada — Estacionamiento</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    La <strong>fecha de factura</strong> de cada comprobante es siempre el día calendario real.
                    La <strong>fecha de jornada</strong> es la del turno abierto y se grabará en
                    <code>venta.fechajornada</code> para la facturación de estacionamiento.
                </p>

                <form method="get" action="{{ url('caja/estacionamiento/jornada') }}" class="form-inline mb-4" id="form-empresa-jornada">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'required' => true,
                        'permite_todas' => false,
                    ])
                </form>

                @if ($estado)
                    <div id="jornada-estado-panel" class="mb-4">
                        @if ($estado['jornada_abierta'])
                            <div class="alert alert-success">
                                <strong>Jornada abierta</strong>
                                — Fecha jornada:
                                <span id="lbl-fecha-jornada">{{ $estado['fecha_jornada_fmt'] ?? $estado['fecha_jornada'] }}</span>
                                · Facturas de hoy usan fecha
                                <span id="lbl-fecha-factura">{{ $estado['fecha_factura_hoy_fmt'] ?? $estado['fecha_factura_hoy'] }}</span>
                                @if (! empty($estado['usuario_apertura']))
                                    <br>Abierta por {{ $estado['usuario_apertura'] }}
                                    @if (! empty($estado['apertura_en']))
                                        ({{ $estado['apertura_en'] }})
                                    @endif
                                @endif
                                @if (! empty($estado['observacion_apertura']))
                                    <br><em>{{ $estado['observacion_apertura'] }}</em>
                                @endif
                                @if ($puede_eliminar ?? false)
                                    @php $elimAbierta = $eliminacion_jornada_abierta ?? null; @endphp
                                    @if (! empty($elimAbierta['puede_eliminar']))
                                        <div class="mt-2">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm js-eliminar-jornada"
                                                    data-jornada-id="{{ (int) $estado['jornada_id'] }}"
                                                    data-fecha-jornada="{{ $estado['fecha_jornada_fmt'] ?? $estado['fecha_jornada'] }}"
                                                    title="Eliminar apertura errónea (sin movimientos)">
                                                <i class="fa fa-trash"></i> Borrar apertura
                                            </button>
                                            <span class="small text-muted ml-1">Solo si no tiene comprobantes ni turnos habilitados.</span>
                                        </div>
                                    @elseif (! empty($elimAbierta['motivo_no_eliminar']))
                                        <p class="small mb-0 mt-2 text-muted">{{ $elimAbierta['motivo_no_eliminar'] }}</p>
                                    @endif
                                @endif
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <strong>Sin jornada abierta</strong> para esta empresa.
                                La facturación de estacionamiento no podrá emitir comprobantes hasta abrir la jornada.
                            </div>
                        @endif
                    </div>

                    <div class="row mb-4">
                        @if ($puede_abrir)
                            <div class="col-md-6">
                                <div class="card card-outline card-success @if ($estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Abrir jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['motivo_no_puede_abrir']))
                                            <p class="text-muted small mb-2">{{ $estado['motivo_no_puede_abrir'] }}</p>
                                        @endif
                                        <div class="form-group">
                                            <label for="fecha_jornada_abrir">Fecha de jornada (turno)</label>
                                            <input type="date" class="form-control" id="fecha_jornada_abrir"
                                                   value="{{ $fecha_hoy }}"
                                                   @if (! empty($fecha_jornada_minima)) min="{{ $fecha_jornada_minima }}" @endif
                                                   max="{{ $fecha_maxima ?? ($estado['fecha_factura_hoy'] ?? now()->format('Y-m-d')) }}"
                                                   @disabled($estado['jornada_abierta'])>
                                        </div>
                                        <div class="form-group">
                                            <label for="observacion_abrir">Observación</label>
                                            <textarea class="form-control" id="observacion_abrir" rows="2"
                                                      @disabled($estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-success" id="btn-abrir-jornada"
                                                @disabled($estado['jornada_abierta'])>
                                            Abrir jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($puede_cerrar)
                            <div class="col-md-6">
                                <div class="card card-outline card-danger @if (! $estado['jornada_abierta']) opacity-50 @endif">
                                    <div class="card-header">Cerrar jornada</div>
                                    <div class="card-body">
                                        @if (! empty($estado['errores_cierre']))
                                            @php
                                                $mostrarLinkSaneamiento = false;
                                                foreach ($estado['errores_cierre'] as $err) {
                                                    if (stripos($err, 'Saneamiento') !== false) {
                                                        $mostrarLinkSaneamiento = true;
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            <ul class="text-danger small" id="lista-errores-cierre">
                                                @foreach ($estado['errores_cierre'] as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                            @if ($mostrarLinkSaneamiento)
                                                <div class="mb-2">
                                                    <a href="{{ $url_saneamiento_turno ?? url('caja/estacionamiento/saneamiento-turno') }}?empresa_id={{ $empresa_id }}"
                                                       class="btn btn-warning btn-sm"
                                                       target="_blank" rel="noopener">
                                                        <i class="fa fa-external-link"></i>
                                                        Ir a Saneamiento de turnos
                                                    </a>
                                                </div>
                                            @endif
                                        @endif
                                        @if (! empty($estado['nota_politica_turnos']))
                                            <p class="text-muted small mb-2">{{ $estado['nota_politica_turnos'] }}</p>
                                        @endif
                                        @if (! empty($estado['turnos_habilitados']))
                                            <div class="small mb-2">
                                                <strong>Turnos habilitados sin cerrar:</strong>
                                                <ul class="mb-1 pl-3">
                                                    @foreach ($estado['turnos_habilitados'] as $th)
                                                        <li>
                                                            {{ $th['identificador_pc'] }} — {{ $th['turno_nombre'] }}
                                                            <span class="badge badge-danger">sin cerrar</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <a href="{{ $url_saneamiento_turno ?? url('caja/estacionamiento/saneamiento-turno') }}?empresa_id={{ $empresa_id }}"
                                                   class="btn btn-outline-warning btn-sm" target="_blank" rel="noopener">
                                                    Cierre remoto de turnos
                                                </a>
                                            </div>
                                        @endif
                                        @if (! empty($estado['cuentas_abiertas_vacias']))
                                            <div class="alert alert-info small py-2 mb-2">
                                                {{ $estado['cuentas_abiertas_vacias'] }} cuenta(s) abierta(s) sin ítems se
                                                <strong>descartarán automáticamente</strong> al cerrar la jornada
                                                (no requieren saneamiento).
                                            </div>
                                        @endif
                                        <div class="form-group">
                                            <label for="observacion_cerrar">Observación de cierre</label>
                                            <textarea class="form-control" id="observacion_cerrar" rows="2"
                                                      @disabled(! $estado['jornada_abierta'])></textarea>
                                        </div>
                                        <button type="button" class="btn btn-danger" id="btn-cerrar-jornada"
                                                @disabled(! $estado['jornada_abierta'] || ! empty($estado['errores_cierre']))>
                                            Cerrar jornada
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Historial de jornadas</h5>
                    @if (($puede_anular_cierre ?? false) && ! empty($cierre_anulable))
                        <button type="button" class="btn btn-danger btn-sm" id="btn-abrir-anular-cierre-jornada"
                                title="Reabrir la última jornada cerrada">
                            <i class="fa fa-undo"></i> Anular último cierre de jornada
                        </button>
                    @endif
                </div>
                @if ($puede_anular_cierre ?? false)
                    <p class="text-muted small mb-2">
                        Si cerró por error, puede anular el cierre y dejar la jornada abierta de nuevo
                        (solo la última jornada cerrada, sin jornada posterior).
                    </p>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha jornada</th>
                                <th>Estado</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Usuario apertura</th>
                                <th>Usuario cierre</th>
                                @if (($puede_eliminar ?? false) || ($puede_anular_cierre ?? false))
                                    <th>Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($historial as $j)
                                @php
                                    $elim = $eliminacion_por_jornada[$j->id] ?? null;
                                    $anular = $anulacion_cierre_por_jornada[$j->id] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $j->id }}</td>
                                    <td>{{ $j->fecha_jornada->format('d/m/Y') }}</td>
                                    <td>{{ ucfirst($j->estado) }}</td>
                                    <td>{{ $j->apertura_en?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $j->cierre_en?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $j->usuarioApertura->nombre ?? '—' }}</td>
                                    <td>{{ $j->usuarioCierre->nombre ?? '—' }}</td>
                                    @if (($puede_eliminar ?? false) || ($puede_anular_cierre ?? false))
                                        <td class="text-nowrap">
                                            @if (($puede_anular_cierre ?? false) && ! empty($anular['puede_anular']))
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-xs js-anular-cierre-jornada mr-1"
                                                        data-jornada-id="{{ $j->id }}"
                                                        data-fecha-jornada="{{ $j->fecha_jornada->format('d/m/Y') }}"
                                                        data-cierre-en="{{ $j->cierre_en?->format('d/m/Y H:i') ?? '' }}"
                                                        data-usuario-cierre="{{ $j->usuarioCierre->nombre ?? '' }}"
                                                        data-texto-confirmacion="{{ $anular['texto_confirmacion'] ?? '' }}"
                                                        title="Anular cierre y reabrir jornada">
                                                    <i class="fa fa-undo"></i>
                                                </button>
                                            @endif
                                            @if ($puede_eliminar ?? false)
                                                @if (! empty($elim['puede_eliminar']))
                                                    <button type="button"
                                                            class="btn btn-outline-secondary btn-xs js-eliminar-jornada"
                                                            data-jornada-id="{{ $j->id }}"
                                                            data-fecha-jornada="{{ $j->fecha_jornada->format('d/m/Y') }}"
                                                            title="Eliminar jornada sin movimientos">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                @elseif (empty($anular['puede_anular']))
                                                    <span class="text-muted small"
                                                          title="{{ $elim['motivo_no_eliminar'] ?? 'Tiene movimientos' }}">—</span>
                                                @endif
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 7 + ((($puede_eliminar ?? false) || ($puede_anular_cierre ?? false)) ? 1 : 0) }}" class="text-center text-muted">Sin registros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($puede_anular_cierre ?? false)
<div class="modal fade" id="modal-anular-cierre-jornada" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-danger text-white">
                <h6 class="modal-title"><i class="fa fa-undo"></i> Anular cierre de jornada</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <form id="form-anular-cierre-jornada" autocomplete="off">
                <div class="modal-body py-3">
                    <p class="text-muted small mb-2">
                        La jornada volverá a estado <strong>abierta</strong>.
                        No borra comprobantes emitidos. Queda registrado en el log del sistema.
                    </p>
                    <div id="anular-cierre-jornada-detalle" class="mb-3"></div>
                    <div class="form-group mb-2">
                        <label for="motivo_anular_cierre_jornada">Motivo (obligatorio)</label>
                        <textarea id="motivo_anular_cierre_jornada" class="form-control" rows="2"
                                  maxlength="500" required placeholder="Ej.: cierre por error antes de terminar el turno"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label for="confirmacion_anular_cierre_jornada" class="requerido">Confirmación</label>
                        <input type="text" class="form-control" id="confirmacion_anular_cierre_jornada"
                               autocomplete="off" required/>
                        <small class="form-text text-muted" id="hint-confirmacion-anular-cierre-jornada"></small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger" id="btn-submit-anular-cierre-jornada">
                        <i class="fa fa-undo"></i> Anular cierre y reabrir jornada
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
