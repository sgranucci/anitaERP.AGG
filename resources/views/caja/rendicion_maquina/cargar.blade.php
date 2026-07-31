@extends("theme.$theme.layout")
@section('titulo')
    {{ ! empty($modo_edicion) ? 'Editar rendición de máquinas' : 'Nueva rendición de máquinas' }}
@endsection

@section("scripts")
<style>
    .rendmaq-workbench { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 991px) { .rendmaq-workbench { grid-template-columns: 1fr; } }
    .rendmaq-panel thead th,
    #modal-log-ajustes-wigos thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .rendmaq-panel tbody td { vertical-align: middle; }
    .rendmaq-panel .col-codigo { width: 4.5rem; }
    .rendmaq-panel .col-monto { width: 11rem; min-width: 10rem; }
    .rendmaq-panel .col-desc {
        max-width: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .rendmaq-panel .js-valor-monto,
    .rendmaq-panel .js-gasto-monto {
        min-width: 9rem;
        font-weight: 600;
    }
    .rendmaq-cabecera-meta .form-group { margin-bottom: 0.75rem; }
    .rendmaq-sticky-footer {
        position: sticky;
        bottom: 0;
        z-index: 100;
        background: linear-gradient(180deg, #f4f9fc 0%, #eaf2f8 100%);
        border-top: 2px solid #85C1E9;
        box-shadow: 0 -4px 12px rgba(23, 32, 42, 0.06);
        padding: 0.85rem 1rem;
        margin: 0 -1.25rem -1.25rem;
    }
    .rendmaq-sticky-footer .totales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.65rem 1rem;
    }
    .rendmaq-sticky-footer .tot-item {
        background: #fff;
        border: 1px solid #d6eaf8;
        border-radius: 4px;
        padding: 0.45rem 0.6rem;
    }
    .rendmaq-sticky-footer .tot-item.is-destacado {
        border-color: #5dade2;
        background: #ebf5fb;
    }
    .rendmaq-sticky-footer .lbl { font-size: 0.75rem; color: #566573; display: block; }
    .rendmaq-sticky-footer .val { font-weight: 700; font-size: 1.05rem; color: #17202A; }
    .input-wigos-ajustable { background-color: #fffde7 !important; }
    .rendmaq-hint {
        font-size: 0.8rem;
        color: #5d6d7e;
        margin: 0;
    }
    /* Botones visibles sobre barra card-info (fondo celeste) */
    .rendmaq-card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .rendmaq-toolbar {
        margin-left: auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
    }
    .rendmaq-toolbar .btn-rendmaq-secondary {
        background: rgba(255, 255, 255, 0.92);
        border-color: rgba(255, 255, 255, 0.92);
        color: #1a5276;
    }
    .rendmaq-toolbar .btn-rendmaq-secondary:hover {
        background: #fff;
        color: #154360;
    }
    .rendmaq-toolbar .btn-rendmaq-wigos {
        background: #f4d03f;
        border-color: #f4d03f;
        color: #1c2833;
        font-weight: 600;
    }
    .rendmaq-toolbar .btn-rendmaq-wigos:hover {
        background: #f7dc6f;
        border-color: #f7dc6f;
        color: #1c2833;
    }
</style>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_maquina/cargar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/rendicion_maquina/cargar.js')) }}"></script>
@endsection

@section('contenido')
@php
    $d = $datos ?? [];
    $rendicion = $d['rendicion'] ?? null;
    $cuentasValor = $d['cuentas_valor'] ?? [];
    $gastosLineas = $d['gastos'] ?? [];
    $inputs = $d['inputs'] ?? [];
    $calcOrq = $d['calc_orquestador'] ?? ['comprobante' => 0, 'vale_rep_fondo' => 0];
    $totales = $d['totales'] ?? [];
    $usuarios = $d['usuarios'] ?? collect();
    $camposWigos = $d['campos_wigos_ajustables'] ?? [];
    $camposManuales = $d['campos_manuales'] ?? [];
    $retornoListadoQuery = $filtrosQuery ?? [];
    $turnoActual = (string) ($turno ?? 'M');
    $badgeTurno = match ($turnoActual) {
        'C' => 'badge-warning',
        'N' => 'badge-dark',
        'T' => 'badge-info',
        default => 'badge-primary',
    };
    $modoEdicion = ! empty($modo_edicion);
@endphp
<div class="row" id="rendicion-maquina-app"
     data-api-calcular="{{ route('rendicion_maquina_api_calcular') }}"
     data-api-guardar="{{ route('rendicion_maquina_api_guardar') }}"
     data-api-traer-wigos="{{ route('rendicion_maquina_api_traer_wigos') }}"
     data-api-lineas-empresa="{{ route('rendicion_maquina_api_lineas_empresa') }}"
     data-api-ajustes="{{ route('rendicion_maquina_api_ajustes') }}"
     data-csrf="{{ csrf_token() }}"
     data-rendicion-id="{{ (int) ($rendicion_id ?? 0) }}"
     data-empresa-id="{{ (int) $empresa_id }}"
     data-fecha="{{ $fecha ?? date('Y-m-d') }}"
     data-turno="{{ $turnoActual }}"
     data-modo-edicion="{{ $modoEdicion ? '1' : '0' }}"
     data-puede-ajustar="{{ ! empty($puede_ajustar_wigos) ? '1' : '0' }}"
     data-url-index="{{ route('rendicion_maquina', $retornoListadoQuery) }}">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info mb-3">
            <div class="card-header rendmaq-card-header">
                <h3 class="card-title mb-0">
                    @if ($modoEdicion)
                        Editar rendici&oacute;n #{{ (int) ($rendicion_id ?? 0) }}
                    @else
                        Nueva rendici&oacute;n de m&aacute;quinas
                    @endif
                    <span class="badge {{ $badgeTurno }} ml-2">Turno {{ $turnoActual }}</span>
                </h3>
                <div class="rendmaq-toolbar">
                    <a href="{{ route('rendicion_maquina', $retornoListadoQuery) }}" class="btn btn-sm btn-rendmaq-secondary">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @if (! empty($puede_ver_log_wigos))
                        <button type="button" class="btn btn-sm btn-rendmaq-secondary" id="btn-ver-log-ajustes">
                            <i class="fa fa-history"></i> Log ajustes
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-rendmaq-wigos" id="btn-traer-wigos">
                        <i class="fa fa-cloud-download"></i> Traer WIGOS
                    </button>
                    @if ($modoEdicion && can('imprimir-rendicion-maquina', false))
                        <a href="{{ route('imprimir_rendicion_maquina', ['id' => (int) $rendicion_id, 'inline' => 1]) }}"
                           target="_blank" rel="noopener"
                           class="btn btn-sm btn-rendmaq-secondary"
                           title="Imprimir comprobante PDF">
                            <i class="fa fa-print"></i> PDF
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body pb-0">
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-2"><strong>Identificaci&oacute;n</strong></div>
                    <div class="card-body py-3 rendmaq-cabecera-meta">
                        <div class="form-row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="empresa_id" class="requerido">Empresa</label>
                                    @include('includes.form-empresa-asignada-control', [
                                        'empresa_query' => $empresa_query,
                                        'empresa_id' => $empresa_id,
                                        'solo_lectura' => $modoEdicion,
                                        'required' => true,
                                        'id' => 'empresa_id',
                                        'name' => 'empresa_id',
                                        'permite_vacio' => ! $modoEdicion,
                                        'opcion_vacia' => '— Seleccionar —',
                                    ])
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="fecha_rendicion">Fecha</label>
                                    <input type="date" id="fecha_rendicion" class="form-control"
                                           value="{{ $fecha ?? date('Y-m-d') }}"
                                           @if($modoEdicion) readonly @endif>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="turno_rendicion">Turno</label>
                                    <select id="turno_rendicion" class="form-control"
                                            @if($modoEdicion) disabled @endif>
                                        @foreach ($d['turnos'] ?? [] as $t)
                                            <option value="{{ $t['valor'] }}" {{ $turnoActual === $t['valor'] ? 'selected' : '' }}>{{ $t['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    @if ($turnoActual === 'C')
                                        <small class="text-muted">Cierre de jornada: drop del d&iacute;a de la fecha.</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rendmaq-workbench mb-3">
                    <div>
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2"><strong>Personal</strong></div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="supervisor_usuario_id">Supervisor</label>
                                        <select id="supervisor_usuario_id" class="form-control form-control-sm">
                                            <option value="">—</option>
                                            @foreach ($usuarios as $u)
                                                <option value="{{ $u->id }}" {{ (int) ($rendicion->supervisor_usuario_id ?? 0) === (int) $u->id ? 'selected' : '' }}>{{ $u->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="auxiliar_usuario_id">Auxiliar</label>
                                        <select id="auxiliar_usuario_id" class="form-control form-control-sm">
                                            <option value="">—</option>
                                            @foreach ($usuarios as $u)
                                                <option value="{{ $u->id }}" {{ (int) ($rendicion->auxiliar_usuario_id ?? 0) === (int) $u->id ? 'selected' : '' }}>{{ $u->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="cajero_usuario_id">Cajero</label>
                                        <select id="cajero_usuario_id" class="form-control form-control-sm">
                                            <option value="">—</option>
                                            @foreach ($usuarios as $u)
                                                <option value="{{ $u->id }}" {{ (int) ($rendicion->cajero_usuario_id ?? 0) === (int) $u->id ? 'selected' : '' }}>{{ $u->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="observacion_rendicion">Observaci&oacute;n</label>
                                    <textarea id="observacion_rendicion" class="form-control form-control-sm" rows="2" maxlength="500">{{ $rendicion->observacion ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card card-outline card-info">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                                <strong>Datos WIGOS / manuales</strong>
                                @if (! empty($puede_ajustar_wigos))
                                    <span class="badge badge-warning">Campos ajustables en amarillo</span>
                                @endif
                            </div>
                            <div class="card-body p-2">
                                <p class="rendmaq-hint mb-2 px-1">
                                    Traiga WIGOS y complete montos. Los ajustes quedan en el log si tiene permiso.
                                </p>
                                <div class="form-row">
                                    @foreach ($camposWigos as $campoRuta => $etiqueta)
                                        @php
                                            $clave = str_starts_with($campoRuta, 'inputs.') ? substr($campoRuta, 7) : $campoRuta;
                                            $valorInput = $inputs[$clave] ?? $inputs[$campoRuta] ?? 0;
                                        @endphp
                                        <div class="form-group col-md-6 col-lg-4 mb-2">
                                            <label class="small mb-0" for="input_{{ $clave }}">{{ $etiqueta }}</label>
                                            <input type="text" inputmode="decimal"
                                                   id="input_{{ $clave }}"
                                                   class="form-control form-control-sm js-input-wigos js-monto-ar text-right"
                                                   data-campo="{{ $campoRuta }}"
                                                   data-clave="{{ $clave }}"
                                                   autocomplete="off"
                                                   value="{{ number_format((float) $valorInput, 2, ',', '.') }}">
                                        </div>
                                    @endforeach
                                    @foreach ($camposManuales as $claveManual)
                                        @php
                                            if ($claveManual === 'impuestos') {
                                                continue;
                                            }
                                            $valorManual = $inputs[$claveManual] ?? 0;
                                        @endphp
                                        <div class="form-group col-md-6 col-lg-4 mb-2">
                                            <label class="small mb-0" for="input_{{ $claveManual }}">{{ ucfirst(str_replace('_', ' ', $claveManual)) }}</label>
                                            <input type="text" inputmode="decimal"
                                                   id="input_{{ $claveManual }}"
                                                   class="form-control form-control-sm js-input-manual js-monto-ar text-right"
                                                   data-clave="{{ $claveManual }}"
                                                   autocomplete="off"
                                                   value="{{ number_format((float) $valorManual, 2, ',', '.') }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2"><strong>Valores (cuentas de caja)</strong></div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered mb-0 rendmaq-panel" id="tabla-valores-rendicion">
                                    <thead>
                                        <tr>
                                            <th class="col-codigo">C&oacute;d.</th>
                                            <th>Cuenta</th>
                                            <th class="text-right col-monto">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($cuentasValor as $linea)
                                            <tr data-cuentacaja-id="{{ (int) $linea['cuentacaja_id'] }}">
                                                <td class="text-muted col-codigo">{{ $linea['codigo'] ?? '' }}</td>
                                                <td class="col-desc" title="{{ $linea['nombre'] ?? '' }}">{{ $linea['nombre'] ?? '' }}</td>
                                                <td class="col-monto">
                                                    <input type="text" inputmode="decimal"
                                                           class="form-control form-control-sm text-right js-valor-monto js-monto-ar"
                                                           autocomplete="off"
                                                           value="{{ number_format((float) ($linea['monto'] ?? 0), 2, ',', '.') }}">
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="js-fila-vacia"><td colspan="3" class="text-muted text-center py-3">Sin cuentas con uso &laquo;Rendici&oacute;n de m&aacute;quinas&raquo;</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2"><strong>Gastos (apertura)</strong></div>
                            <div class="card-body p-0 table-responsive" style="max-height: 320px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-0 rendmaq-panel" id="tabla-gastos-rendicion">
                                    <thead>
                                        <tr>
                                            <th class="col-codigo">C&oacute;d.</th>
                                            <th>Concepto</th>
                                            <th class="text-right col-monto">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($gastosLineas as $linea)
                                            <tr data-apertura-gasto-id="{{ (int) $linea['apertura_gasto_id'] }}">
                                                <td class="text-muted col-codigo">{{ $linea['codigo'] ?? '' }}</td>
                                                <td class="col-desc" title="{{ $linea['nombre'] ?? '' }}">{{ $linea['nombre'] ?? '' }}</td>
                                                <td class="col-monto">
                                                    <input type="text" inputmode="decimal"
                                                           class="form-control form-control-sm text-right js-gasto-monto js-monto-ar"
                                                           autocomplete="off"
                                                           value="{{ number_format((float) ($linea['monto'] ?? 0), 2, ',', '.') }}">
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="js-fila-vacia"><td colspan="3" class="text-muted text-center py-3">Sin aperturas de gasto activas para la empresa</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card card-outline card-secondary">
                            <div class="card-header py-2"><strong>Orquestador</strong></div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-md-0">
                                        <label for="calc_comprobante">Comprobante</label>
                                        <input type="text" inputmode="decimal" id="calc_comprobante"
                                               class="form-control form-control-sm text-right js-calc-orq js-monto-ar"
                                               autocomplete="off"
                                               value="{{ number_format((float) ($calcOrq['comprobante'] ?? 0), 2, ',', '.') }}">
                                    </div>
                                    <div class="form-group col-md-6 mb-0">
                                        <label for="calc_vale_rep_fondo">Vale rep. fondo</label>
                                        <input type="text" inputmode="decimal" id="calc_vale_rep_fondo"
                                               class="form-control form-control-sm text-right js-calc-orq js-monto-ar"
                                               autocomplete="off"
                                               value="{{ number_format((float) ($calcOrq['vale_rep_fondo'] ?? 0), 2, ',', '.') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body pt-0">
                <div class="rendmaq-sticky-footer">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                        <strong>Totales calculados</strong>
                        <button type="button" class="btn btn-success btn-sm" id="btn-guardar-rendicion">
                            <i class="fa fa-save"></i> Guardar rendici&oacute;n
                        </button>
                    </div>
                    <div class="totales-grid" id="panel-totales-rendicion">
                        <div class="tot-item"><span class="lbl">Fondo inicial</span><span class="val" data-total="fondo_inicial">${{ number_format((float) ($totales['fondo_inicial'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item"><span class="lbl">Comprobante</span><span class="val" data-total="comprobante">${{ number_format((float) ($totales['comprobante'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item"><span class="lbl">Total ingreso</span><span class="val" data-total="total_ingreso">${{ number_format((float) ($totales['total_ingreso'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item"><span class="lbl">Total salida</span><span class="val" data-total="total_salida">${{ number_format((float) ($totales['total_salida'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item is-destacado"><span class="lbl">Resultado turno</span><span class="val" data-total="resultado_turno">${{ number_format((float) ($totales['resultado_turno'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item"><span class="lbl">Fondo cierre</span><span class="val" data-total="fondo_cierre">${{ number_format((float) ($totales['fondo_cierre'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item is-destacado"><span class="lbl">Transferencia</span><span class="val" data-total="transferencia">${{ number_format((float) ($totales['transferencia'] ?? 0), 2, ',', '.') }}</span></div>
                        <div class="tot-item d-none" id="wrap-dif-caja"><span class="lbl">Dif. caja (C)</span><span class="val" data-total="dif_caja">${{ number_format((float) ($totales['dif_caja'] ?? 0), 2, ',', '.') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-log-ajustes-wigos" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white">Log de ajustes WIGOS</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Campo</th>
                            <th class="text-right">WIGOS</th>
                            <th class="text-right">Ajustado</th>
                            <th class="text-right">Delta</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-log-ajustes-wigos"></tbody>
                </table>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
