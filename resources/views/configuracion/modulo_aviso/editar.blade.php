@extends("theme.$theme.layout")
@section('titulo')
Configurar aviso — {{ $tipo->nombre }}
@endsection

@section('contenido')
@php
    $tab = $tab_activa ?? 'acciones';
    $programable = $programable ?? false;
@endphp
@include('includes.tabs-activas-estilos')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-envelope-o"></i> {{ $tipo->nombre }}
                    <small class="text-muted">({{ $tipo->modulo }} / {{ $tipo->codigo }})</small>
                </h3>
                <div class="card-tools">
                    @if ($programable)
                        <a href="{{ route('consultar_modulo_aviso', ['solo_programables' => 1]) }}" class="btn btn-sm btn-outline-light">
                            <i class="fa fa-clock"></i> Programador
                        </a>
                    @endif
                    <a href="{{ url('configuracion/modulo-aviso') }}" class="btn btn-sm btn-outline-light">
                        <i class="fa fa-reply-all"></i> Listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="tabs-activas mb-3">
                    <ul class="nav nav-tabs" id="tabs-modulo-aviso" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'acciones' ? 'active' : '' }}" data-toggle="tab" href="#tab-acciones" role="tab">
                                <i class="fa fa-bolt"></i> Acciones
                            </a>
                        </li>
                        @if ($programable)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'rango' ? 'active' : '' }}" data-toggle="tab" href="#tab-rango" role="tab">
                                <i class="fa fa-calendar"></i> Rango temporal
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'destinatarios' ? 'active' : '' }}" data-toggle="tab" href="#tab-destinatarios" role="tab">
                                <i class="fa fa-users"></i> Destinatarios
                            </a>
                        </li>
                        @if ($programable)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'ejecucion' ? 'active' : '' }}" data-toggle="tab" href="#tab-ejecucion" role="tab">
                                <i class="fa fa-play"></i> Ejecución
                            </a>
                        </li>
                        @endif
                        @if ($programable && ! empty($suscripciones_envio))
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'suscripciones' ? 'active' : '' }}" data-toggle="tab" href="#tab-suscripciones" role="tab">
                                <i class="fa fa-share-alt"></i> Suscripciones
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <form action="{{ url('configuracion/modulo-aviso/'.$tipo->id) }}" method="POST" id="form-modulo-aviso" autocomplete="off" data-enter-campos="1">
                    @csrf @method('PUT')
                    <div class="tab-content">
                        <div class="tab-pane fade {{ $tab === 'acciones' ? 'show active' : '' }}" id="tab-acciones" role="tabpanel">
                            <div class="form-check mb-3">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                                    @if(old('activo', $tipo->activo)) checked @endif>
                                <label class="form-check-label" for="activo">Envío activo</label>
                            </div>
                            <div class="form-group">
                                <label for="mail_asunto" class="control-label">Asunto del correo</label>
                                <input type="text" class="form-control" name="mail_asunto" id="mail_asunto" maxlength="255" required
                                    value="{{ old('mail_asunto', $tipo->mail_asunto) }}">
                            </div>
                            <div class="form-group">
                                <label for="mail_texto" class="control-label">Texto del cuerpo</label>
                                <textarea class="form-control" name="mail_texto" id="mail_texto" rows="4" maxlength="8000">{{ old('mail_texto', $tipo->mail_texto) }}</textarea>
                                <small class="text-muted">Placeholders: {{ implode(', ', $placeholders_ayuda) }}</small>
                            </div>
                            <div class="form-group">
                                <label for="mail_remitente" class="control-label">Remitente (From) opcional</label>
                                <input type="email" class="form-control" name="mail_remitente" id="mail_remitente" maxlength="255"
                                    value="{{ old('mail_remitente', $tipo->mail_remitente) }}">
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="hidden" name="adjuntar_pdf" value="0">
                                        <input type="checkbox" class="form-check-input" name="adjuntar_pdf" id="adjuntar_pdf" value="1"
                                            @if(old('adjuntar_pdf', $tipo->adjuntar_pdf)) checked @endif>
                                        <label class="form-check-label" for="adjuntar_pdf">Adjuntar PDF de emisión</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="hidden" name="incluir_link_consulta" value="0">
                                        <input type="checkbox" class="form-check-input" name="incluir_link_consulta" id="incluir_link_consulta" value="1"
                                            @if(old('incluir_link_consulta', $tipo->incluir_link_consulta)) checked @endif>
                                        <label class="form-check-label" for="incluir_link_consulta">Incluir enlace a consulta en el ERP</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="hidden" name="notificar_in_app" value="0">
                                        <input type="checkbox" class="form-check-input" name="notificar_in_app" id="notificar_in_app" value="1"
                                            @if(old('notificar_in_app', $tipo->notificar_in_app)) checked @endif>
                                        <label class="form-check-label" for="notificar_in_app">También avisar en campanita</label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input type="hidden" name="notificar_whatsapp" value="0">
                                        <input type="checkbox" class="form-check-input" name="notificar_whatsapp" id="notificar_whatsapp" value="1"
                                            @if(old('notificar_whatsapp', $tipo->notificar_whatsapp ?? false)) checked @endif>
                                        <label class="form-check-label" for="notificar_whatsapp">También WhatsApp (si la empresa tiene cuenta)</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <div class="form-group mb-0">
                                        <label for="sla_horas" class="control-label">SLA (horas)</label>
                                        <input type="number" min="0" max="8760" class="form-control" name="sla_horas" id="sla_horas"
                                            value="{{ old('sla_horas', $tipo->sla_horas) }}"
                                            placeholder="Vacío = default sistema">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($programable)
                        @php
                            $diasMarcados = old('programacion_dias');
                            if (! is_array($diasMarcados)) {
                                $diasMarcados = \App\Support\Configuracion\ModuloAvisoProgramacionSupport::diasLista($tipo->programacion_dias);
                            }
                            $diasMarcados = array_map('intval', $diasMarcados);
                            $horaProgramada = old('programacion_hora', \App\Support\Configuracion\ModuloAvisoProgramacionSupport::normalizarHora($tipo->programacion_hora) ?? '08:20');
                        @endphp
                        <div class="tab-pane fade {{ $tab === 'rango' ? 'show active' : '' }}" id="tab-rango" role="tabpanel">
                            <p class="text-muted small">
                                El despachador revisa cada 15 minutos y envía una vez al día a partir de la hora, en los días marcados (o el día del mes).
                            </p>
                            <div class="form-check mb-2">
                                <input type="hidden" name="programacion_habilitada" value="0">
                                <input type="checkbox" class="form-check-input" name="programacion_habilitada" id="programacion_habilitada" value="1"
                                    @if(old('programacion_habilitada', $tipo->programacion_habilitada)) checked @endif>
                                <label class="form-check-label" for="programacion_habilitada">Programación activa</label>
                            </div>
                            <div class="form-check mb-3">
                                <input type="hidden" name="programacion_pausada" value="0">
                                <input type="checkbox" class="form-check-input" name="programacion_pausada" id="programacion_pausada" value="1"
                                    @if(old('programacion_pausada', $tipo->programacion_pausada ?? false)) checked @endif>
                                <label class="form-check-label" for="programacion_pausada">Pausada (cron no dispara; sí podés ejecutar manual)</label>
                            </div>
                            @php
                                $horaHastaProgramada = old(
                                    'programacion_hora_hasta',
                                    \App\Support\Configuracion\ModuloAvisoProgramacionSupport::normalizarHora($tipo->programacion_hora_hasta ?? null)
                                );
                            @endphp
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_hora" class="control-label">Hora desde</label>
                                        <input type="time" class="form-control" name="programacion_hora" id="programacion_hora" value="{{ $horaProgramada }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_hora_hasta" class="control-label">Hora hasta</label>
                                        <input type="time" class="form-control" name="programacion_hora_hasta" id="programacion_hora_hasta" value="{{ $horaHastaProgramada }}">
                                        <small class="text-muted">Vacío = hasta fin del día. Si se pasa la ventana, no corre.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="d-block control-label">Días de la semana</label>
                                @foreach (\App\Support\Configuracion\ModuloAvisoProgramacionSupport::DIAS as $nro => $nombre)
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" name="programacion_dias[]" id="programacion_dia_{{ $nro }}" value="{{ $nro }}"
                                            @if(in_array($nro, $diasMarcados, true)) checked @endif>
                                        <label class="form-check-label" for="programacion_dia_{{ $nro }}">{{ $nombre }}</label>
                                    </div>
                                @endforeach
                                <small class="form-text text-muted">Si cargás «Día del mes», los días de la semana se ignoran.</small>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_dia_mes" class="control-label">Día del mes</label>
                                        <input type="number" class="form-control" name="programacion_dia_mes" id="programacion_dia_mes" min="1" max="31" step="1"
                                            value="{{ old('programacion_dia_mes', $tipo->programacion_dia_mes) }}"
                                            placeholder="Vacío = semanal">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_cada_n" class="control-label">Cada N semanas</label>
                                        <input type="number" class="form-control" name="programacion_cada_n" id="programacion_cada_n" min="1" max="52" step="1"
                                            value="{{ old('programacion_cada_n', $tipo->programacion_cada_n ?? 1) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_hasta" class="control-label">Hasta</label>
                                        <input type="date" class="form-control" name="programacion_hasta" id="programacion_hasta"
                                            value="{{ old('programacion_hasta', optional($tipo->programacion_hasta)->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="programacion_reintentos_max" class="control-label">Máx. reintentos/día</label>
                                        <input type="number" class="form-control" name="programacion_reintentos_max" id="programacion_reintentos_max" min="0" max="96" step="1"
                                            value="{{ old('programacion_reintentos_max', $tipo->programacion_reintentos_max ?? 0) }}">
                                        <small class="text-muted">0 = sin tope.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check mb-3">
                                        <input type="hidden" name="programacion_skip_feriados" value="0">
                                        <input type="checkbox" class="form-check-input" name="programacion_skip_feriados" id="programacion_skip_feriados" value="1"
                                            @if(old('programacion_skip_feriados', $tipo->programacion_skip_feriados ?? false)) checked @endif>
                                        <label class="form-check-label" for="programacion_skip_feriados">No correr en feriados</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="programacion_depende_de" class="control-label">Depende de (clave)</label>
                                        <select class="form-control" name="programacion_depende_de" id="programacion_depende_de">
                                            <option value="">— Sin dependencia —</option>
                                            @foreach (($claves_dependencia ?? []) as $claveDep)
                                                <option value="{{ $claveDep }}"
                                                    @if (old('programacion_depende_de', $tipo->programacion_depende_de) === $claveDep) selected @endif>
                                                    {{ $claveDep }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="programacion_depende_modo" class="control-label">Modo dependencia</label>
                                        <select class="form-control" name="programacion_depende_modo" id="programacion_depende_modo">
                                            @foreach (\App\Support\Configuracion\ModuloAvisoProgramacionSupport::modosDependencia() as $modoVal => $modoLabel)
                                                <option value="{{ $modoVal }}"
                                                    @if (old('programacion_depende_modo', $tipo->programacion_depende_modo ?? 'ok_hoy') === $modoVal) selected @endif>
                                                    {{ $modoLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @if (! empty($usa_parametro_dias))
                                <div class="form-group" style="max-width: 14rem;">
                                    <label for="parametro_dias" class="control-label">Días próximos a vencer</label>
                                    <input type="number" class="form-control" name="parametro_dias" id="parametro_dias" min="0" max="365" step="1"
                                        value="{{ old('parametro_dias', $tipo->condicion('dias_proximos', $tipo->parametro_dias ?? 7)) }}">
                                </div>
                            @endif
                            @if ($tipo->programacion_ultima_en)
                                <p class="text-muted small mb-2">Última corrida OK: {{ $tipo->programacion_ultima_en->format('d/m/Y') }}</p>
                            @endif
                            <div class="form-check mb-0 border rounded p-2 bg-light">
                                <input type="hidden" name="confirmar_cambio_programacion" value="0">
                                <input type="checkbox" class="form-check-input" name="confirmar_cambio_programacion" id="confirmar_cambio_programacion" value="1"
                                    @if(old('confirmar_cambio_programacion')) checked @endif>
                                <label class="form-check-label" for="confirmar_cambio_programacion">
                                    Confirmo el cambio de programación (horario / días / pausa / dependencia)
                                </label>
                                @error('confirmar_cambio_programacion')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted mb-0">Obligatorio solo si cambiaste el rango. Queda auditado en el tipo.</small>
                            </div>
                        </div>
                        @endif

                        <div class="tab-pane fade {{ $tab === 'destinatarios' ? 'show active' : '' }}" id="tab-destinatarios" role="tabpanel">
                            <p class="text-muted small">
                                Email y/o usuario (lupa / F1 / código + Enter). Empresa y CC filtran el ámbito (vacío = todos).
                                @if($tipo->codigo === 'asignacion_tecnico')
                                    En este aviso el mail principal va al técnico asignado; estas filas son copia.
                                @endif
                                @if (! empty($destinatarios_dinamicos))
                                    <br>{{ $destinatarios_dinamicos }}
                                @endif
                            </p>
                            <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="tabla-destinatarios-aviso">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th style="min-width:12rem;">Email</th>
                                        <th style="min-width:18rem;">Usuario</th>
                                        <th>Empresa filtro</th>
                                        <th>CC filtro</th>
                                        <th class="text-center">Activo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $filasDest = old('destinatarios');
                                        if ($filasDest === null) {
                                            $filasDest = $tipo->destinatarios->map(function ($d) {
                                                $u = $d->usuarios;
                                                return [
                                                    'id' => $d->id,
                                                    'email' => $d->email ?: ($u->email ?? ''),
                                                    'usuario_id' => $d->usuario_id,
                                                    'usuario_codigo' => $u->usuario ?? '',
                                                    'usuario_nombre' => $u->nombre ?? '',
                                                    'empresa_id' => $d->empresa_id,
                                                    'centrocosto_id' => $d->centrocosto_id,
                                                    'activo' => $d->activo,
                                                ];
                                            })->all();
                                        }
                                        if ($filasDest === [] || $filasDest === null) {
                                            $filasDest = [[
                                                'id' => '', 'email' => '', 'usuario_id' => '', 'usuario_codigo' => '',
                                                'usuario_nombre' => '', 'empresa_id' => '', 'centrocosto_id' => '', 'activo' => true,
                                            ]];
                                        }
                                    @endphp
                                    @foreach ($filasDest as $idx => $fila)
                                    <tr class="fila-destinatario-aviso">
                                        <td>
                                            <input type="hidden" name="destinatarios[{{ $idx }}][id]" value="{{ $fila['id'] ?? '' }}">
                                            <input type="email" class="form-control form-control-sm emailusuario"
                                                name="destinatarios[{{ $idx }}][email]"
                                                value="{{ $fila['email'] ?? '' }}" placeholder="correo@empresa.com">
                                        </td>
                                        <td>
                                            <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                                <input type="hidden" class="usuario_id_arbol"
                                                    name="destinatarios[{{ $idx }}][usuario_id]"
                                                    value="{{ $fila['usuario_id'] ?? '' }}">
                                                <input type="text"
                                                    class="usuario_codigo_arbol form-control form-control-sm"
                                                    value="{{ $fila['usuario_codigo'] ?? '' }}"
                                                    placeholder="Cód." autocomplete="off"
                                                    style="width:5.5rem;flex-shrink:0;"
                                                    title="Login o ID; Enter valida; F1 consulta">
                                                <button type="button" title="Consulta usuarios (F1)"
                                                    class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0"
                                                    data-omitir_filtro_empresa="1">
                                                    <i class="fa fa-search text-primary"></i>
                                                </button>
                                                <input type="text"
                                                    class="nombreusuario form-control form-control-sm"
                                                    value="{{ $fila['usuario_nombre'] ?? '' }}"
                                                    placeholder="Nombre" readonly
                                                    style="min-width:0;flex:1 1 auto;">
                                            </div>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm" name="destinatarios[{{ $idx }}][empresa_id]">
                                                <option value="">Todas</option>
                                                @foreach ($empresa_query as $emp)
                                                <option value="{{ $emp->id }}" @if((string)($fila['empresa_id'] ?? '') === (string)$emp->id) selected @endif>{{ $emp->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm" name="destinatarios[{{ $idx }}][centrocosto_id]">
                                                <option value="">Todos</option>
                                                @foreach ($centrocosto_query as $cc)
                                                @if($cc->id > 0)
                                                <option value="{{ $cc->id }}" @if((string)($fila['centrocosto_id'] ?? '') === (string)$cc->id) selected @endif>{{ $cc->codigo }} - {{ $cc->nombre }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="destinatarios[{{ $idx }}][activo]" value="0">
                                            <input type="checkbox" name="destinatarios[{{ $idx }}][activo]" value="1"
                                                @if(!empty($fila['activo'])) checked @endif>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger btn-quitar-destinatario" title="Quitar fila">&times;</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-agregar-destinatario">
                                <i class="fa fa-plus"></i> Agregar destinatario
                            </button>
                        </div>
                    </div>
                </form>

                @if ($programable)
                <div class="tab-content">
                    @if (! empty($suscripciones_envio))
                    <div class="tab-pane fade {{ $tab === 'suscripciones' ? 'show active' : '' }}" id="tab-suscripciones" role="tabpanel">
                        @include('configuracion.modulo_aviso.partials.suscripciones_envio', [
                            'filas' => $suscripciones_envio,
                            'titulo' => 'Suscripciones de este batch',
                            'ayuda' => 'Editá horario y mails acá (lápiz) o abrí el módulo. Pausá sin tocar el resto.',
                            'mostrarFamilia' => false,
                        ])
                        @include('configuracion.modulo_aviso.partials.modal_editar_suscripcion')
                    </div>
                    @endif
                    <div class="tab-pane fade {{ $tab === 'ejecucion' ? 'show active' : '' }}" id="tab-ejecucion" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <h6>Analizador de impacto</h6>
                                @if (! empty($impacto_resumen))
                                    <p class="small mb-1">
                                        Acción: <strong>{{ $impacto_resumen['accion'] }}</strong>
                                        @if ($impacto_resumen['comando'])
                                            · <code>{{ $impacto_resumen['comando'] }}</code>
                                        @endif
                                        · canales: {{ implode(', ', $impacto_resumen['canales']) }}
                                    </p>
                                    <p class="small mb-1">
                                        Destinatarios catálogo: <strong>{{ $impacto_resumen['destinatarios_count'] }}</strong>
                                        @if ($impacto_resumen['proxima'])
                                            · próxima: {{ $impacto_resumen['proxima'] }}
                                        @endif
                                    </p>
                                    @if (! empty($impacto_resumen['advertencias']))
                                        <ul class="small text-warning pl-3">
                                            @foreach ($impacto_resumen['advertencias'] as $adv)
                                                <li>{{ $adv }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    <p class="small mb-2">Simulación 14 días:
                                        @forelse ($impacto_resumen['simular_14d'] as $f)
                                            <span class="badge badge-info mr-1">{{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}</span>
                                        @empty
                                            <span class="text-muted">ninguna</span>
                                        @endforelse
                                    </p>
                                @endif
                                <div class="btn-group btn-group-sm">
                                    @if (! empty($puede_ejecutar))
                                    <form action="{{ route('ejecutar_ahora_modulo_aviso', $tipo->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Ejecutar ahora? Revisá el impacto.');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="fa fa-bolt"></i> Ejecutar ahora
                                        </button>
                                    </form>
                                    @endif
                                    <a href="{{ route('dry_run_modulo_aviso_programado', $tipo->id) }}" class="btn btn-outline-info btn-sm">
                                        Dry-run 30 días
                                    </a>
                                    <a href="{{ route('impacto_modulo_aviso_programado', $tipo->id) }}" class="btn btn-outline-secondary btn-sm">
                                        Impacto completo
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <h6>Últimas corridas</h6>
                                @if (($logs_recientes ?? collect())->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Inicio</th>
                                                <th>Origen</th>
                                                <th>Exit</th>
                                                <th>Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($logs_recientes as $log)
                                                <tr>
                                                    <td class="text-nowrap">{{ optional($log->inicio_en)->format('d/m/Y H:i') ?? '—' }}</td>
                                                    <td>{{ $log->origen }}</td>
                                                    <td>
                                                        @if ($log->exit_code === 0)
                                                            <span class="badge badge-success">0</span>
                                                        @else
                                                            <span class="badge badge-danger">{{ $log->exit_code }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="small">
                                                        @if ($log->error)
                                                            <span class="text-danger">{{ \Illuminate\Support\Str::limit($log->error, 160) }}</span>
                                                        @else
                                                            {{ \Illuminate\Support\Str::limit($log->salida ?? '—', 160) }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <a href="{{ route('historial_modulo_aviso_programado', ['clave' => $tipo->modulo.'.'.$tipo->codigo]) }}" class="small">
                                    Ver historial global de esta clave
                                </a>
                                @else
                                    <p class="text-muted small mb-0">Sin corridas todavía.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="card-footer">
                @if (can('actualizar-modulo-aviso', false))
                    <button type="submit" class="btn botonsubmit btn-success" form="form-modulo-aviso">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    window._consultaUsuarioOmitirFiltroEmpresaFijo = true;
    window._consultaUsuarioOmitirFiltroEmpresa = true;

    var contador = {{ count($filasDest) }};

    function reiniciarEventosUsuario() {
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    }

    // Solapas fuera del form (ejecución / suscripciones): sincronizar con el tab activo.
    $('#tabs-modulo-aviso a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var href = $(e.target).attr('href');
        var fueraForm = href === '#tab-ejecucion' || href === '#tab-suscripciones';
        if (fueraForm) {
            $('#form-modulo-aviso .tab-pane').removeClass('show active');
            $('#tab-ejecucion, #tab-suscripciones').removeClass('show active');
            $(href).addClass('show active');
        } else {
            $('#tab-ejecucion, #tab-suscripciones').removeClass('show active');
        }
    });

    $('#btn-agregar-destinatario').on('click', function () {
        var $tbody = $('#tabla-destinatarios-aviso tbody');
        var $primera = $tbody.find('tr.fila-destinatario-aviso').first().clone();
        $primera.find('input[type=email], .emailusuario').val('');
        $primera.find('input[type=hidden][name*="[id]"]').val('');
        $primera.find('.usuario_id_arbol').val('');
        $primera.find('.usuario_codigo_arbol').val('');
        $primera.find('.nombreusuario').val('');
        $primera.find('select').prop('selectedIndex', 0);
        $primera.find('input[type=checkbox]').prop('checked', true);
        $primera.find('[name]').each(function () {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + contador + ']'));
            }
        });
        $tbody.append($primera);
        contador++;
        reiniciarEventosUsuario();
    });

    $(document).on('click', '.btn-quitar-destinatario', function () {
        var $rows = $('#tabla-destinatarios-aviso tbody tr');
        if ($rows.length <= 1) {
            var $tr = $(this).closest('tr');
            $tr.find('input[type=email], .emailusuario').val('');
            $tr.find('.usuario_id_arbol').val('');
            $tr.find('.usuario_codigo_arbol').val('');
            $tr.find('.nombreusuario').val('');
            $tr.find('select').prop('selectedIndex', 0);
            return;
        }
        $(this).closest('tr').remove();
    });

    $(function () {
        reiniciarEventosUsuario();
        @if (in_array($tab, ['ejecucion', 'suscripciones'], true))
        $('#form-modulo-aviso .tab-pane').removeClass('show active');
        $('#tab-ejecucion, #tab-suscripciones').removeClass('show active');
        $('#tab-{{ $tab }}').addClass('show active');
        @endif
    });
})();
</script>
@endsection
