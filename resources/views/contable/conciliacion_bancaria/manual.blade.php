@extends("theme.$theme.layout")
@section('titulo')
    Conciliación manual
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/conciliacion-bancaria-manual.css') }}?v={{ @filemtime(public_path('assets/css/conciliacion-bancaria-manual.css')) ?: time() }}">
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionManual = {
        empresaId: {{ (int) $empresa_id }},
        cuentacajaId: {{ (int) $cuentacaja_id }},
        mes: {{ (int) $mes }},
        anio: {{ (int) $anio }},
        tableroUrl: @json(route('conciliacion_bancaria_api_tablero')),
        conciliarUrl: @json(route('conciliacion_bancaria_api_conciliar')),
        desconciliarUrl: @json(route('conciliacion_bancaria_api_desconciliar')),
        sincronizarUrl: @json(route('conciliacion_bancaria_api_sincronizar_dia')),
        guardarModoUrl: @json(route('conciliacion_bancaria_api_guardar_modo')),
        importarExtractoUrl: @json(route('conciliacion_bancaria_api_importar_extracto')),
        cerrarPeriodoUrl: @json(route('conciliacion_bancaria_api_cerrar_periodo')),
        reabrirPeriodoUrl: @json(route('conciliacion_bancaria_api_reabrir_periodo')),
        aprobarPeriodoUrl: @json(route('conciliacion_bancaria_api_aprobar_periodo')),
        adjuntarEvidenciaUrl: @json(route('conciliacion_bancaria_api_adjuntar_evidencia')),
        listarEvidenciaUrl: @json(route('conciliacion_bancaria_api_listar_evidencia')),
        descargarEvidenciaUrl: @json(url('contable/conciliacion-bancaria/api/descargar-evidencia')),
        puedeAprobar: @json(can('aprobar-conciliacion-bancaria', false)),
        exportarUrl: @json(route('conciliacion_bancaria_manual_exportar')),
        preferencias: @json($modo_prefs ?? []),
        tolerancia: {{ (float) \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaReglasSupport::toleranciaImporte() }}
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/manual.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/manual.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $prefs = $modo_prefs ?? [];
    $modoActual = $prefs['modo'] ?? 'contaduria';
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info conciliacion-manual-workspace">
            <div class="card-header">
                <h3 class="card-title">
                    Conciliación manual
                    @if ($cuentacaja)
                        — {{ $cuentacaja->codigo }} {{ $cuentacaja->nombre }}
                    @endif
                    ({{ sprintf('%02d/%d', $mes, $anio) }})
                </h3>
                <div class="card-tools d-flex align-items-center" style="gap:8px;">
                    <label class="mb-0 small text-white" for="select-modo-workspace">Modo</label>
                    <select id="select-modo-workspace" class="form-control form-control-sm" style="width:auto;min-width:210px;" title="Se guarda por empresa">
                        @foreach (($modos_opciones ?? []) as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($modoActual === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <a href="{{ $volver }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body pb-2">
                <p class="small text-muted mb-2" id="ayuda-modo">{{ $prefs['ayuda'] ?? '' }}</p>

                <div class="cm-toolbar d-flex flex-wrap align-items-end mb-2" style="gap:8px;">
                    <div>
                        <label class="mb-0 small" for="filtro-fecha">Fecha</label>
                        <input type="date" id="filtro-fecha" class="form-control form-control-sm" style="width:150px;" title="Filtra y permite traer movimientos IB de ese día">
                    </div>
                    <div>
                        <label class="mb-0 small" for="filtro-texto">Texto</label>
                        <input type="text" id="filtro-texto" class="form-control form-control-sm" placeholder="Detalle, tipo, comprobante" style="width:200px;">
                    </div>
                    <div>
                        <label class="mb-0 small" for="filtro-importe">Importe</label>
                        <input type="text" id="filtro-importe" class="form-control form-control-sm" placeholder="-381225,12" style="width:130px;" title="Acepta 381225,12 o 381225.12">
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="solo-pendientes" @checked(($prefs['solo_pendientes_default'] ?? true))>
                        <label class="form-check-label" for="solo-pendientes">Solo pendientes</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="incluir-anteriores" @checked(($prefs['incluir_anteriores_default'] ?? false))>
                        <label class="form-check-label" for="incluir-anteriores">Incluir anteriores</label>
                    </div>
                    <button type="button" class="btn btn-sm {{ !empty($prefs['traer_banco_destacado']) ? 'btn-primary' : 'btn-outline-primary' }}" id="btn-traer-banco" title="Consulta Interbanking y persiste movimientos de la fecha">
                        <i class="fa fa-cloud-download-alt"></i> Traer del banco
                    </button>
                    <label class="btn btn-outline-primary btn-sm mb-0" for="input-extracto" title="Excel Contaduría, CSV, OFX, CAMT.053 o MT940">
                        <i class="fa fa-upload"></i> Importar extracto
                    </label>
                    <input type="file" id="input-extracto" class="d-none" accept=".xlsx,.xls,.csv,.txt,.ofx,.qfx,.xml,.sta,.mt940">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-sugerir" title="Sugerir par por importe (S)">Sugerir par</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-conciliar" title="Conciliar selección (Enter). Soporta multi-match">Conciliar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-limpiar-sel" title="Limpiar selección (Esc)">Limpiar</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btn-cerrar-periodo" title="Cierra el período para auditoría">Cerrar período</button>
                    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btn-aprobar-periodo" title="Aprueba el cierre (workflow Contaduría)">Aprobar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-reabrir-periodo" title="Reabre el período cerrado">Reabrir</button>
                    <a href="#" class="btn btn-outline-success btn-sm" id="btn-exportar">
                        <i class="fa fa-file-excel"></i> Excel
                    </a>
                    <span class="small text-muted" id="estado-cruce"></span>
                    <span class="badge badge-light ml-1" id="badge-cierre">Período abierto</span>
                </div>

                <div class="cm-evidencia border rounded p-2 mb-2" id="panel-evidencia">
                    <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                        <strong class="small mb-0">Evidencia de cierre / aprobación</strong>
                        <label class="btn btn-outline-secondary btn-sm mb-0" for="input-evidencia" title="PDF, imagen o Excel de respaldo">
                            <i class="fa fa-paperclip"></i> Adjuntar
                        </label>
                        <input type="file" id="input-evidencia" class="d-none" multiple accept=".pdf,.png,.jpg,.jpeg,.xlsx,.xls,.csv,.txt">
                        <span class="small text-muted" id="evidencia-ayuda">Los archivos se guardan al cerrar, aprobar o con Adjuntar (si ya hay ejecución).</span>
                    </div>
                    <ul class="list-unstyled small mb-0 mt-2" id="lista-evidencias">
                        <li class="text-muted">Sin adjuntos todavía.</li>
                    </ul>
                </div>

                <div class="cm-chips mb-2" id="chips-tipo" role="group" aria-label="Tipo de movimiento">
                    <button type="button" class="btn btn-sm btn-primary cm-chip active" data-tipo="">Todos</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary cm-chip" data-tipo="cheque">Cheques</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary cm-chip" data-tipo="trf">Transferencias</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary cm-chip" data-tipo="dep">Depósitos</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary cm-chip" data-tipo="gasto">Gastos / débitos</button>
                </div>

                <div class="cm-totales mb-2" id="barra-totales">
                    <div class="cm-total-item">
                        <span class="cm-total-label">Sel. anitaERP</span>
                        <strong id="tot-sel-c">0,00</strong>
                        <span class="small text-muted" id="tot-sel-c-n">0</span>
                    </div>
                    <div class="cm-total-item">
                        <span class="cm-total-label">Sel. banco</span>
                        <strong id="tot-sel-b">0,00</strong>
                        <span class="small text-muted" id="tot-sel-b-n">0</span>
                    </div>
                    <div class="cm-total-item">
                        <span class="cm-total-label">Diferencia</span>
                        <strong id="tot-diff">0,00</strong>
                    </div>
                    <div class="cm-total-item">
                        <span class="cm-total-label">Importe conciliado (mes)</span>
                        <strong id="tot-conciliado">0,00</strong>
                    </div>
                    @if (!empty($prefs['mostrar_saldo_historico']))
                        <div class="cm-total-item cm-total-historico">
                            <span class="cm-total-label">Pares del período</span>
                            <strong id="tot-pares-n">0</strong>
                        </div>
                    @endif
                    <div class="cm-total-item flex-grow-1">
                        <span class="small" id="aviso-multimatch"></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8 pr-lg-1">
                        <div class="row">
                            <div class="col-md-6 pr-md-1 mb-2 mb-md-0">
                                <div class="d-flex justify-content-between align-items-center px-2 py-1 cm-cabezal-contable">
                                    <strong>anitaERP</strong>
                                    <span class="small" id="cuenta-contable"></span>
                                </div>
                                <div id="lista-contable" class="border cm-lista" tabindex="0"></div>
                            </div>
                            <div class="col-md-6 pl-md-1">
                                <div class="d-flex justify-content-between align-items-center px-2 py-1 cm-cabezal-banco">
                                    <strong>Banco / extracto</strong>
                                    <span class="small" id="cuenta-banco"></span>
                                </div>
                                <div id="lista-banco" class="border cm-lista" tabindex="0"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 pl-lg-1">
                        <div class="cm-detalle border h-100">
                            <div class="px-2 py-1 cm-cabezal-detalle">
                                <strong>Detalle</strong>
                                <span class="small text-muted" id="detalle-lado"></span>
                            </div>
                            <div class="p-2" id="panel-detalle">
                                <p class="text-muted small mb-0">Elegí un movimiento para ver el detalle. Clic = selección exclusiva · Ctrl+clic = multi-selección.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <h5 class="mb-2">Conciliados del período</h5>
                    <div id="lista-pares" class="border" style="max-height:28vh;overflow:auto;"></div>
                </div>

                <p class="small text-muted mt-2 mb-0">
                    Atajos: <kbd>↑</kbd><kbd>↓</kbd> navegar · <kbd>Espacio</kbd> marcar · <kbd>Enter</kbd> conciliar ·
                    <kbd>S</kbd> sugerir · <kbd>Esc</kbd> limpiar · <kbd>Ctrl</kbd>+clic multi-selección.
                    Multi-match: seleccioná N de un lado y M del otro; si las sumas coinciden, se guarda el grupo.
                    Extracto: Excel Contaduría, CSV (fecha;importe;concepto), OFX, CAMT.053 o MT940 — también para bancos sin Interbanking.
                    Evidencia: adjuntá el respaldo al cerrar o aprobar el período (o con Adjuntar si ya hay corrida).
                </p>
            </div>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'conciliacion-manual-overlay',
    'tituloId' => 'conciliacion-manual-titulo',
    'subtituloId' => 'conciliacion-manual-subtitulo',
    'titulo' => 'Cargando movimientos…',
    'subtitulo' => 'Leyendo el mayor del mes y el extracto.',
])
@endsection
