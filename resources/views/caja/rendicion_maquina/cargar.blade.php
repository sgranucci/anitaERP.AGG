@extends("theme.$theme.layout")
@section('titulo')
    {{ ! empty($soloConsulta) ? 'Consultar rendición de máquinas' : (! empty($modo_edicion) ? 'Editar rendición de máquinas' : 'Nueva rendición de máquinas') }}
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
    /* Barra fija al pie (mismo patrón que empleado → acciones legajo) */
    .rendmaq-acciones-fijas {
        position: sticky;
        bottom: 0;
        z-index: 1050;
        background: #fff;
        border-top: 2px solid #85C1E9 !important;
        box-shadow: 0 -4px 14px rgba(23, 32, 42, 0.12);
        padding: 0.75rem 1rem;
    }
    .rendmaq-acciones-fijas .totales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
        gap: 0.3rem 0.4rem;
        flex: 1 1 auto;
        min-width: 0;
    }
    .rendmaq-acciones-fijas .tot-item {
        background: #f8fbfc;
        border: 1px solid #d6eaf8;
        border-radius: 3px;
        padding: 0.2rem 0.35rem;
        text-align: center;
        min-width: 0;
        overflow: hidden;
    }
    .rendmaq-acciones-fijas .tot-item.is-destacado {
        border-color: #5dade2;
        background: #ebf5fb;
    }
    .rendmaq-acciones-fijas .lbl {
        font-size: 0.62rem;
        color: #566573;
        display: block;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rendmaq-acciones-fijas .val {
        font-weight: 600;
        font-size: 0.72rem;
        color: #17202A;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rendmaq-acciones-fijas .rendmaq-footer-acciones {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.4rem;
        flex: 0 0 auto;
    }
    .input-wigos-ajustable { background-color: #fffde7 !important; }
    .rendmaq-hint {
        font-size: 0.8rem;
        color: #5d6d7e;
        margin: 0;
    }
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
    /* Sobre card-info: fondo blanco sólido (hover no pierde el texto) */
    .rendmaq-toolbar .btn-rendmaq-header {
        color: #1a5276 !important;
        background: #fff !important;
        border: 1px solid #fff !important;
        font-weight: 600;
    }
    .rendmaq-toolbar .btn-rendmaq-header:hover,
    .rendmaq-toolbar .btn-rendmaq-header:focus {
        color: #0e3d5c !important;
        background: #e8f4fc !important;
        border-color: #d4e6f1 !important;
    }
    .rendmaq-toolbar .btn-rendmaq-wigos {
        background: #f4d03f !important;
        border: 1px solid #d4ac0d !important;
        color: #1c2833 !important;
        font-weight: 600;
    }
    .rendmaq-toolbar .btn-rendmaq-wigos:hover,
    .rendmaq-toolbar .btn-rendmaq-wigos:focus {
        background: #f7dc6f !important;
        border-color: #d4ac0d !important;
        color: #1c2833 !important;
    }
    #aviso-wigos-pendiente,
    #aviso-wigos-progreso {
        display: none;
        margin: 0 0 0.75rem 0;
    }
    .rendmaq-bloque-impuestos {
        background: #f4f9fc;
        border: 1px solid #d4e6f1;
        border-radius: 4px;
        padding: 0.65rem 0.75rem 0.25rem;
        margin: 0.25rem 0 0.5rem;
    }
    .rendmaq-bloque-impuestos > .rendmaq-bloque-titulo {
        font-size: 0.78rem;
        font-weight: 700;
        color: #1a5276;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        margin: 0 0 0.45rem;
    }
    .rendmaq-bloque-manuales {
        border-top: 1px dashed #d5d8dc;
        margin-top: 0.5rem;
        padding-top: 0.65rem;
    }
    .rendmaq-bloque-manuales > .rendmaq-bloque-titulo {
        font-size: 0.78rem;
        font-weight: 700;
        color: #566573;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        margin: 0 0 0.45rem;
    }
</style>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/usuario/consulta.js')) }}"></script>
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
    $camposWigos = $d['campos_wigos'] ?? ($d['campos_wigos_ajustables'] ?? []);
    $camposImpuestos = $d['campos_impuestos'] ?? [];
    $camposManuales = $d['campos_manuales'] ?? [];
    $retornoListadoQuery = $filtrosQuery ?? [];
    $turnoActual = (string) ($turno ?? 'M');
    $mostrarAvisoPrecargaQr = $turnoActual === 'M';
    $badgeTurno = match ($turnoActual) {
        'C' => 'badge-warning',
        'N' => 'badge-dark',
        'T' => 'badge-info',
        default => 'badge-primary',
    };
    $modoEdicion = ! empty($modo_edicion);
    $soloConsulta = ! empty($soloConsulta);
    $soloLectura = $soloConsulta;
    $personalCampos = [
        [
            'label' => 'Supervisor',
            'id' => 'supervisor_usuario_id',
            'codigo_id' => 'supervisor_usuario_codigo',
            'nombre_id' => 'supervisor_usuario_nombre',
            'usuario' => $rendicion?->supervisorUsuario,
        ],
        [
            'label' => 'Auxiliar',
            'id' => 'auxiliar_usuario_id',
            'codigo_id' => 'auxiliar_usuario_codigo',
            'nombre_id' => 'auxiliar_usuario_nombre',
            'usuario' => $rendicion?->auxiliarUsuario,
        ],
        [
            'label' => 'Cajero',
            'id' => 'cajero_usuario_id',
            'codigo_id' => 'cajero_usuario_codigo',
            'nombre_id' => 'cajero_usuario_nombre',
            'usuario' => $rendicion?->cajeroUsuario,
        ],
    ];
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
        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'rendmaq-guardando-overlay',
            'tituloId' => 'rendmaq-guardando-titulo',
            'subtituloId' => 'rendmaq-guardando-subtitulo',
            'titulo' => 'Grabando rendición…',
            'subtitulo' => 'Por favor espere. Se abrirá el PDF y volverá al listado.',
        ])

        <div class="card card-info mb-3">
            <div class="card-header rendmaq-card-header">
                <h3 class="card-title mb-0">
                    @if ($modoEdicion)
                        @if ($soloConsulta)
                            Consultar rendici&oacute;n #{{ (int) ($rendicion_id ?? 0) }}
                        @else
                            Editar rendici&oacute;n #{{ (int) ($rendicion_id ?? 0) }}
                        @endif
                    @else
                        Nueva rendici&oacute;n de m&aacute;quinas
                    @endif
                    <span id="rendmaq-badge-turno" class="badge {{ $badgeTurno }} ml-2">Turno {{ $turnoActual }}</span>
                </h3>
                <div class="rendmaq-toolbar">
                    @if (! $soloConsulta)
                    <a href="{{ route('rendicion_maquina', $retornoListadoQuery) }}" class="btn btn-sm btn-rendmaq-header">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                    @if (! $soloConsulta && ! empty($puede_ver_log_wigos))
                        <button type="button" class="btn btn-sm btn-rendmaq-header" id="btn-ver-log-ajustes">
                            <i class="fa fa-history"></i> Log ajustes
                        </button>
                    @endif
                    @if (! $soloConsulta)
                    <button type="button" class="btn btn-sm btn-rendmaq-wigos" id="btn-traer-wigos">
                        <i class="fa fa-cloud-download"></i> Traer WIGOS
                    </button>
                    @endif
                    @if ($modoEdicion && \App\Support\Contable\CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionMaquina())
                        <a href="{{ route('imprimir_rendicion_maquina', ['id' => (int) $rendicion_id, 'inline' => 1]) }}"
                           target="_blank" rel="noopener"
                           class="btn btn-sm btn-rendmaq-header"
                           title="Imprimir comprobante PDF">
                            <i class="fa fa-print"></i> PDF
                        </a>
                    @endif
                    @if ($soloConsulta)
                        <button type="button" class="btn btn-sm btn-rendmaq-header" onclick="window.close()">
                            Cerrar solapa
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body pb-0 @if($soloLectura) pe-none @endif" @if($soloLectura) style="opacity:.92" @endif>
                @if (! $modoEdicion)
                    <div class="alert alert-info py-2 px-3" id="aviso-wigos-progreso" role="status">
                        <i class="fa fa-spinner fa-spin"></i>
                        Leyendo WIGOS en segundo plano&hellip; Puede seguir cargando valores, gastos y el resto del formulario.
                        Si cambia empresa, fecha o turno, se reinicia la lectura.
                    </div>
                    <div class="alert alert-warning py-2 px-3" id="aviso-wigos-pendiente" role="alert">
                        <i class="fa fa-exclamation-triangle"></i>
                        Todav&iacute;a no se importaron datos WIGOS. Revise empresa, fecha y turno y pulse
                        <strong>Traer WIGOS</strong> (o al cambiar esos campos se lee solo).
                        Mientras tanto puede cargar el resto de la rendici&oacute;n.
                    </div>
                @endif
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
                                        <small class="text-muted">
                                            Cierre de jornada: drop del d&iacute;a + suma M/T/N.
                                            Fondo inicial y comprobante = apertura del turno ma&ntilde;ana.
                                        </small>
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
                                    @foreach ($personalCampos as $campoPersonal)
                                        @php
                                            $uPersonal = $campoPersonal['usuario'];
                                            $idPersonal = (int) ($uPersonal->id ?? 0);
                                            $codigoPersonal = (string) ($uPersonal->usuario ?? '');
                                            $nombrePersonal = (string) ($uPersonal->nombre ?? '');
                                        @endphp
                                        <div class="form-group col-md-4 tm-usuario-campo">
                                            <label for="{{ $campoPersonal['codigo_id'] }}">{{ $campoPersonal['label'] }}</label>
                                            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                                <input type="hidden"
                                                       id="{{ $campoPersonal['id'] }}"
                                                       class="usuario_id_arbol"
                                                       value="{{ $idPersonal > 0 ? $idPersonal : '' }}">
                                                <input type="text"
                                                       id="{{ $campoPersonal['codigo_id'] }}"
                                                       class="usuario_codigo_arbol form-control form-control-sm"
                                                       style="flex: 0 0 5.5rem; width: 5.5rem;"
                                                       value="{{ $codigoPersonal }}"
                                                       placeholder="C&oacute;d."
                                                       title="C&oacute;digo o ID; Enter valida; F1 consulta"
                                                       autocomplete="off"
                                                       autocapitalize="off"
                                                       spellcheck="false">
                                                <button type="button"
                                                        title="Consulta usuarios (F1)"
                                                        class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0"
                                                        data-ptrusuario_id="#{{ $campoPersonal['id'] }}"
                                                        data-ptrnombre="#{{ $campoPersonal['nombre_id'] }}"
                                                        data-ptrusuario_codigo="#{{ $campoPersonal['codigo_id'] }}">
                                                    <i class="fa fa-search text-primary"></i>
                                                </button>
                                                <input type="text"
                                                       id="{{ $campoPersonal['nombre_id'] }}"
                                                       class="nombreusuario form-control form-control-sm"
                                                       style="flex: 1 1 auto; min-width: 0;"
                                                       value="{{ $nombrePersonal }}"
                                                       placeholder="Nombre"
                                                       readonly
                                                       tabindex="-1"
                                                       autocomplete="off">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-group mb-0">
                                    <label for="observacion_rendicion">Observaci&oacute;n</label>
                                    <textarea id="observacion_rendicion" class="form-control form-control-sm" rows="2" maxlength="500">{{ $rendicion->observacion ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card card-outline card-info">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                                <strong>Datos WIGOS</strong>
                                @if (! empty($puede_ajustar_wigos))
                                    <span class="badge badge-warning">Editables (amarillo = ajustado → log)</span>
                                @endif
                            </div>
                            <div class="card-body p-2">
                                <p class="rendmaq-hint mb-2 px-1">
                                    <strong>Traer WIGOS</strong> importa drop, venta (slots + ruletas), tito, pagos y QR.
                                    Drop rodillo en pantalla = <strong>neto</strong> (bruto − impuesto drop);
                                    el bruto queda aparte. Drop anterior en M/T/N = bruto WIGOS (como Anita);
                                    en C = neto del M del d&iacute;a. En Completo, fondo/comprobante = apertura de la ma&ntilde;ana;
                                    WIN = drop efectivo neto + drop QR + ventas − manuales − tito.
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
                                </div>

                                @if ($camposImpuestos !== [])
                                    <div class="rendmaq-bloque-impuestos">
                                        <p class="rendmaq-bloque-titulo">Impuestos</p>
                                        <div class="form-row">
                                            @foreach ($camposImpuestos as $campoRuta => $etiqueta)
                                                @php
                                                    $clave = str_starts_with($campoRuta, 'inputs.') ? substr($campoRuta, 7) : $campoRuta;
                                                    $valorInput = $inputs[$clave] ?? $inputs[$campoRuta] ?? 0;
                                                @endphp
                                                <div class="form-group col-md-6 col-lg-3 mb-2">
                                                    <label class="small mb-0" for="input_{{ $clave }}">{!! $etiqueta !!}</label>
                                                    <input type="text" inputmode="decimal"
                                                           id="input_{{ $clave }}"
                                                           class="form-control form-control-sm js-input-wigos js-monto-ar text-right"
                                                           data-campo="{{ $campoRuta }}"
                                                           data-clave="{{ $clave }}"
                                                           autocomplete="off"
                                                           value="{{ number_format((float) $valorInput, 2, ',', '.') }}">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="rendmaq-bloque-manuales">
                                    <p class="rendmaq-bloque-titulo">Carga manual / previas</p>
                                    <div class="form-row">
                                        @php
                                            $etiquetasManuales = [
                                                'fondo_inicial' => 'Fondo inicial',
                                                'variacion_ff' => 'Variaci&oacute;n FF',
                                                'pago_diferido' => 'Pago diferido',
                                                'sobrantes' => 'Sobrantes',
                                                'ticket_prom' => 'Ticket promocionales',
                                            ];
                                            $depositoCalc = (float) ($totales['deposito'] ?? $inputs['deposito'] ?? 0);
                                            $fondoFijoCalc = (float) ($totales['fondo_fijo']
                                                ?? ((float) ($inputs['fondo_inicial'] ?? 0) + (float) ($calcOrq['comprobante'] ?? 0)));
                                        @endphp
                                        @foreach ($camposManuales as $claveManual)
                                            @php
                                                $valorManual = $inputs[$claveManual] ?? 0;
                                                $etiquetaManual = $etiquetasManuales[$claveManual]
                                                    ?? ucfirst(str_replace('_', ' ', $claveManual));
                                            @endphp
                                            <div class="form-group col-md-6 col-lg-4 mb-2">
                                                <label class="small mb-0" for="input_{{ $claveManual }}">{!! $etiquetaManual !!}</label>
                                                <input type="text" inputmode="decimal"
                                                       id="input_{{ $claveManual }}"
                                                       class="form-control form-control-sm js-input-manual js-monto-ar text-right"
                                                       data-clave="{{ $claveManual }}"
                                                       autocomplete="off"
                                                       value="{{ number_format((float) $valorManual, 2, ',', '.') }}">
                                            </div>
                                        @endforeach
                                        <div class="form-group col-md-6 col-lg-4 mb-2">
                                            <label class="small mb-0" for="calc_fondo_fijo"
                                                   title="fondo_inicial + comprobante (Anita RENDMc_fondo_fijo)">
                                                Fondo fijo tesoro <span class="text-muted">(calculado)</span>
                                            </label>
                                            <input type="text" id="calc_fondo_fijo" readonly
                                                   class="form-control form-control-sm text-right bg-light"
                                                   value="{{ number_format($fondoFijoCalc, 2, ',', '.') }}">
                                        </div>
                                        <div class="form-group col-md-6 col-lg-4 mb-2">
                                            <label class="small mb-0" for="calc_deposito"
                                                   title="valores + gastos (+ vta ant. gastro si hubiera)">
                                                Dep&oacute;sito <span class="text-muted">(calculado)</span>
                                            </label>
                                            <input type="text" id="calc_deposito" readonly
                                                   class="form-control form-control-sm text-right bg-light"
                                                   value="{{ number_format($depositoCalc, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2">
                                <strong>Valores (cuentas de caja)</strong>
                                <small class="text-muted d-block font-weight-normal" id="aviso-precarga-qr-maquinas"
                                       style="{{ $mostrarAvisoPrecargaQr ? '' : 'display:none' }}">
                                    En turno mañana, TotalCoin QR Máquina se precarga al traer WIGOS (drop QR rodillo + impuesto QR).
                                </small>
                            </div>
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
                                            <tr data-cuentacaja-id="{{ (int) $linea['cuentacaja_id'] }}"
                                                data-cotizacion="{{ number_format((float) ($linea['cotizacion'] ?? 1), 6, '.', '') }}"
                                                data-moneda-id="{{ (int) ($linea['moneda_id'] ?? 1) }}">
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
                                        <label for="calc_vale_rep_fondo" title="Remesa interna Anita (rememae tipo I), solo turno ma&ntilde;ana">
                                            Vale rep. fondo
                                        </label>
                                        <input type="text" inputmode="decimal" id="calc_vale_rep_fondo"
                                               class="form-control form-control-sm text-right js-calc-orq js-monto-ar"
                                               autocomplete="off"
                                               title="Se precarga desde remesa interna Anita en turno M; editable"
                                               value="{{ number_format((float) ($calcOrq['vale_rep_fondo'] ?? 0), 2, ',', '.') }}">
                                        @if (($d['previas']['origen_vale_rep_fondo'] ?? '') === 'rememae')
                                            <small class="text-muted">Precargado desde remesa interna Anita.</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer rendmaq-acciones-fijas">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <strong class="mb-1 mb-md-0"><i class="fa fa-calculator text-info"></i> Totales (siempre visibles)</strong>
                    <div class="rendmaq-footer-acciones">
                        @if ($soloConsulta)
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">
                                Cerrar solapa
                            </button>
                        @else
                            <a href="{{ route('rendicion_maquina', $retornoListadoQuery) }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                            <button type="button" class="btn btn-success btn-sm" id="btn-guardar-rendicion">
                                <i class="fa fa-save"></i> Guardar rendici&oacute;n
                            </button>
                        @endif
                    </div>
                </div>
                <div class="totales-grid" id="panel-totales-rendicion">
                    <div class="tot-item"><span class="lbl">Fondo inicial</span><span class="val" data-total="fondo_inicial">${{ number_format((float) ($totales['fondo_inicial'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Comprobante</span><span class="val" data-total="comprobante">${{ number_format((float) ($totales['comprobante'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item is-destacado"><span class="lbl">Fondo fijo tesoro</span><span class="val" data-total="fondo_fijo">${{ number_format((float) ($totales['fondo_fijo'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Venta slots</span><span class="val" data-total="venta_ficha">${{ number_format((float) ($totales['venta_ficha'] ?? $inputs['venta_ficha'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Venta ruletas</span><span class="val" data-total="venta_ruleta">${{ number_format((float) ($totales['venta_ruleta'] ?? $inputs['venta_ruleta'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item is-destacado"><span class="lbl">Total ventas</span><span class="val" data-total="total_ventas">${{ number_format((float) ($totales['total_ventas'] ?? ((float) ($inputs['venta_ficha'] ?? 0) + (float) ($inputs['venta_ruleta'] ?? 0))), 2, ',', '.') }}</span></div>
                    <div class="tot-item is-destacado" title="Drop efectivo neto + drop QR + ventas − manuales − tito (ventas ya netas de impuesto)">
                        <span class="lbl">WIN</span>
                        <span class="val" data-total="win">${{ number_format((float) ($totales['win'] ?? 0), 2, ',', '.') }}</span>
                    </div>
                    <div class="tot-item"><span class="lbl">Drop rodillo bruto</span><span class="val" data-total="drop_billete_bruto">${{ number_format((float) ($totales['drop_billete_bruto'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Impuesto drop</span><span class="val" data-total="impuesto_drop">${{ number_format((float) ($totales['impuesto_drop'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item is-destacado"><span class="lbl">Drop rodillo neto</span><span class="val" data-total="drop_bill_rodillo">${{ number_format((float) ($totales['drop_bill_rodillo'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Drop billetes ruleta</span><span class="val" data-total="drop_bill_ruleta">${{ number_format((float) ($totales['drop_bill_ruleta'] ?? $inputs['drop_ruleta'] ?? 0), 2, ',', '.') }}</span></div>
                    <div class="tot-item"><span class="lbl">Drop QR rodillo</span><span class="val" data-total="dropqr_rodillo">${{ number_format((float) ($totales['dropqr_rodillo'] ?? $inputs['dropqr_rodillo'] ?? 0), 2, ',', '.') }}</span></div>
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

@include('includes.admin.modalconsultausuario')
@endsection
