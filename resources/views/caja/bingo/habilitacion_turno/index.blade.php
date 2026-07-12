@extends("theme.$theme.layout")
@section('titulo')
    Habilitación y cierres de turno bingo
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/bingo/habilitacion_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/bingo/habilitacion_turno.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="habilitacion-turno-bingo-app"
     data-api-estado="{{ route('bingo_habilitacion_turno_api_estado') }}"
     data-api-habilitar="{{ route('bingo_habilitacion_turno_api_habilitar') }}"
     data-api-cierre-parcial="{{ route('bingo_habilitacion_turno_api_cierre_parcial') }}"
     data-api-cerrar="{{ route('bingo_habilitacion_turno_api_cerrar') }}"
     data-csrf="{{ csrf_token() }}"
     data-empresa-id="{{ (int) ($empresa_id ?? 0) }}"
     data-puede-habilitar="{{ ($puede_habilitar ?? false) ? '1' : '0' }}"
     data-puede-cierre-parcial="{{ ($puede_cierre_parcial ?? false) ? '1' : '0' }}"
     data-puede-cerrar="{{ ($puede_cerrar ?? false) ? '1' : '0' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if ($modo_caja_directo ?? false)
            <div class="alert alert-info">
                El sistema está en modo <strong>caja directo</strong>
                (<code>BINGO_REQUIERE_HABILITACION_TURNO=false</code>).
                No se utiliza habilitación ni cierre de turno por terminal.
            </div>
        @elseif (! $cfg)
            @include('caja.bingo.habilitacion_turno.partials.filtro_empresa', [
                'empresa_query' => $empresa_query ?? collect(),
                'empresas_sin_pv' => $empresas_sin_pv ?? collect(),
                'configs_pv_asignadas' => $configs_pv_asignadas ?? collect(),
                'empresa_id' => $empresa_id ?? 0,
                'identificador_pc' => $identificador_pc,
            ])
        @else
            @include('caja.bingo.habilitacion_turno.partials.filtro_empresa', [
                'empresa_query' => $empresa_query ?? collect(),
                'empresas_sin_pv' => $empresas_sin_pv ?? collect(),
                'configs_pv_asignadas' => $configs_pv_asignadas ?? collect(),
                'empresa_id' => $empresa_id ?? 0,
                'identificador_pc' => $identificador_pc,
            ])

            <div class="card card-info mb-3">
                <div class="card-header">
                    <h3 class="card-title">Terminal <code>{{ $identificador_pc }}</code>
                        @if ($cfg->descripcion)
                            — {{ $cfg->descripcion }}
                        @endif
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('bingo_jornada', ['empresa_id' => $empresa_id]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-calendar"></i> Jornada
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @php $est = $estado ?? []; @endphp

                    @if (empty($est['jornada_abierta']))
                        <div class="alert alert-warning mb-3">
                            No hay jornada abierta para esta empresa.
                            <a href="{{ route('bingo_jornada', ['empresa_id' => $empresa_id]) }}">Abrir jornada</a>
                        </div>
                    @endif

                    @if (! empty($est['turno_habilitado']))
                        <div class="alert alert-success">
                            <strong>Turno habilitado:</strong> {{ $est['turno_nombre'] ?? '—' }}
                            @if (! empty($est['usuario_habilitado']))
                                · {{ $est['usuario_habilitado'] }}
                            @endif
                            @if (! empty($est['habilitacion_en_fmt']))
                                · desde {{ $est['habilitacion_en_fmt'] }}
                            @endif
                            @if (isset($est['monto_habilitacion']))
                                · Habilitación: ${{ number_format((float) $est['monto_habilitacion'], 2, ',', '.') }}
                            @endif
                            <br>
                            <span class="small text-muted">Fecha jornada: {{ $est['fecha_jornada_fmt'] ?? $est['fecha_jornada'] ?? '—' }}</span>
                        </div>

                        @if ($puede_cierre_parcial ?? false)
                            <button type="button" class="btn btn-warning btn-sm mb-2" id="btn-cierre-parcial-bingo">
                                <i class="fa fa-file-text-o"></i> Cierre parcial
                            </button>
                        @endif

                        @if ($puede_cerrar ?? false)
                            <div class="border rounded p-3 mt-2 bg-light">
                                <p class="mb-2">
                                    <a href="{{ route('bingo_rendicion_cargar', ['empresa_id' => $empresa_id]) }}" class="btn btn-primary btn-sm">
                                        <i class="fa fa-table"></i> Cargar rendición (cartones + conceptos)
                                    </a>
                                </p>
                                <h5 class="mb-3">Cierre rápido (montos manuales)</h5>
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="cierre_monto_rendicion">Monto rendición</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="cierre_monto_rendicion"
                                               value="{{ (float) ($est['totales_turno']['total_general'] ?? 0) }}">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="cierre_medio_efectivo">Efectivo</label>
                                        <input type="number" step="0.01" class="form-control" id="cierre_medio_efectivo"
                                               value="{{ (float) ($est['totales_turno']['total_general'] ?? 0) }}">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="cierre_redondeo">Redondeo</label>
                                        <input type="number" step="0.01" class="form-control" id="cierre_redondeo" value="0">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="cierre_sobrante">Sobrante / faltante</label>
                                        <input type="number" step="0.01" class="form-control" id="cierre_sobrante" value="0">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cierre_observacion">Observación</label>
                                    <textarea class="form-control" id="cierre_observacion" rows="2"></textarea>
                                </div>
                                <button type="button" class="btn btn-danger" id="btn-cerrar-turno-bingo">
                                    <i class="fa fa-lock"></i> Cerrar turno
                                </button>
                            </div>
                        @endif
                    @elseif (! empty($est['jornada_abierta']))
                        @if (! empty($est['errores_habilitacion']))
                            <div class="alert alert-danger">
                                @foreach ($est['errores_habilitacion'] as $err)
                                    <div>{{ $err }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if ($puede_habilitar ?? false)
                            <div class="border rounded p-3 bg-light">
                                <h5 class="mb-3">Habilitar turno</h5>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="hab_turno_bingo_id" class="requerido">Turno</label>
                                        <select id="hab_turno_bingo_id" class="form-control">
                                            <option value="">Seleccione…</option>
                                            @foreach ($turnos as $t)
                                                @php
                                                    $habilitables = $est['turnos_bingo_habilitables_ids'] ?? [];
                                                    $cerrados = $est['turnos_bingo_cerrados_ids'] ?? [];
                                                    $disabled = ! in_array((int) $t->id, $habilitables, true);
                                                @endphp
                                                <option value="{{ $t->id }}" {{ $disabled ? 'disabled' : '' }}>
                                                    {{ $t->nombre }}
                                                    @if (in_array((int) $t->id, $cerrados, true))
                                                        (cerrado hoy)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="hab_monto">Monto habilitación</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="hab_monto" value="0">
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label for="usuario_habilitado_id" class="requerido">Usuario habilitado</label>
                                        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                            <input type="text" style="flex: 0 0 110px; width: 110px; height: 38px;" class="usuario_id form-control" id="usuario_habilitado_id" value="" placeholder="ID usuario" title="ID numérico del usuario; Enter para validar" autocomplete="off" inputmode="numeric"/>
                                            <button type="button" title="Consulta usuarios" style="padding: 1px; flex: 0 0 auto;" class="btn-accion-tabla consultausuario tooltipsC"
                                                data-ptrusuario_id="#usuario_habilitado_id" data-ptrnombre="#nombre_usuario_habilitado">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px;" class="nombreusuario form-control" id="nombre_usuario_habilitado" value="" placeholder="Nombre usuario" readonly/>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="hab_observacion">Observación</label>
                                    <input type="text" class="form-control" id="hab_observacion" maxlength="500">
                                </div>
                                <button type="button" class="btn btn-success" id="btn-habilitar-turno-bingo"
                                        @if (empty($est['puede_habilitar'])) disabled @endif>
                                    <i class="fa fa-play"></i> Habilitar turno
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.admin.modalconsultausuario')
@endsection
